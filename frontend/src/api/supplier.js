import request from './index'

export const getSuppliers = (params) => {
  return request({
    url: '/suppliers',
    method: 'get',
    params
  })
}

export const createSupplier = (data) => {
  return request({
    url: '/suppliers',
    method: 'post',
    data
  })
}

export const updateSupplier = (id, data) => {
  return request({
    url: `/suppliers/${id}`,
    method: 'put',
    data
  })
}

export const deleteSupplier = (id) => {
  return request({
    url: `/suppliers/${id}`,
    method: 'delete'
  })
}
