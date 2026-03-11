import request from './index'

export const login = (data) => {
  return request({
    url: '/login',
    method: 'post',
    data
  })
}

export const logout = () => {
  return request({
    url: '/logout',
    method: 'post'
  })
}

export const getUserInfo = () => {
  return request({
    url: '/me',
    method: 'get'
  })
}

export const register = (data) => {
  return request({
    url: '/register',
    method: 'post',
    data
  })
}
