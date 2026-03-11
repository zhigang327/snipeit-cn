<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WechatNotificationService
{
    private $webhookUrl;
    private $enabled;

    public function __construct()
    {
        $this->enabled = config('services.wechat.enabled', false);
        $this->webhookUrl = config('services.wechat.webhook_url');
    }

    /**
     * 发送文本消息
     */
    public function sendText($content, array $mentionedList = [])
    {
        if (!$this->enabled || !$this->webhookUrl) {
            Log::info('微信通知未启用');
            return false;
        }

        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ]
        ];

        if (!empty($mentionedList)) {
            $data['text']['mentioned_list'] = $mentionedList;
        }

        return $this->send($data);
    }

    /**
     * 发送Markdown消息
     */
    public function sendMarkdown($content)
    {
        if (!$this->enabled || !$this->webhookUrl) {
            Log::info('微信通知未启用');
            return false;
        }

        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $content,
            ]
        ];

        return $this->send($data);
    }

    /**
     * 发送资产到期提醒
     */
    public function sendAssetExpiring($asset, $days)
    {
        $content = "## ⚠️ 资产即将到期提醒\n\n" .
                   "> **资产名称**: {$asset->name}\n" .
                   "> **资产编号**: {$asset->asset_tag}\n" .
                   "> **当前持有人**: {$asset->user->name}\n" .
                   "> **所属部门**: {$asset->department->name}\n" .
                   "> **到期时间**: {$asset->expiry_date}\n" .
                   "> **剩余天数**: {$days}天\n\n" .
                   "请及时处理资产归还或续期操作。";

        return $this->sendMarkdown($content);
    }

    /**
     * 发送资产变动通知
     */
    public function sendAssetChanged($asset, $action, $fromUser = null, $toUser = null)
    {
        $actionText = [
            'checkout' => '领用',
            'checkin' => '归还',
            'transfer' => '转移',
        ];

        $content = "## 📱 资产变动通知\n\n" .
                   "> **资产名称**: {$asset->name}\n" .
                   "> **资产编号**: {$asset->asset_tag}\n" .
                   "> **操作类型**: {$actionText[$action]}\n";

        if ($fromUser) {
            $content .= "> **原持有人**: {$fromUser->name}\n";
        }

        if ($toUser) {
            $content .= "> **新持有人**: {$toUser->name}\n";
        }

        $content .= "> **所属部门**: {$asset->department->name}\n" .
                   "> **操作时间**: " . now()->format('Y-m-d H:i:s') . "\n";

        return $this->sendMarkdown($content);
    }

    /**
     * 发送盘点任务通知
     */
    public function sendInventoryTask($inventory)
    {
        $content = "## 📋 盘点任务创建通知\n\n" .
                   "> **任务编号**: {$inventory->id}\n" .
                   "> **任务名称**: {$inventory->name}\n" .
                   "> **创建人**: {$inventory->creator->name}\n" .
                   "> **创建时间**: {$inventory->created_at}\n" .
                   "> **盘点范围**: {$inventory->description}\n\n" .
                   "请及时完成盘点任务。";

        return $this->sendMarkdown($content);
    }

    /**
     * 发送盘点完成通知
     */
    public function sendInventoryCompleted($inventory)
    {
        $content = "## ✅ 盘点任务完成通知\n\n" .
                   "> **任务编号**: {$inventory->id}\n" .
                   "> **任务名称**: {$inventory->name}\n" .
                   "> **完成时间**: {$inventory->completed_at}\n" .
                   "> **总资产数**: {$inventory->total_assets}\n" .
                   "> **已盘点**: {$inventory->scanned_assets}\n" .
                   "> **正常资产**: {$inventory->normal_assets}\n" .
                   "> **异常资产**: {$inventory->abnormal_assets}\n" .
                   "> **异常率**: " . ($inventory->abnormal_assets / $inventory->total_assets * 100) . "%\n" .
                   "> **操作人**: {$inventory->completer->name}\n";

        return $this->sendMarkdown($content);
    }

    /**
     * 发送HTTP请求
     */
    private function send($data)
    {
        try {
            $response = Http::post($this->webhookUrl, $data);

            if ($response->successful()) {
                Log::info('微信通知发送成功', ['data' => $data]);
                return true;
            } else {
                Log::error('微信通知发送失败', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('微信通知发送异常', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查配置是否有效
     */
    public function checkConfig()
    {
        if (!$this->enabled || !$this->webhookUrl) {
            return [
                'enabled' => false,
                'message' => '微信通知未配置'
            ];
        }

        // 发送测试消息
        $result = $this->sendText('【测试消息】微信通知配置成功!');

        return [
            'enabled' => true,
            'webhook_url' => $this->webhookUrl,
            'status' => $result ? 'success' : 'failed',
            'message' => $result ? '测试消息发送成功' : '测试消息发送失败,请检查Webhook URL'
        ];
    }
}
