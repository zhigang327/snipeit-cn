import request from './index'

// 盘点管理API
export const inventoryApi = {
  // 获取盘点任务列表
  getTasks(params) {
    return request({
      url: '/api/inventory/tasks',
      method: 'get',
      params
    })
  },

  // 创建盘点任务
  createTask(data) {
    return request({
      url: '/api/inventory/tasks',
      method: 'post',
      data
    })
  },

  // 获取单个盘点任务详情
  getTaskDetail(id) {
    return request({
      url: `/api/inventory/tasks/${id}`,
      method: 'get'
    })
  },

  // 更新盘点任务
  updateTask(id, data) {
    return request({
      url: `/api/inventory/tasks/${id}`,
      method: 'put',
      data
    })
  },

  // 开始盘点任务
  startTask(id) {
    return request({
      url: `/api/inventory/tasks/${id}/start`,
      method: 'post'
    })
  },

  // 完成盘点任务
  completeTask(id, data = {}) {
    return request({
      url: `/api/inventory/tasks/${id}/complete`,
      method: 'post',
      data
    })
  },

  // 取消盘点任务
  cancelTask(id) {
    return request({
      url: `/api/inventory/tasks/${id}/cancel`,
      method: 'post'
    })
  },

  // 获取盘点记录列表
  getRecords(params) {
    return request({
      url: '/api/inventory/records',
      method: 'get',
      params
    })
  },

  // 创建盘点记录
  createRecord(data) {
    return request({
      url: '/api/inventory/records',
      method: 'post',
      data
    })
  },

  // 获取单个盘点记录详情
  getRecordDetail(id) {
    return request({
      url: `/api/inventory/records/${id}`,
      method: 'get'
    })
  },

  // 审核盘点记录
  reviewRecord(id, data) {
    return request({
      url: `/api/inventory/records/${id}/review`,
      method: 'post',
      data
    })
  },

  // 获取盘点统计
  getStatistics(params) {
    return request({
      url: '/api/inventory/statistics',
      method: 'get',
      params
    })
  },

  // 获取待审核的盘点记录
  getPendingReviews(params) {
    return request({
      url: '/api/inventory/pending-reviews',
      method: 'get',
      params
    })
  },

  // 获取有异常的盘点记录
  getIssueRecords(params) {
    return request({
      url: '/api/inventory/issue-records',
      method: 'get',
      params
    })
  },

  // 获取今日待办盘点任务
  getTodaysTasks() {
    return request({
      url: '/api/inventory/todays-tasks',
      method: 'get'
    })
  },

  // 获取逾期未完成的任务
  getOverdueTasks() {
    return request({
      url: '/api/inventory/overdue-tasks',
      method: 'get'
    })
  },

  // 获取资产盘点历史
  getAssetHistory(assetId, params) {
    return request({
      url: `/api/assets/${assetId}/inventory/history`,
      method: 'get',
      params
    })
  },

  // 通过二维码扫描创建盘点记录
  scanQrCode(data) {
    return request({
      url: '/api/inventory/scan-qr',
      method: 'post',
      data
    })
  },

  // 导出盘点数据
  export(params) {
    return request({
      url: '/api/inventory/export',
      method: 'post',
      data: params
    })
  }
}

export default inventoryApi