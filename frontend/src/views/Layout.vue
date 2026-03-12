<template>
  <el-container class="layout-container">
    <el-aside width="240px" class="aside">
      <div class="logo">Snipe-CN</div>
      <el-menu
        :default-active="currentRoute"
        router
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409eff"
      >
        <el-menu-item index="/dashboard">
          <el-icon><DataBoard /></el-icon>
          <span>仪表盘</span>
        </el-menu-item>
        <el-menu-item index="/assets">
          <el-icon><Document /></el-icon>
          <span>资产管理</span>
        </el-menu-item>
        <el-menu-item index="/departments">
          <el-icon><OfficeBuilding /></el-icon>
          <span>部门管理</span>
        </el-menu-item>
        <el-menu-item index="/inventory">
          <el-icon><Scan /></el-icon>
          <span>资产盘点</span>
        </el-menu-item>
        <el-menu-item index="/depreciation">
          <el-icon><TrendCharts /></el-icon>
          <span>资产折旧</span>
        </el-menu-item>
        <el-menu-item index="/maintenance">
          <el-icon><Tools /></el-icon>
          <span>维修记录</span>
        </el-menu-item>
        <el-menu-item index="/users">
          <el-icon><User /></el-icon>
          <span>用户管理</span>
        </el-menu-item>
        <el-sub-menu index="settings">
          <template #title>
            <el-icon><Setting /></el-icon>
            <span>系统设置</span>
          </template>
          <el-menu-item index="/settings/wechat">
            <el-icon><ChatDotRound /></el-icon>
            <span>微信通知</span>
          </el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="breadcrumb">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ currentTitle }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="user-info">
          <el-dropdown @command="handleCommand">
            <span class="user-dropdown">
              <el-avatar :size="32" :src="userAvatar || 'https://cube.elemecdn.com/0/88/03b0d39583f48206768a7534e55bcpng.png'" />
              <span class="username">{{ userName }}</span>
              <el-icon class="el-icon--right"><CaretBottom /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="logout">退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { ElMessageBox, ElMessage } from 'element-plus'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const currentRoute = computed(() => route.path)
const currentTitle = computed(() => route.meta.title || '')
const userName = computed(() => authStore.userName)
const userAvatar = computed(() => authStore.userAvatar)

const handleCommand = async (command) => {
  if (command === 'logout') {
    try {
      await ElMessageBox.confirm('确定要退出登录吗?', '提示', {
        type: 'warning'
      })
      await authStore.logout()
      ElMessage.success('退出成功')
      router.push('/login')
    } catch (error) {
      // 取消操作
    }
  }
}
</script>

<style scoped>
.layout-container {
  height: 100vh;
}

.aside {
  background-color: #304156;
  height: 100vh;
  overflow-x: hidden;
}

.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #fff;
  background-color: #2b3a4a;
}

.header {
  background-color: #fff;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.user-info {
  display: flex;
  align-items: center;
}

.user-dropdown {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.username {
  margin-left: 10px;
  margin-right: 5px;
}

.main {
  background-color: #f0f2f5;
  padding: 20px;
}
</style>
