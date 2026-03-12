import request from './index'

export default {
  // 计算折旧
  calculate(assetId) {
    return request.get(`/assets/${assetId}/depreciation/calculate`)
  },

  // 执行折旧
  execute(assetId, data) {
    return request.post(`/assets/${assetId}/depreciation/execute`, data)
  },

  // 批量执行折旧
  executeBatch(data) {
    return request.post('/depreciation/batch', data)
  },

  // 获取折旧记录
  getRecords(assetId, params) {
    return request.get(`/assets/${assetId}/depreciation/records`, { params })
  },

  // 获取折旧预测表
  getSchedule(assetId, params) {
    return request.get(`/assets/${assetId}/depreciation/schedule`, { params })
  },

  // 获取折旧报表
  getReport(params) {
    return request.get('/depreciation/report', { params })
  },

  // 获取折旧统计
  getStatistics() {
    return request.get('/depreciation/statistics')
  }
}
