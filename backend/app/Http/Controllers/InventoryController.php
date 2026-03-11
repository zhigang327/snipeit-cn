<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Inventory;
use App\Services\WechatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    private $wechatService;

    public function __construct(WechatNotificationService $wechatService)
    {
        $this->wechatService = $wechatService;
    }
    /**
     * 开始盘点
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $inventory = Inventory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'department_id' => $validated['department_id'] ?? null,
            'status' => 'in_progress',
            'created_by' => auth()->id(),
        ]);

        // 发送微信通知
        if (config('services.wechat.notifications.inventory_created', true)) {
            $this->wechatService->sendInventoryTask($inventory->load('creator'));
        }

        return response()->json([
            'success' => true,
            'message' => '盘点创建成功',
            'data' => $inventory
        ], 201);
    }

    /**
     * 扫码盘点资产
     */
    public function scanAsset(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'asset_tag' => 'required|string',
            'status' => 'required|in:found,not_found,lost,damaged',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // 查找资产
        $asset = Asset::where('asset_tag', $validated['asset_tag'])->first();

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => '未找到该资产'
            ], 404);
        }

        // 检查是否已盘点
        $existingItem = $inventory->items()->where('asset_id', $asset->id)->first();
        if ($existingItem) {
            return response()->json([
                'success' => false,
                'message' => '该资产已盘点'
            ], 422);
        }

        // 创建盘点记录
        $inventory->items()->create([
            'asset_id' => $asset->id,
            'expected_location' => $asset->location,
            'actual_location' => $validated['location'] ?? $asset->location,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? '',
            'scanned_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '资产盘点成功',
            'data' => [
                'asset' => $asset->load('category', 'user'),
                'inventory_id' => $inventory->id
            ]
        ]);
    }

    /**
     * 获取盘点进度
     */
    public function progress(Inventory $inventory): JsonResponse
    {
        $total = $inventory->items()->count();
        $found = $inventory->items()->where('status', 'found')->count();
        $lost = $inventory->items()->where('status', 'lost')->count();
        $damaged = $inventory->items()->where('status', 'damaged')->count();
        $notFound = $inventory->items()->where('status', 'not_found')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'found' => $found,
                'lost' => $lost,
                'damaged' => $damaged,
                'not_found' => $notFound,
                'progress' => $total > 0 ? round(($found / $total) * 100, 2) : 0
            ]
        ]);
    }

    /**
     * 完成盘点
     */
    public function complete(Request $request, Inventory $inventory): JsonResponse
    {
        $inventory->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $request->notes ?? '',
            'completed_by' => auth()->id(),
        ]);

        // 更新资产状态
        $items = $inventory->items;
        foreach ($items as $item) {
            $asset = $item->asset;
            if ($item->status === 'lost') {
                $asset->update(['status' => 'lost']);
            } elseif ($item->status === 'damaged') {
                $asset->update(['status' => 'broken']);
            }
        }

        // 发送微信通知
        if (config('services.wechat.notifications.inventory_completed', true)) {
            $inventory->refresh();
            $this->wechatService->sendInventoryCompleted($inventory->load('completer'));
        }

        return response()->json([
            'success' => true,
            'message' => '盘点完成'
        ]);
    }

    /**
     * 获取所有盘点列表
     */
    public function index(Request $request): JsonResponse
    {
        $inventories = Inventory::with('department', 'items.asset')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $inventories
        ]);
    }

    /**
     * 获取盘点详情
     */
    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $inventory->load('department', 'items.asset.category', 'items.asset.user', 'items.scannedBy')
        ]);
    }
}
