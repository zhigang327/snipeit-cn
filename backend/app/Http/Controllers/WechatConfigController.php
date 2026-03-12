<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Services\WechatNotificationService;

class WechatConfigController extends Controller
{
    private $wechatService;

    public function __construct(WechatNotificationService $wechatService)
    {
        $this->wechatService = $wechatService;
    }

    /**
     * 获取微信配置
     */
    public function getConfig()
    {
        return response()->json([
            'enabled' => Config::get('services.wechat.enabled', false),
            'webhook_url' => Config::get('services.wechat.webhook_url', ''),
        ]);
    }

    /**
     * 更新微信配置
     */
    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'webhook_url' => 'nullable|string',
        ]);

        // 更新配置文件
        $configPath = config_path('services.php');

        if (file_exists($configPath)) {
            $config = require $configPath;
        } else {
            $config = [];
        }

        if (!isset($config['wechat'])) {
            $config['wechat'] = [];
        }

        $config['wechat']['enabled'] = $validated['enabled'] ?? false;
        if (isset($validated['webhook_url'])) {
            $config['wechat']['webhook_url'] = $validated['webhook_url'];
        }

        // 写入配置
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configPath, $content);

        // 清除配置缓存
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return response()->json([
            'message' => '配置更新成功',
            'data' => $validated
        ]);
    }

    /**
     * 测试微信通知
     */
    public function testNotification()
    {
        $result = $this->wechatService->checkConfig();

        return response()->json($result);
    }

    /**
     * 获取微信通知开关配置
     */
    public function getNotificationSettings()
    {
        return response()->json([
            'asset_expiring' => Config::get('services.wechat.notifications.asset_expiring', true),
            'asset_changed' => Config::get('services.wechat.notifications.asset_changed', true),
            'inventory_created' => Config::get('services.wechat.notifications.inventory_created', true),
            'inventory_completed' => Config::get('services.wechat.notifications.inventory_completed', true),
            'maintenance' => Config::get('services.wechat.notifications.maintenance', true),
        ]);
    }

    /**
     * 更新通知开关配置
     */
    public function updateNotificationSettings(Request $request)
    {
        $validated = $request->validate([
            'asset_expiring' => 'boolean',
            'asset_changed' => 'boolean',
            'inventory_created' => 'boolean',
            'inventory_completed' => 'boolean',
            'maintenance' => 'boolean',
        ]);

        $configPath = config_path('services.php');

        if (file_exists($configPath)) {
            $config = require $configPath;
        } else {
            $config = [];
        }

        if (!isset($config['wechat'])) {
            $config['wechat'] = [];
        }

        if (!isset($config['wechat']['notifications'])) {
            $config['wechat']['notifications'] = [];
        }

        foreach ($validated as $key => $value) {
            $config['wechat']['notifications'][$key] = $value;
        }

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configPath, $content);

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return response()->json([
            'message' => '通知设置更新成功',
            'data' => $validated
        ]);
    }
}
