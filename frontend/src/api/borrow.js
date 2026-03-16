import request from '@/utils/request'

export const borrowApi = {
  // 获取借用记录列表
  getRecords(params) {
    return request({
      url: '/borrow',
      method: 'get',
      params,
    })
  },

  // 获取单个借用记录
  getRecord(id) {
    return request({
      url: `/borrow/${id}`,
      method: 'get',
    })
  },

  // 创建借用申请
  createBorrow(data) {
    return request({
      url: '/borrow',
      method: 'post',
      data,
    })
  },

  // 审批借用申请
  approveBorrow(id) {
    return request({
      url: `/borrow/${id}/approve`,
      method: 'post',
    })
  },

  // 拒绝借用申请
  rejectBorrow(id, data) {
    return request({
      url: `/borrow/${id}/reject`,
      method: 'post',
      data,
    })
  },

  // 确认借出资产
  confirmBorrow(id) {
    return request({
      url: `/borrow/${id}/confirm-borrow`,
      method: 'post',
    })
  },

  // 归还资产
  returnAsset(id, data) {
    return request({
      url: `/borrow/${id}/return`,
      method: 'post',
      data,
    })
  },

  // 取消借用申请
  cancelBorrow(id, data = {}) {
    return request({
      url: `/borrow/${id}/cancel`,
      method: 'post',
      data,
    })
  },

  // 获取统计信息
  getStatistics() {
    return request({
      url: '/borrow/statistics',
      method: 'get',
    })
  },

  // 获取逾期记录
  getOverdue() {
    return request({
      url: '/borrow/overdue',
      method: 'get',
    })
  },

  // 检查并更新逾期记录
  checkOverdue() {
    return request({
      url: '/borrow/check-overdue',
      method: 'get',
    })
  },

  // 获取即将到期记录
  getUpcomingDue(params) {
    return request({
      url: '/borrow/upcoming-due',
      method: 'get',
      params,
    })
  },

  // 获取资产借用历史
  getAssetHistory(assetId) {
    return request({
      url: `/assets/${assetId}/borrow/history`,
      method: 'get',
    })
  },

  // 获取用户借用历史
  getUserHistory(userId) {
    return request({
      url: userId ? `/users/${userId}/borrow/history` : '/users/borrow/history',
      method: 'get',
    })
  },

  // 导出借用记录
  exportRecords(params) {
    return request({
      url: '/borrow/export',
      method: 'post',
      params,
    })
  },
}
