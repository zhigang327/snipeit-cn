<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 获取用户列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('department');

        // 关键词搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        // 按部门筛选
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // 按激活状态筛选
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 20);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * 获取单个用户
     */
    public function show(User $user): JsonResponse
    {
        $user->load('department', 'roles');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * 创建用户
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'phone'         => 'nullable|string|max:20',
            'employee_id'   => 'nullable|string|max:50',
            'position'      => 'nullable|string|max:100',
            'department_id' => 'nullable|integer|exists:departments,id',
            'hire_date'     => 'nullable|date',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        $user->load('department');

        return response()->json([
            'success' => true,
            'message' => '用户创建成功',
            'data'    => $user,
        ], 201);
    }

    /**
     * 更新用户
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'      => 'sometimes|nullable|string|min:6',
            'phone'         => 'nullable|string|max:20',
            'employee_id'   => 'nullable|string|max:50',
            'position'      => 'nullable|string|max:100',
            'department_id' => 'nullable|integer|exists:departments,id',
            'hire_date'     => 'nullable|date',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('department');

        return response()->json([
            'success' => true,
            'message' => '用户更新成功',
            'data'    => $user,
        ]);
    }

    /**
     * 删除用户（软删除）
     */
    public function destroy(User $user): JsonResponse
    {
        // 防止删除当前登录用户
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => '不能删除当前登录用户',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => '用户已删除',
        ]);
    }

    /**
     * 获取当前用户个人信息
     */
    public function profile(): JsonResponse
    {
        $user = auth()->user()->load('department', 'roles');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * 更新当前用户密码
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '当前密码不正确',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->input('new_password'))]);

        return response()->json([
            'success' => true,
            'message' => '密码修改成功',
        ]);
    }
}
