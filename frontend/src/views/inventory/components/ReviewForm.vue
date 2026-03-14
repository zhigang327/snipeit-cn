<template>
  <div>
    <div v-if="record" style="margin-bottom:20px">
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item label="资产编号">{{ record.asset?.asset_tag }}</el-descriptions-item>
        <el-descriptions-item label="资产名称">{{ record.asset?.name }}</el-descriptions-item>
        <el-descriptions-item label="实物状态">{{ record.physical_status_label }}</el-descriptions-item>
        <el-descriptions-item label="状态匹配">{{ record.status_match_label }}</el-descriptions-item>
        <el-descriptions-item v-if="record.has_issues" label="问题描述" :span="2">
          {{ record.issue_description }}
        </el-descriptions-item>
      </el-descriptions>
    </div>

    <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
      <el-form-item label="审核意见" prop="review_comment">
        <el-input v-model="form.review_comment" type="textarea" :rows="3"
          placeholder="请输入审核意见" />
      </el-form-item>

      <el-form-item>
        <el-button type="success" @click="handleApprove">
          <el-icon><Check /></el-icon> 通过
        </el-button>
        <el-button type="danger" @click="handleReject">
          <el-icon><Close /></el-icon> 拒绝
        </el-button>
        <el-button @click="$emit('cancel')">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Check, Close } from '@element-plus/icons-vue'

defineProps({ record: { type: Object, default: null } })
const emit = defineEmits(['approve', 'cancel'])

const formRef = ref(null)
const form = reactive({ review_comment: '', status: '' })
const rules = {
  review_comment: [{ required: true, message: '请输入审核意见', trigger: 'blur' }]
}

const handleApprove = async () => {
  await formRef.value.validate()
  emit('approve', { ...form, status: 'approved' })
}

const handleReject = async () => {
  await formRef.value.validate()
  emit('approve', { ...form, status: 'rejected' })
}
</script>
