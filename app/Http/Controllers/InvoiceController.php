<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Supplier;
use App\Services\AfipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InvoiceController
 *
 * Gestiona tanto Ventas (type=sale) como Compras (type=purchase).
 * La vista determina el contexto pasando el parámetro `type`.
 */
class InvoiceController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request, string $type)
    {
        $this->validateType($type);

        $query = Invoice::with($type === Invoice::TYPE_SALE ? 'customer' : 'supplier')
            ->where('company_id', auth()->user()->company_id)
            ->where('type', $type)
            ->orderByDesc('date')
            ->orderByDesc('number');

        if ($search = $request->input('q')) {
            if ($type === Invoice::TYPE_SALE) {
                $query->whereHas('customer', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
            } else {
                $query->whereHas('supplier', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
            }
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $invoices = $query->paginate(20)->withQueryString();

        return view("invoices.index", compact('invoices', 'type'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(string $type)
    {
        $this->validateType($type);
        $companyId = auth()->user()->company_id;

        $customers = Customer::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();
        $articles  = Article::where('company_id', $companyId)->where('active', true)->orderBy('description')->get();

        $invoice = new Invoice(['type' => $type, 'date' => today(), 'pos_number' => 1, 'invoice_code' => 'B']);

        return view('invoices.form', compact('invoice', 'type', 'customers', 'suppliers', 'articles'));
    }

    public function store(Request $request, string $type)
    {
        $this->validateType($type);
        $data = $this->validateInvoice($request, $type);

        DB::transaction(function () use ($data, $type, $request) {
            $invoice = Invoice::create(array_merge($data, [
                'company_id' => auth()->user()->company_id,
                'type'       => $type,
                'status'     => Invoice::STATUS_DRAFT,
            ]));

            $this->syncLines($invoice, $request->input('lines', []));
            $invoice->recalcTotals();
            $invoice->save();
        });

        return redirect()->route("invoices.index", ['type' => $type])
            ->with('success', 'Comprobante guardado como borrador.');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(string $type, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice, $type);
        abort_unless($invoice->isDraft(), 403, 'Solo se puede editar comprobantes en borrador.');

        $companyId = auth()->user()->company_id;
        $customers = Customer::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();
        $articles  = Article::where('company_id', $companyId)->where('active', true)->orderBy('description')->get();

        $invoice->load('lines.article');

        return view('invoices.form', compact('invoice', 'type', 'customers', 'suppliers', 'articles'));
    }

    public function update(Request $request, string $type, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice, $type);
        abort_unless($invoice->isDraft(), 403, 'Solo se puede editar comprobantes en borrador.');

        $data = $this->validateInvoice($request, $type);

        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->update($data);
            $this->syncLines($invoice, $request->input('lines', []));
            $invoice->recalcTotals();
            $invoice->save();
        });

        return redirect()->route("invoices.index", ['type' => $type])
            ->with('success', 'Comprobante actualizado.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(string $type, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice, $type);
        abort_unless($invoice->isDraft(), 403, 'No se puede eliminar comprobantes autorizados.');

        $invoice->delete();

        return redirect()->route("invoices.index", ['type' => $type])
            ->with('success', 'Borrador eliminado.');
    }

    // ── AFIP: Autorizar ───────────────────────────────────────────────────────

    public function authorize(string $type, Invoice $invoice, AfipService $afip)
    {
        $this->authorizeInvoice($invoice, $type);
        abort_unless($invoice->isDraft(), 403, 'El comprobante ya fue autorizado o cancelado.');

        try {
            $invoice = $afip->authorize($invoice);
            return redirect()->route("invoices.index", ['type' => $type])
                ->with('success', "CAE obtenido: {$invoice->cae} — Vto: {$invoice->cae_expiry->format('d/m/Y')}");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error AFIP: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateType(string $type): void
    {
        abort_unless(in_array($type, [Invoice::TYPE_SALE, Invoice::TYPE_PURCHASE]), 404);
    }

    private function authorizeInvoice(Invoice $invoice, string $type): void
    {
        abort_unless(
            $invoice->company_id === auth()->user()->company_id && $invoice->type === $type,
            403
        );
    }

    private function validateInvoice(Request $request, string $type): array
    {
        $rules = [
            'invoice_code' => 'required|in:A,B,C,M,X',
            'pos_number'   => 'required|integer|min:1',
            'date'         => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ];

        if ($type === Invoice::TYPE_SALE) {
            $rules['customer_id'] = 'required|exists:customers,id';
        } else {
            $rules['supplier_id'] = 'required|exists:suppliers,id';
        }

        return $request->validate($rules);
    }

    private function syncLines(Invoice $invoice, array $linesInput): void
    {
        $invoice->lines()->delete();

        foreach ($linesInput as $i => $lineData) {
            if (empty($lineData['description'])) continue;

            $line = new InvoiceLine([
                'article_id'  => $lineData['article_id'] ?? null,
                'description' => $lineData['description'],
                'quantity'    => (float) ($lineData['quantity'] ?? 1),
                'unit_price'  => (float) ($lineData['unit_price'] ?? 0),
                'vat_rate'    => (float) ($lineData['vat_rate'] ?? 21),
                'sort_order'  => $i,
            ]);
            $line->calcSubtotal();
            $invoice->lines()->save($line);
        }
    }
}
