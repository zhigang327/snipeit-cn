import request from '@/utils/request'

export default {
  // 获取维修记录列表
  list(params) {
    return request({
      url: '/maintenance',
      method: 'get',
      params
    })
  },

  // 创建维修记录
  create(data) {
    return request({
      url: '/maintenance',
      method: 'post',
      data
    })
  },

  // 获取维修记录详情
  get(id) {
    return request({
      url: `/maintenance/${id}`,
      method: 'get'
    })
  },

  // 更新维修记录
  update(id, data) {
    return request({
      url: `/maintenance/${id}`,
      method: 'put',
      data
    })
  },

  // 删除维修记录
  delete(id) {
    return request({
      url: `/maintenance/${id}`,
      method: 'delete'
    })
  },

  // 分配维修人员
  assign(id, data) {
    return request({
      url: `/maintenance/${id}/assign`,
      method: 'post',
      data
    })
  },

  // 完成维修
  complete(id, data) {
    return request({
      url: `/maintenance/${id}/complete`,
      method: 'post',
      data
    })
  },

  // 取消维修
  cancel(id, data) {
    return request({
      url: `/maintenance/${id}/cancel`,
      method: 'post',
      data
    })
  },

  // 获取统计信息
  statistics(params) {
    return request({
      url: '/maintenance/statistics',
      method: 'get',
      params
    })
  },

  // 获取逾期记录
  overdue() {
    return request({
      url: '/maintenance/overdue',
      method: 'get'
    })
  },

  // 获取资产维修历史
  assetHistory(assetId) {
    return request({
      url: `/assets/${assetId}/maintenance/history`,
      method: 'get'
    })
  },

  // 导出维修记录
  export(data) {
    return request({
      url: '/maintenance/export',
      method: 'post',
      data
    })
  }
}