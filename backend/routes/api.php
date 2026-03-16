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

    // 用户管理
    Route::get('/users/profile', [\App\Http\Controllers\UserController::class, 'profile']);
    Route::post('/users/change-password', [\App\Http\Controllers\UserController::class, 'changePassword']);
    Route::apiResource('users', \App\Http\Controllers\UserController::class);

    // 部门管理（固定路由必须在 apiResource 之前）
    Route::get('/departments/tree', [DepartmentController::class, 'getTree']);
    Route::apiResource('departments', DepartmentController::class);
    Route::get('/departments/{department}/statistics', [DepartmentController::class, 'statistics']);
    Route::post('/departments/{department}/move', [DepartmentController::class, 'move']);

    // 资产管理（固定路由必须在 apiResource 之前）
    Route::get('/assets/statistics', [AssetController::class, 'statistics']);
    Route::post('/assets/qrcode/batch', [\App\Http\Controllers\AssetQRCodeController::class, 'batchGenerate']);
    Route::post('/assets/scan', [\App\Http\Controllers\AssetQRCodeController::class, 'scan']);
    Route::apiResource('assets', AssetController::class);
    Route::post('/assets/{asset}/checkout', [AssetController::class, 'checkout']);
    Route::post('/assets/{asset}/checkin', [AssetController::class, 'checkin']);

    // 二维码管理
    Route::post('/assets/{asset}/qrcode', [\App\Http\Controllers\AssetQRCodeController::class, 'generate']);
    Route::get('/assets/{asset}/qrcode/download', [\App\Http\Controllers\AssetQRCodeController::class, 'download']);
    Route::get('/assets/{asset}/qrcode/print', [\App\Http\Controllers\AssetQRCodeController::class, 'printLabel']);

    // 二维码管理
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

    // 维修管理（固定路由必须在 apiResource 之前）
    Route::get('/maintenance/statistics', [
        \App\Http\Controllers\MaintenanceController::class, 'statistics']);
    Route::get('/maintenance/overdue', [
        \App\Http\Controllers\MaintenanceController::class, 'overdue']);
    Route::post('/maintenance/export', [
        \App\Http\Controllers\MaintenanceController::class, 'export']);
    Route::apiResource('maintenance', \App\Http\Controllers\MaintenanceController::class);
    Route::post('/maintenance/{maintenance}/assign', [
        \App\Http\Controllers\MaintenanceController::class, 'assign']);
    Route::post('/maintenance/{maintenance}/complete', [
        \App\Http\Controllers\MaintenanceController::class, 'complete']);
    Route::post('/maintenance/{maintenance}/cancel', [
        \App\Http\Controllers\MaintenanceController::class, 'cancel']);
    Route::get('/assets/{asset}/maintenance/history', [
        \App\Http\Controllers\MaintenanceController::class, 'assetHistory']);

    // 借用管理（固定路由必须在 apiResource 之前）
    Route::get('/borrow/statistics', [
        \App\Http\Controllers\BorrowController::class, 'statistics']);
    Route::get('/borrow/overdue', [
        \App\Http\Controllers\BorrowController::class, 'overdue']);
    Route::get('/borrow/check-overdue', [
        \App\Http\Controllers\BorrowController::class, 'checkOverdue']);
    Route::get('/borrow/upcoming-due', [
        \App\Http\Controllers\BorrowController::class, 'upcomingDue']);
    Route::post('/borrow/export', [
        \App\Http\Controllers\BorrowController::class, 'export']);
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
    Route::get('/assets/{asset}/borrow/history', [
        \App\Http\Controllers\BorrowController::class, 'assetHistory']);
    Route::get('/users/{user?}/borrow/history', [
        \App\Http\Controllers\BorrowController::class, 'userHistory']);

    // 报废管理（固定路由必须在 apiResource 之前）
    Route::get('/disposal/statistics', [
        \App\Http\Controllers\DisposalController::class, 'statistics']);
    Route::get('/disposal/overdue', [
        \App\Http\Controllers\DisposalController::class, 'overdue']);
    Route::post('/disposal/export', [
        \App\Http\Controllers\DisposalController::class, 'export']);
    Route::apiResource('disposal', \App\Http\Controllers\DisposalController::class);
    Route::post('/disposal/{disposal}/approve', [
        \App\Http\Controllers\DisposalController::class, 'approve']);
    Route::post('/disposal/{disposal}/reject', [
        \App\Http\Controllers\DisposalController::class, 'reject']);
    Route::post('/disposal/{disposal}/complete', [
        \App\Http\Controllers\DisposalController::class, 'complete']);
    Route::post('/disposal/{disposal}/cancel', [
        \App\Http\Controllers\DisposalController::class, 'cancel']);
    Route::get('/assets/{asset}/disposal/history', [
        \App\Http\Controllers\DisposalController::class, 'assetHistory']);

    // 盘点管理
    Route::get('/inventory/tasks', [
        \App\Http\Controllers\InventoryController::class, 'tasks']);
    Route::post('/inventory/tasks', [
        \App\Http\Controllers\InventoryController::class, 'createTask']);
    Route::get('/inventory/tasks/{task}', [
        \App\Http\Controllers\InventoryController::class, 'taskDetail']);
    Route::put('/inventory/tasks/{task}', [
        \App\Http\Controllers\InventoryController::class, 'updateTask']);
    Route::post('/inventory/tasks/{task}/start', [
        \App\Http\Controllers\InventoryController::class, 'startTask']);
    Route::post('/inventory/tasks/{task}/complete', [
        \App\Http\Controllers\InventoryController::class, 'completeTask']);
    Route::post('/inventory/tasks/{task}/cancel', [
        \App\Http\Controllers\InventoryController::class, 'cancelTask']);
    
    Route::get('/inventory/records', [
        \App\Http\Controllers\InventoryController::class, 'records']);
    Route::post('/inventory/records', [
        \App\Http\Controllers\InventoryController::class, 'createRecord']);
    Route::get('/inventory/records/{record}', [
        \App\Http\Controllers\InventoryController::class, 'recordDetail']);
    Route::post('/inventory/records/{record}/review', [
        \App\Http\Controllers\InventoryController::class, 'reviewRecord']);
    
    Route::get('/inventory/statistics', [
        \App\Http\Controllers\InventoryController::class, 'statistics']);
    Route::get('/inventory/pending-reviews', [
        \App\Http\Controllers\InventoryController::class, 'pendingReviews']);
    Route::get('/inventory/issue-records', [
        \App\Http\Controllers\InventoryController::class, 'issueRecords']);
    Route::get('/inventory/todays-tasks', [
        \App\Http\Controllers\InventoryController::class, 'todaysTasks']);
    Route::get('/inventory/overdue-tasks', [
        \App\Http\Controllers\InventoryController::class, 'overdueTasks']);
    
    Route::get('/assets/{asset}/inventory/history', [
        \App\Http\Controllers\InventoryController::class, 'assetHistory']);
    
    Route::post('/inventory/scan-qr', [
        \App\Http\Controllers\InventoryController::class, 'scanQrCode']);
    Route::post('/inventory/export', [
        \App\Http\Controllers\InventoryController::class, 'export']);
});

Route::get('/', function () {
    return response()->json([
        'message' => 'Snipe-CN API',
        'version' => '1.1.0',
    ]);
});
