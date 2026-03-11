import request from './index'

export const getDepartments = (params) => {
  return request({
    url: '/departments',
    method: 'get',
    params
  })
}

export const getDepartmentTree = () => {
  return request({
    url: '/departments/tree',
    method: 'get'
  })
}

export const getDepartment = (id) => {
  return request({
    url: `/departments/${id}`,
    method: 'get'
  })
}

export const createDepartment = (data) => {
  return request({
    url: '/departments',
    method: 'post',
    data
  })
}

export const updateDepartment = (id, data) => {
  return request({
    url: `/departments/${id}`,
    method: 'put',
    data
  })
}

export const deleteDepartment = (id) => {
  return request({
    url: `/departments/${id}`,
    method: 'delete'
  })
}

export const getDepartmentStatistics = (id) => {
  return request({
    url: `/departments/${id}/statistics`,
    method: 'get'
  })
}

export const moveDepartment = (id, data) => {
  return request({
    url: `/departments/${id}/move`,
    method: 'post',
    data
  })
}
