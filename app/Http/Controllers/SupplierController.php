<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('fiscal_id', 'ilike', "%{$search}%");
            });
        }

        $suppliers = $query->paginate(20)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSupplier($request);
        $data['company_id'] = auth()->user()->company_id;

        Supplier::create($data);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeCompany($supplier);
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeCompany($supplier);
        $supplier->update($this->validateSupplier($request, $supplier->id));

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeCompany($supplier);
        $supplier->update(['active' => false]);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor dado de baja.');
    }

    private function validateSupplier(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'code'       => [
                'required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('suppliers')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'name'        => 'required|string|max:255',
            'fiscal_id'   => 'nullable|string|max:20',
            'fiscal_type' => 'nullable|in:DNI,CUIT',
            'address'     => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:30',
        ]);
    }

    private function authorizeCompany(Supplier $supplier): void
    {
        abort_unless($supplier->company_id === auth()->user()->company_id, 403);
    }
}
