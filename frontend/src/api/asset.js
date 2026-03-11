import request from './index'

export const getAssets = (params) => {
  return request({
    url: '/assets',
    method: 'get',
    params
  })
}

export const getAsset = (id) => {
  return request({
    url: `/assets/${id}`,
    method: 'get'
  })
}

export const createAsset = (data) => {
  return request({
    url: '/assets',
    method: 'post',
    data
  })
}

export const updateAsset = (id, data) => {
  return request({
    url: `/assets/${id}`,
    method: 'put',
    data
  })
}

export const deleteAsset = (id) => {
  return request({
    url: `/assets/${id}`,
    method: 'delete'
  })
}

export const checkoutAsset = (id, data) => {
  return request({
    url: `/assets/${id}/checkout`,
    method: 'post',
    data
  })
}

export const checkinAsset = (id, data) => {
  return request({
    url: `/assets/${id}/checkin`,
    method: 'post',
    data
  })
}

export const getAssetStatistics = () => {
  return request({
    url: '/assets/statistics',
    method: 'get'
  })
}
