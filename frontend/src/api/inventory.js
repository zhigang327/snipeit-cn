import request from './index'

export const getInventories = (params) => {
  return request({
    url: '/inventories',
    method: 'get',
    params
  })
}

export const getInventory = (id) => {
  return request({
    url: `/inventories/${id}`,
    method: 'get'
  })
}

export const startInventory = (data) => {
  return request({
    url: '/inventories',
    method: 'post',
    data
  })
}

export const scanAsset = (id, data) => {
  return request({
    url: `/inventories/${id}/scan`,
    method: 'post',
    data
  })
}

export const getInventoryProgress = (id) => {
  return request({
    url: `/inventories/${id}/progress`,
    method: 'get'
  })
}

export const completeInventory = (id, data) => {
  return request({
    url: `/inventories/${id}/complete`,
    method: 'post',
    data
  })
}
