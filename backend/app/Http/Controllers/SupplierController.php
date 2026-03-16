<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * 获取供应商列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        if ($request->has('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%");
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $suppliers = $query->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $suppliers,
        ]);
    }

    /**
     * 创建供应商
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|max:50|unique:suppliers,code',
            'contact'      => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
            'tax_number'   => 'nullable|string|max:100',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => '供应商创建成功',
            'data'    => $supplier,
        ], 201);
    }

    /**
     * 获取单个供应商
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $supplier,
        ]);
    }

    /**
     * 更新供应商
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'string|max:255',
            'code'         => 'string|max:50|unique:suppliers,code,' . $supplier->id,
            'contact'      => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
            'tax_number'   => 'nullable|string|max:100',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => '供应商更新成功',
            'data'    => $supplier->fresh(),
        ]);
    }

    /**
     * 删除供应商
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->assets()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该供应商下有资产记录，无法删除',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => '供应商删除成功',
        ]);
    }
}
