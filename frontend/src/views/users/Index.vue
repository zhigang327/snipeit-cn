<template>
  <div class="users-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>用户管理</span>
          <el-button type="primary" @click="handleAdd">添加用户</el-button>
        </div>
      </template>

      <el-alert title="用户管理功能开发中" type="info" :closable="false" style="margin-bottom: 20px;" />
    </el-card>
  </div>
</template>

<script setup>
const handleAdd = () => {
  console.log('Add user')
}
</script>

<style scoped>
.users-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
