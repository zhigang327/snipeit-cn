<template>
  <div v-if="record">
    <el-descriptions :column="2" border>
      <el-descriptions-item label="盘点编号">{{ record.inventory_number }}</el-descriptions-item>
      <el-descriptions-item label="盘点日期">{{ record.inventory_date }}</el-descriptions-item>
      <el-descriptions-item label="资产编号">{{ record.asset?.asset_tag }}</el-descriptions-item>
      <el-descriptions-item label="资产名称">{{ record.asset?.name }}</el-descriptions-item>
      <el-descriptions-item label="盘点类型">{{ record.inventory_type_label }}</el-descriptions-item>
      <el-descriptions-item label="实物状态">
        <el-tag :type="getPhysicalStatusType(record.physical_status)">
          {{ record.physical_status_label }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="状态匹配">
        <el-tag :type="getMatchStatusType(record.status_match)">
          {{ record.status_match_label }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="审核状态">
        <el-tag :type="getReviewStatusType(record.review_status)">
          {{ record.review_status_label }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="盘点员">{{ record.user?.name }}</el-descriptions-item>
      <el-descriptions-item label="状况描述">{{ record.condition_description || '-' }}</el-descriptions-item>
      <el-descriptions-item label="是否有问题">
        <el-tag :type="record.has_issues ? 'danger' : 'success'">
          {{ record.has_issues ? '是' : '否' }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item v-if="record.has_issues" label="问题描述" :span="2">
        {{ record.issue_description }}
      </el-descriptions-item>
      <el-descriptions-item label="备注" :span="2">{{ record.notes || '-' }}</el-descriptions-item>
    </el-descriptions>

    <div style="text-align:right; margin-top:20px">
      <el-button @click="$emit('close')">关闭</el-button>
    </div>
  </div>
</template>

<script setup>
defineProps({ record: { type: Object, default: null } })
defineEmits(['close'])

const getPhysicalStatusType = (s) => ({ found: 'success', not_found: 'danger',
  damaged: 'warning', scrapped: 'info', transferred: 'primary' })[s] || 'info'
const getMatchStatusType = (s) => ({ matched: 'success', location_mismatch: 'warning',
  user_mismatch: 'warning', both_mismatch: 'danger' })[s] || 'info'
const getReviewStatusType = (s) => ({ pending: 'warning', approved: 'success', rejected: 'danger' })[s] || 'info'
</script>
