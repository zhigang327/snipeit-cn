import request from './index'

const userApi = {
  // 获取用户列表
  list(params) {
    return request({
      url: '/users',
      method: 'get',
      params
    })
  },

  // 获取单个用户
  get(id) {
    return request({
      url: `/users/${id}`,
      method: 'get'
    })
  },

  // 创建用户
  create(data) {
    return request({
      url: '/users',
      method: 'post',
      data
    })
  },

  // 更新用户
  update(id, data) {
    return request({
      url: `/users/${id}`,
      method: 'put',
      data
    })
  },

  // 删除用户
  delete(id) {
    return request({
      url: `/users/${id}`,
      method: 'delete'
    })
  }
}

export default userApi
