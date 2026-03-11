# 微信通知配置指南

## 功能简介

Snipe-CN集成了企业微信机器人通知功能,可以在以下场景自动发送通知:

- **资产到期提醒**: 资产即将到期时发送提醒
- **资产变动通知**: 资产领用、归还时发送通知
- **盘点任务创建**: 创建盘点任务时发送通知
- **盘点完成通知**: 盘点任务完成时发送通知

## 配置步骤

### 第一步: 添加企业微信机器人

1. 在需要接收通知的企业微信群中,点击右上角 `...` 菜单
2. 选择 `添加群机器人` → `新建`
3. 设置机器人名称(如:资产管理系统通知)
4. 点击 `添加`

### 第二步: 获取Webhook地址

机器人创建成功后,会显示Webhook地址,格式如下:

```
https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

**复制这个地址**,稍后配置系统时需要使用。

### 第三步: 配置系统

#### 方式一: 通过Web界面配置

1. 登录系统后,进入 `系统设置` → `微信通知`
2. 将复制的Webhook地址粘贴到输入框中
3. 点击 `保存配置`
4. 点击 `发送测试消息`,检查是否能成功接收通知
5. 根据需要开启或关闭各类通知

#### 方式二: 通过环境变量配置

编辑 `.env` 文件:

```bash
# 启用微信通知
WECHAT_ENABLED=true

# Webhook地址
WECHAT_WEBHOOK_URL=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxxx

# 通知开关
WECHAT_NOTIFY_ASSET_EXPIRING=true      # 资产到期提醒
WECHAT_NOTIFY_ASSET_CHANGED=true       # 资产变动通知
WECHAT_NOTIFY_INVENTORY_CREATED=true   # 盘点任务创建通知
WECHAT_NOTIFY_INVENTORY_COMPLETED=true # 盘点完成通知
```

修改配置后需要重启服务:

```bash
docker-compose restart backend
```

### 第四步: 测试通知

配置完成后,点击 `发送测试消息` 按钮,系统会发送一条测试消息到企业微信群:

```
【测试消息】微信通知配置成功!
```

如果能在群里看到这条消息,说明配置成功。

## 通知格式

### 资产到期提醒

```
⚠️ 资产即将到期提醒

> 资产名称: MacBook Pro 14寸
> 资产编号: ASSET001
> 当前持有人: 张三
> 所属部门: 技术部
> 到期时间: 2025-03-15
> 剩余天数: 7天

请及时处理资产归还或续期操作。
```

### 资产变动通知

```
📱 资产变动通知

> 资产名称: MacBook Pro 14寸
> 资产编号: ASSET001
> 操作类型: 领用
> 新持有人: 李四
> 所属部门: 市场部
> 操作时间: 2025-03-11 14:30:00
```

### 盘点任务创建通知

```
📋 盘点任务创建通知

> 任务编号: 1
> 任务名称: 2025年第一季度盘点
> 创建人: 王五
> 创建时间: 2025-03-11 10:00:00
> 盘点范围: 技术部全部资产

请及时完成盘点任务。
```

### 盘点完成通知

```
✅ 盘点任务完成通知

> 任务编号: 1
> 任务名称: 2025年第一季度盘点
> 完成时间: 2025-03-11 16:00:00
> 总资产数: 100
> 已盘点: 98
> 正常资产: 95
> 异常资产: 3
> 异常率: 3.0%
> 操作人: 王五
```

## 注意事项

1. **企业微信限制**
   - 每个企业微信机器人每分钟最多发送20条消息
   - 建议不要配置过多的触发条件,避免消息轰炸

2. **Webhook地址安全**
   - Webhook地址包含敏感信息,请妥善保管
   - 不要将Webhook地址提交到公开的代码仓库
   - 定期更换Webhook地址以提高安全性

3. **群聊要求**
   - 机器人只能在群聊中使用
   - 建议创建专门的资产管理通知群
   - 群成员应该包含资产管理人员

4. **通知频率**
   - 资产到期提醒通常在到期前7天触发
   - 资产变动通知在操作后立即发送
   - 盘点通知在任务创建/完成时发送

## 故障排查

### 问题: 测试消息发送失败

**可能原因:**
1. Webhook地址错误或已失效
2. 企业微信群已删除机器人
3. 网络连接问题

**解决方案:**
1. 重新创建机器人,获取新的Webhook地址
2. 检查后端日志: `docker-compose logs backend`
3. 确认服务器能访问企业微信API

### 问题: 实际操作时没有收到通知

**可能原因:**
1. 通知开关被关闭
2. 对应的通知类型未启用
3. 后端服务未重启

**解决方案:**
1. 检查通知开关设置
2. 确认 `.env` 文件中的配置生效
3. 重启后端服务: `docker-compose restart backend`

### 问题: 消息发送频繁

**可能原因:**
1. 通知类型全部开启
2. 资产操作频繁

**解决方案:**
1. 根据实际需求关闭不必要的通知
2. 调整通知频率(需修改代码)

## API说明

### 获取微信配置

```
GET /api/wechat/config
```

### 更新微信配置

```
PUT /api/wechat/config
Content-Type: application/json

{
  "enabled": true,
  "webhook_url": "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx"
}
```

### 测试通知

```
POST /api/wechat/test
```

### 获取通知设置

```
GET /api/wechat/notifications
```

### 更新通知设置

```
PUT /api/wechat/notifications
Content-Type: application/json

{
  "asset_expiring": true,
  "asset_changed": true,
  "inventory_created": true,
  "inventory_completed": true
}
```

## 扩展开发

如需添加新的通知类型,可以参考以下步骤:

1. 在 `WechatNotificationService` 中添加新的发送方法
2. 在对应的控制器中调用通知服务
3. 在前端配置界面添加开关
4. 在 `.env.example` 中添加配置项

## 参考资料

- [企业微信机器人API文档](https://developer.work.weixin.qq.com/document/path/91770)
- [企业微信群机器人使用说明](https://developer.work.weixin.qq.com/document/path/91770)
