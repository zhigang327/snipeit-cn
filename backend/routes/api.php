<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\AssetController;

// 公开路由
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    // 用户相关
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 部门管理
    Route::apiResource('departments', DepartmentController::class);
    Route::get('/departments/tree', [DepartmentController::class, 'getTree']);
    Route::get('/departments/{department}/statistics', [DepartmentController::class, 'statistics']);
    Route::post('/departments/{department}/move', [DepartmentController::class, 'move']);

    // 资产管理
    Route::apiResource('assets', AssetController::class);
    Route::post('/assets/{asset}/checkout', [AssetController::class, 'checkout']);
    Route::post('/assets/{asset}/checkin', [AssetController::class, 'checkin']);
    Route::get('/assets/statistics', [AssetController::class, 'statistics']);

    // 二维码管理
    Route::post('/assets/{asset}/qrcode', [\App\Http\Controllers\AssetQRCodeController::class, 'generate']);
    Route::post('/assets/qrcode/batch', [\App\Http\Controllers\AssetQRCodeController::class, 'batchGenerate']);
    Route::get('/assets/{asset}/qrcode/download', [\App\Http\Controllers\AssetQRCodeController::class, 'download']);
    Route::get('/assets/{asset}/qrcode/print', [\App\Http\Controllers\AssetQRCodeController::class, 'printLabel']);
    Route::post('/assets/scan', [\App\Http\Controllers\AssetQRCodeController::class, 'scan']);

    // 盘点管理
    Route::apiResource('inventories', \App\Http\Controllers\InventoryController::class);
    Route::post('/inventories/{inventory}/scan', [\App\Http\Controllers\InventoryController::class, 'scanAsset']);
    Route::get('/inventories/{inventory}/progress', [\App\Http\Controllers\InventoryController::class, 'progress']);
    Route::post('/inventories/{inventory}/complete', [\App\Http\Controllers\InventoryController::class, 'complete']);

    // 微信配置
    Route::get('/wechat/config', [\App\Http\Controllers\WechatConfigController::class, 'getConfig']);
    Route::put('/wechat/config', [\App\Http\Controllers\WechatConfigController::class, 'updateConfig']);
    Route::post('/wechat/test', [\App\Http\Controllers\WechatConfigController::class, 'testNotification']);
    Route::get('/wechat/notifications', [
        \App\Http\Controllers\WechatConfigController::class, 'getNotificationSettings']);
    Route::put('/wechat/notifications', [
        \App\Http\Controllers\WechatConfigController::class, 'updateNotificationSettings']);

    // 折旧管理
    Route::get('/assets/{asset}/depreciation/calculate', [
        \App\Http\Controllers\DepreciationController::class, 'calculate']);
    Route::post('/assets/{asset}/depreciation/execute', [
        \App\Http\Controllers\DepreciationController::class, 'execute']);
    Route::get('/assets/{asset}/depreciation/records', [
        \App\Http\Controllers\DepreciationController::class, 'records']);
    Route::get('/assets/{asset}/depreciation/schedule', [
        \App\Http\Controllers\DepreciationController::class, 'schedule']);
    Route::post('/depreciation/batch', [
        \App\Http\Controllers\DepreciationController::class, 'executeBatch']);
    Route::get('/depreciation/report', [
        \App\Http\Controllers\DepreciationController::class, 'report']);
    Route::get('/depreciation/statistics', [
        \App\Http\Controllers\DepreciationController::class, 'statistics']);

    // 维修管理
    Route::apiResource('maintenance', \App\Http\Controllers\MaintenanceController::class);
    Route::post('/maintenance/{maintenance}/assign', [
        \App\Http\Controllers\MaintenanceController::class, 'assign']);
    Route::post('/maintenance/{maintenance}/complete', [
        \App\Http\Controllers\MaintenanceController::class, 'complete']);
    Route::post('/maintenance/{maintenance}/cancel', [
        \App\Http\Controllers\MaintenanceController::class, 'cancel']);
    Route::get('/maintenance/statistics', [
        \App\Http\Controllers\MaintenanceController::class, 'statistics']);
    Route::get('/maintenance/overdue', [
        \App\Http\Controllers\MaintenanceController::class, 'overdue']);
    Route::get('/assets/{asset}/maintenance/history', [
        \App\Http\Controllers\MaintenanceController::class, 'assetHistory']);
    Route::post('/maintenance/export', [
        \App\Http\Controllers\MaintenanceController::class, 'export']);

    // 借用管理
    Route::apiResource('borrow', \App\Http\Controllers\BorrowController::class);
    Route::post('/borrow/{borrow}/approve', [
        \App\Http\Controllers\BorrowController::class, 'approve']);
    Route::post('/borrow/{borrow}/reject', [
        \App\Http\Controllers\BorrowController::class, 'reject']);
    Route::post('/borrow/{borrow}/confirm-borrow', [
        \App\Http\Controllers\BorrowController::class, 'confirmBorrow']);
    Route::post('/borrow/{borrow}/return', [
        \App\Http\Controllers\BorrowController::class, 'returnAsset']);
    Route::post('/borrow/{borrow}/cancel', [
        \App\Http\Controllers\BorrowController::class, 'cancel']);
    Route::get('/borrow/statistics', [
        \App\Http\Controllers\BorrowController::class, 'statistics']);
    Route::get('/borrow/overdue', [
        \App\Http\Controllers\BorrowController::class, 'overdue']);
    Route::get('/borrow/check-overdue', [
        \App\Http\Controllers\BorrowController::class, 'checkOverdue']);
    Route::get('/borrow/upcoming-due', [
        \App\Http\Controllers\BorrowController::class, 'upcomingDue']);
    Route::get('/assets/{asset}/borrow/history', [
        \App\Http\Controllers\BorrowController::class, 'assetHistory']);
    Route::get('/users/{user?}/borrow/history', [
        \App\Http\Controllers\BorrowController::class, 'userHistory']);
    Route::post('/borrow/export', [
        \App\Http\Controllers\BorrowController::class, 'export']);

    // 报废管理
    Route::apiResource('disposal', \App\Http\Controllers\DisposalController::class);
    Route::post('/disposal/{disposal}/approve', [
        \App\Http\Controllers\DisposalController::class, 'approve']);
    Route::post('/disposal/{disposal}/reject', [
        \App\Http\Controllers\DisposalController::class, 'reject']);
    Route::post('/disposal/{disposal}/complete', [
        \App\Http\Controllers\DisposalController::class, 'complete']);
    Route::post('/disposal/{disposal}/cancel', [
        \App\Http\Controllers\DisposalController::class, 'cancel']);
    Route::get('/disposal/statistics', [
        \App\Http\Controllers\DisposalController::class, 'statistics']);
    Route::get('/disposal/overdue', [
        \App\Http\Controllers\DisposalController::class, 'overdue']);
    Route::get('/assets/{asset}/disposal/history', [
        \App\Http\Controllers\DisposalController::class, 'assetHistory']);
    Route::post('/disposal/export', [
        \App\Http\Controllers\DisposalController::class, 'export']);
});

Route::get('/', function () {
    return response()->json([
        'message' => 'Snipe-CN API',
        'version' => '1.1.0',
    ]);
});
