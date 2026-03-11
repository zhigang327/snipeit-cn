import request from './index'

export default {
  // 获取微信配置
  getConfig() {
    return request.get('/wechat/config')
  },

  // 更新微信配置
  updateConfig(data) {
    return request.put('/wechat/config', data)
  },

  // 测试微信通知
  testNotification() {
    return request.post('/wechat/test')
  },

  // 获取通知设置
  getNotificationSettings() {
    return request.get('/wechat/notifications')
  },

  // 更新通知设置
  updateNotificationSettings(data) {
    return request.put('/wechat/notifications', data)
  }
}
