<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query();

        // 搜索
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        // 状态筛选
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // 获取树形结构
        if ($request->has('tree') && $request->tree) {
            $departments = Department::getTree();
        } else {
            $departments = $query->with('parent', 'manager')
                ->orderBy('sort')
                ->orderBy('id')
                ->paginate($request->per_page ?? 20);
        }

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'sort' => 'integer',
            'location' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        // 检查是否形成循环引用
        if ($validated['parent_id']) {
            $parent = Department::find($validated['parent_id']);
            $ancestors = $parent->ancestor_ids ?? [];
            if (in_array($validated['parent_id'], $ancestors)) {
                return response()->json([
                    'success' => false,
                    'message' => '不能选择子部门作为父部门'
                ], 422);
            }
        }

        $department = Department::create($validated);

        return response()->json([
            'success' => true,
            'message' => '部门创建成功',
            'data' => $department->load('parent', 'manager')
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $department->load('parent', 'manager', 'children', 'users')
        ]);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => [
                'string',
                'max:50',
                Rule::unique('departments')->ignore($department->id)
            ],
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'sort' => 'integer',
            'location' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        // 检查是否形成循环引用
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            if ($validated['parent_id'] == $department->id) {
                return response()->json([
                    'success' => false,
                    'message' => '不能选择自己作为父部门'
                ], 422);
            }

            $parent = Department::find($validated['parent_id']);
            $ancestors = $parent->ancestor_ids;
            if (in_array($department->id, $ancestors)) {
                return response()->json([
                    'success' => false,
                    'message' => '不能选择子部门作为父部门'
                ], 422);
            }
        }

        $department->update($validated);

        return response()->json([
            'success' => true,
            'message' => '部门更新成功',
            'data' => $department->load('parent', 'manager')
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        // 检查是否有子部门
        if ($department->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该部门下还有子部门,无法删除'
            ], 422);
        }

        // 检查是否有用户
        if ($department->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该部门下还有用户,无法删除'
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => '部门删除成功'
        ]);
    }

    public function getTree(): JsonResponse
    {
        $tree = Department::getTree();

        return response()->json([
            'success' => true,
            'data' => $tree
        ]);
    }

    public function statistics(Department $department): JsonResponse
    {
        $stats = [
            'user_count' => $department->allUsers()->count(),
            'asset_count' => $department->getAllAssetsCount(),
            'total_asset_value' => $department->getTotalAssetValue(),
            'child_count' => $department->children()->count(),
            'depth' => $department->depth,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function move(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:departments,id'
        ]);

        if ($validated['parent_id'] == $department->id) {
            return response()->json([
                'success' => false,
                'message' => '不能移动到自己'
            ], 422);
        }

        // 检查循环引用
        if ($validated['parent_id']) {
            $parent = Department::find($validated['parent_id']);
            if ($department->isAncestorOf($validated['parent_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => '不能移动到子部门下'
                ], 422);
            }
        }

        $department->update(['parent_id' => $validated['parent_id']]);

        return response()->json([
            'success' => true,
            'message' => '部门移动成功',
            'data' => $department->load('parent')
        ]);
    }
}
