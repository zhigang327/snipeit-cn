<template>
  <div v-if="task">
    <el-descriptions :column="2" border>
      <el-descriptions-item label="任务编号">{{ task.task_number }}</el-descriptions-item>
      <el-descriptions-item label="任务名称">{{ task.task_name }}</el-descriptions-item>
      <el-descriptions-item label="任务类型">{{ task.task_type_label }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag :type="getStatusTagType(task.status)">{{ task.status_label }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="开始日期">{{ task.start_date }}</el-descriptions-item>
      <el-descriptions-item label="结束日期">{{ task.end_date }}</el-descriptions-item>
      <el-descriptions-item label="负责人">{{ task.assignee?.name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="资产进度">
        {{ task.completed_assets }}/{{ task.total_assets }}
      </el-descriptions-item>
      <el-descriptions-item label="完成率" :span="2">
        <el-progress :percentage="task.completion_rate || 0" />
      </el-descriptions-item>
      <el-descriptions-item label="任务描述" :span="2">
        {{ task.description || '-' }}
      </el-descriptions-item>
    </el-descriptions>

    <div style="text-align:right; margin-top:20px">
      <el-button @click="$emit('close')">关闭</el-button>
    </div>
  </div>
</template>

<script setup>
defineProps({ task: { type: Object, default: null } })
defineEmits(['close'])

const getStatusTagType = (status) => {
  const types = { draft: 'info', active: 'primary', in_progress: 'success',
    paused: 'warning', completed: 'success', cancelled: 'danger' }
  return types[status] || 'info'
}
</script>
