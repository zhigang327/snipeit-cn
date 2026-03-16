<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssetCategoryController extends Controller
{
    /**
     * 获取分类列表（支持树形结构）
     */
    public function index(Request $request): JsonResponse
    {
        $tree = $request->boolean('tree', false);

        if ($tree) {
            $categories = AssetCategory::getTree()->load('children');
            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        }

        $categories = AssetCategory::with('parent')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * 创建分类
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:50|unique:asset_categories,code',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:asset_categories,id',
            'image'       => 'nullable|string',
            'sort'        => 'integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $category = AssetCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => '分类创建成功',
            'data'    => $category,
        ], 201);
    }

    /**
     * 获取单个分类
     */
    public function show(AssetCategory $assetCategory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $assetCategory->load('parent', 'children'),
        ]);
    }

    /**
     * 更新分类
     */
    public function update(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'string|max:100',
            'code'        => 'string|max:50|unique:asset_categories,code,' . $assetCategory->id,
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:asset_categories,id',
            'image'       => 'nullable|string',
            'sort'        => 'integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $assetCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => '分类更新成功',
            'data'    => $assetCategory->fresh(),
        ]);
    }

    /**
     * 删除分类
     */
    public function destroy(AssetCategory $assetCategory): JsonResponse
    {
        if ($assetCategory->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该分类下有子分类，无法删除',
            ], 422);
        }

        if ($assetCategory->assets()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该分类下有资产，无法删除',
            ], 422);
        }

        $assetCategory->delete();

        return response()->json([
            'success' => true,
            'message' => '分类删除成功',
        ]);
    }
}
