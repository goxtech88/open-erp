<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 — Clientes (CRM)
 * Ruta base: /api/v1/customers
 */
class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->when($request->search, fn($q, $v) => $q->where('name', 'ilike', "%{$v}%")
                ->orWhere('cuit', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'total'        => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;
        $customer  = Customer::where('company_id', $companyId)->findOrFail($id);

        return response()->json(['data' => $customer]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'cuit'     => 'nullable|string|max:15',
            'email'    => 'nullable|email',
            'phone'    => 'nullable|string',
            'address'  => 'nullable|string',
            'tax_type' => 'nullable|in:RI,MO,EX,CF',
        ]);

        $companyId = $request->attributes->get('api_key')->company_id;

        $customer = Customer::create(
            array_merge($request->validated(), ['company_id' => $companyId])
        );

        // Disparar evento para webhooks
        event('customer.created', ['customer' => $customer]);

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;
        $customer  = Customer::where('company_id', $companyId)->findOrFail($id);

        $customer->update($request->except(['company_id']));

        return response()->json(['data' => $customer->fresh()]);
    }
}
