import request from './index'

// 报废管理API
export const disposalApi = {
  // 获取报废记录列表
  getList(params) {
    return request({
      url: '/api/disposal',
      method: 'get',
      params
    })
  },

  // 创建报废申请
  create(data) {
    return request({
      url: '/api/disposal',
      method: 'post',
      data
    })
  },

  // 获取单个报废记录详情
  getDetail(id) {
    return request({
      url: `/api/disposal/${id}`,
      method: 'get'
    })
  },

  // 更新报废申请
  update(id, data) {
    return request({
      url: `/api/disposal/${id}`,
      method: 'put',
      data
    })
  },

  // 审批报废申请
  approve(id, data) {
    return request({
      url: `/api/disposal/${id}/approve`,
      method: 'post',
      data
    })
  },

  // 拒绝报废申请
  reject(id, data) {
    return request({
      url: `/api/disposal/${id}/reject`,
      method: 'post',
      data
    })
  },

  // 完成报废流程
  complete(id) {
    return request({
      url: `/api/disposal/${id}/complete`,
      method: 'post'
    })
  },

  // 取消报废申请
  cancel(id) {
    return request({
      url: `/api/disposal/${id}/cancel`,
      method: 'post'
    })
  },

  // 获取报废统计
  getStatistics(params) {
    return request({
      url: '/api/disposal/statistics',
      method: 'get',
      params
    })
  },

  // 获取逾期未处理的报废申请
  getOverdue(params) {
    return request({
      url: '/api/disposal/overdue',
      method: 'get',
      params
    })
  },

  // 获取资产报废历史
  getAssetHistory(assetId) {
    return request({
      url: `/api/assets/${assetId}/disposal/history`,
      method: 'get'
    })
  },

  // 导出报废记录
  export(params) {
    return request({
      url: '/api/disposal/export',
      method: 'post',
      data: params
    })
  },

  // 删除报废记录
  delete(id) {
    return request({
      url: `/api/disposal/${id}`,
      method: 'delete'
    })
  }
}

export default disposalApi