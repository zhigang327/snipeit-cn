<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetQRCodeController extends Controller
{
    /**
     * 生成资产二维码
     */
    public function generate(Request $request, Asset $asset): JsonResponse
    {
        // 生成二维码内容: 资产访问URL
        $qrContent = url('/api/assets/' . $asset->id);

        // 生成二维码
        $qrCode = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($qrContent);

        // 保存到存储
        $filename = 'qr_codes/asset_' . $asset->id . '_' . time() . '.png';
        Storage::disk('public')->put($filename, $qrCode);
        $qrUrl = Storage::disk('public')->url($filename);

        // 更新资产记录
        $asset->update(['qr_code' => $qrUrl]);

        return response()->json([
            'success' => true,
            'message' => '二维码生成成功',
            'data' => [
                'qr_code_url' => $qrUrl,
                'qr_content' => $qrContent
            ]
        ]);
    }

    /**
     * 批量生成资产二维码
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:assets,id'
        ]);

        $results = [];
        foreach ($validated['asset_ids'] as $assetId) {
            $asset = Asset::find($assetId);
            if ($asset) {
                $qrContent = url('/api/assets/' . $asset->id);
                $qrCode = QrCode::format('png')
                    ->size(300)
                    ->margin(2)
                    ->errorCorrection('H')
                    ->generate($qrContent);

                $filename = 'qr_codes/asset_' . $asset->id . '_' . time() . '.png';
                Storage::disk('public')->put($filename, $qrCode);
                $qrUrl = Storage::disk('public')->url($filename);

                $asset->update(['qr_code' => $qrUrl]);

                $results[] = [
                    'asset_id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'qr_code_url' => $qrUrl
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => '批量生成二维码成功',
            'data' => $results
        ]);
    }

    /**
     * 下载二维码(打印用)
     */
    public function download(Request $request, Asset $asset)
    {
        if (!$asset->qr_code) {
            return response()->json([
                'success' => false,
                'message' => '该资产尚未生成二维码'
            ], 404);
        }

        // 获取二维码文件路径
        $path = str_replace('/storage', '', $asset->qr_code);

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => '二维码文件不存在'
            ], 404);
        }

        return Storage::disk('public')->download($path, $asset->asset_tag . '_qrcode.png');
    }

    /**
     * 扫码获取资产信息
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string'
        ]);

        // 方式1: 直接扫描资产标签
        $asset = Asset::where('asset_tag', $validated['code'])
            ->with('category', 'department', 'user', 'supplier')
            ->first();

        if (!$asset) {
            // 方式2: 解析二维码URL获取资产ID
            // URL格式: /api/assets/{id}
            if (preg_match('/assets\/(\d+)/', $validated['code'], $matches)) {
                $assetId = $matches[1];
                $asset = Asset::with('category', 'department', 'user', 'supplier')
                    ->find($assetId);
            }
        }

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => '未找到对应资产'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $asset
        ]);
    }

    /**
     * 生成打印标签HTML
     */
    public function printLabel(Request $request, Asset $asset)
    {
        $qrCodeUrl = $asset->qr_code;

        if (!$qrCodeUrl) {
            // 临时生成二维码
            $qrContent = url('/api/assets/' . $asset->id);
            $qrCode = QrCode::format('png')
                ->size(200)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($qrContent);

            $filename = 'qr_codes/temp_asset_' . $asset->id . '.png';
            Storage::disk('public')->put($filename, $qrCode);
            $qrCodeUrl = Storage::disk('public')->url($filename);
        }

        return view('assets.label', [
            'asset' => $asset,
            'qrCodeUrl' => $qrCodeUrl
        ]);
    }
}
