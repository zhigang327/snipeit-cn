import { defineStore } from 'pinia'
import { login, logout, getUserInfo } from '@/api/auth'
import { getToken, setToken, removeToken } from '@/utils/token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: getToken(),
    user: null,
    permissions: []
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    userName: (state) => state.user?.name || '',
    userAvatar: (state) => state.user?.avatar || ''
  },

  actions: {
    async login(credentials) {
      try {
        const response = await login(credentials)
        this.token = response.data.token
        this.user = response.data.user
        setToken(response.data.token)
        return response
      } catch (error) {
        throw error
      }
    },

    async logout() {
      try {
        await logout()
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.token = null
        this.user = null
        removeToken()
      }
    },

    async getUserInfo() {
      try {
        const response = await getUserInfo()
        this.user = response.data
        return response
      } catch (error) {
        throw error
      }
    },

    hasPermission(permission) {
      return this.permissions.includes(permission)
    }
  }
})
