<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('fiscal_id', 'ilike', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCustomer($request);
        $data['company_id'] = auth()->user()->company_id;

        Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function edit(Customer $customer)
    {
        $this->authorizeCompany($customer);
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCompany($customer);
        $customer->update($this->validateCustomer($request, $customer->id));

        return redirect()->route('customers.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeCompany($customer);
        $customer->update(['active' => false]);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente dado de baja.');
    }

    private function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'code'       => [
                'required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('customers')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'name'        => 'required|string|max:255',
            'fiscal_id'   => 'nullable|string|max:20',
            'fiscal_type' => 'nullable|in:DNI,CUIT,PASSPORT',
            'address'     => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:30',
        ]);
    }

    private function authorizeCompany(Customer $customer): void
    {
        abort_unless($customer->company_id === auth()->user()->company_id, 403);
    }
}
