<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\User;
use App\Services\WechatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    private $wechatService;

    public function __construct(WechatNotificationService $wechatService)
    {
        $this->wechatService = $wechatService;
    }
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->with('category', 'department', 'user', 'supplier');

        // 搜索
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('asset_tag', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%')
                    ->orWhere('model', 'like', '%' . $search . '%');
            });
        }

        // 筛选
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 排序
        $query->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc');

        $assets = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $assets
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_tag' => 'required|string|max:50|unique:assets',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:asset_categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_price' => 'numeric|min:0',
            'purchase_date' => 'nullable|date',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'warranty_months' => 'integer|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'ready';

        // 计算保修到期日期
        if (!empty($validated['purchase_date']) && !empty($validated['warranty_months'])) {
            $validated['warranty_expiry'] = date('Y-m-d', strtotime($validated['purchase_date'] . ' +' . $validated['warranty_months'] . ' months'));
        }

        $asset = Asset::create($validated);

        // 记录历史
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'action' => 'create',
            'notes' => '创建资产',
            'new_values' => $validated
        ]);

        return response()->json([
            'success' => true,
            'message' => '资产创建成功',
            'data' => $asset->load('category', 'department', 'user', 'supplier')
        ], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $asset->load('category', 'department', 'user', 'supplier', 'createdBy', 'histories.user', 'histories.targetUser')
        ]);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'asset_tag' => [
                'string',
                'max:50',
                Rule::unique('assets')->ignore($asset->id)
            ],
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'exists:asset_categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_price' => 'numeric|min:0',
            'purchase_date' => 'nullable|date',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'warranty_months' => 'integer|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'in:ready,assigned,maintenance,broken,lost,scrapped',
        ]);

        $oldValues = $asset->toArray();
        $validated['updated_by'] = auth()->id();

        // 计算保修到期日期
        if (!empty($validated['purchase_date']) && !empty($validated['warranty_months'])) {
            $validated['warranty_expiry'] = date('Y-m-d', strtotime($validated['purchase_date'] . ' +' . $validated['warranty_months'] . ' months'));
        }

        $asset->update($validated);

        // 记录历史
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'action' => 'update',
            'notes' => '更新资产信息',
            'old_values' => array_intersect_key($oldValues, $validated),
            'new_values' => $validated
        ]);

        return response()->json([
            'success' => true,
            'message' => '资产更新成功',
            'data' => $asset->load('category', 'department', 'user', 'supplier')
        ]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        if ($asset->status === 'assigned') {
            return response()->json([
                'success' => false,
                'message' => '资产已分配给用户,无法删除'
            ], 422);
        }

        // 记录历史
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'action' => 'delete',
            'notes' => '删除资产',
            'old_values' => $asset->toArray()
        ]);

        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => '资产删除成功'
        ]);
    }

    public function checkout(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->status !== 'ready') {
            return response()->json([
                'success' => false,
                'message' => '资产当前状态不允许分配'
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'expected_checkin_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string',
        ]);

        $user = User::find($validated['user_id']);
        if (!$validated['department_id']) {
            $validated['department_id'] = $user->department_id;
        }

        $asset->update([
            'status' => 'assigned',
            'user_id' => $validated['user_id'],
            'department_id' => $validated['department_id'],
            'checkout_date' => now(),
            'expected_checkin_date' => $validated['expected_checkin_date'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        // 记录历史
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'action' => 'checkout',
            'notes' => $validated['notes'] ?? '资产领用',
            'target_user_id' => $validated['user_id'],
            'target_department_id' => $validated['department_id'],
        ]);

        // 发送微信通知
        if (config('services.wechat.notifications.asset_changed', true)) {
            $this->wechatService->sendAssetChanged($asset->load('user', 'department'), 'checkout', null, $user);
        }

        return response()->json([
            'success' => true,
            'message' => '资产分配成功',
            'data' => $asset->load('category', 'department', 'user')
        ]);
    }

    public function checkin(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->status !== 'assigned') {
            return response()->json([
                'success' => false,
                'message' => '资产当前状态不允许归还'
            ], 422);
        }

        $validated = $request->validate([
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $asset->update([
            'status' => 'ready',
            'user_id' => null,
            'checkout_date' => null,
            'expected_checkin_date' => null,
            'location' => $validated['location'] ?? $asset->location,
            'updated_by' => auth()->id(),
        ]);

        // 记录历史
        $previousUser = $asset->user;
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'action' => 'checkin',
            'notes' => $validated['notes'] ?? '资产归还',
            'target_user_id' => $previousUser->id ?? null,
        ]);

        // 发送微信通知
        if (config('services.wechat.notifications.asset_changed', true)) {
            $asset->refresh();
            $this->wechatService->sendAssetChanged($asset, 'checkin', $previousUser, null);
        }

        return response()->json([
            'success' => true,
            'message' => '资产归还成功',
            'data' => $asset->load('category', 'department', 'user')
        ]);
    }

    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Asset::count(),
            'ready' => Asset::where('status', 'ready')->count(),
            'assigned' => Asset::where('status', 'assigned')->count(),
            'maintenance' => Asset::where('status', 'maintenance')->count(),
            'broken' => Asset::where('status', 'broken')->count(),
            'lost' => Asset::where('status', 'lost')->count(),
            'scrapped' => Asset::where('status', 'scrapped')->count(),
            'total_value' => Asset::sum('purchase_price'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
