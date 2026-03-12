import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/store/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard'
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '仪表盘' }
      },
      {
        path: 'assets',
        name: 'Assets',
        component: () => import('@/views/assets/Index.vue'),
        meta: { title: '资产管理' }
      },
      {
        path: 'departments',
        name: 'Departments',
        component: () => import('@/views/departments/Index.vue'),
        meta: { title: '部门管理' }
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('@/views/users/Index.vue'),
        meta: { title: '用户管理' }
      },
      {
        path: 'inventory',
        name: 'Inventory',
        component: () => import('@/views/inventory/Index.vue'),
        meta: { title: '资产盘点' }
      },
      {
        path: 'depreciation',
        name: 'Depreciation',
        component: () => import('@/views/depreciation/Index.vue'),
        meta: { title: '资产折旧' }
      },
      {
        path: 'settings/wechat',
        name: 'WechatSettings',
        component: () => import('@/views/settings/Wechat.vue'),
        meta: { title: '微信通知设置' }
      },
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/')
  } else {
    next()
  }
})

export default router
