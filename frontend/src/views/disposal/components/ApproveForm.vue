<template>
  <div class="approve-form">
    <!-- 申请信息概览 -->
    <el-card class="summary-card">
      <template #header>
        <div class="card-header">
          <span>申请信息概览</span>
        </div>
      </template>
      
      <el-descriptions :column="1" size="small">
        <el-descriptions-item label="报废编号">
          {{ record.disposal_number }}
        </el-descriptions-item>
        
        <el-descriptions-item label="资产信息">
          {{ record.asset?.asset_tag }} - {{ record.asset?.name }}
        </el-descriptions-item>
        
        <el-descriptions-item label="报废类型">
          {{ record.disposal_type_label }}
        </el-descriptions-item>
        
        <el-descriptions-item label="报废原因">
          {{ record.reason }}
        </el-descriptions-item>
        
        <el-descriptions-item label="金额信息">
          账面价值: {{ record.book_value | currency }} | 
          报废金额: {{ record.disposal_amount | currency }}
        </el-descriptions-item>
      </el-descriptions>
    </el-card>

    <!-- 审批操作 -->
    <el-form :model="form" :rules="rules" ref="approveFormRef" class="approve-form-content">
      <el-form-item label="审批单号" prop="approval_number">
        <el-input
          v-model="form.approval_number"
          placeholder="请输入审批单号（可选）"
          maxlength="50"
        />
      </el-form-item>

      <!-- 审批按钮 -->
      <div class="action-buttons">
        <el-button type="success" @click="handleApprove" :loading="loading.approve">
          <el-icon><Check /></el-icon>
          批准申请
        </el-button>
        
        <el-button type="danger" @click="showRejectForm = true" :disabled="showRejectForm">
          <el-icon><Close /></el-icon>
          拒绝申请
        </el-button>
        
        <el-button @click="$emit('cancel')">取消</el-button>
      </div>

      <!-- 拒绝表单 -->
      <el-form-item v-if="showRejectForm" label="拒绝原因" prop="rejection_reason" required>
        <el-input
          v-model="form.rejection_reason"
          type="textarea"
          :rows="3"
          placeholder="请详细说明拒绝原因"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>

      <!-- 拒绝操作按钮 -->
      <div v-if="showRejectForm" class="reject-buttons">
        <el-button type="danger" @click="handleReject" :loading="loading.reject">
          <el-icon><Close /></el-icon>
          确认拒绝
        </el-button>
        
        <el-button @click="cancelReject">取消拒绝</el-button>
      </div>
    </el-form>

    <!-- 审批说明 -->
    <el-alert
      title="审批说明"
      type="info"
      :closable="false"
      class="approve-note"
    >
      <ul>
        <li>批准后，资产将进入"已批准"状态，需要完成报废流程</li>
        <li>拒绝后，申请人将收到拒绝原因通知</li>
        <li>审批前请仔细核对资产信息和报废原因</li>
        <li>对于金额较大的报废，建议填写审批单号以便追溯</li>
      </ul>
    </el-alert>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Close } from '@element-plus/icons-vue'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['approve', 'reject', 'cancel'])

const approveFormRef = ref()
const showRejectForm = ref(false)

const loading = reactive({
  approve: false,
  reject: false
})

const form = reactive({
  approval_number: '',
  rejection_reason: ''
})

const rules = {
  rejection_reason: [
    { required: true, message: '请填写拒绝原因', trigger: 'blur' },
    { min: 10, message: '拒绝原因至少10个字符', trigger: 'blur' }
  ]
}

// 方法
const handleApprove = async () => {
  try {
    loading.approve = true
    
    const approvalData = {}
    if (form.approval_number) {
      approvalData.approval_number = form.approval_number
    }
    
    emit('approve', approvalData)
  } catch (error) {
    ElMessage.error('审批操作失败')
    console.error(error)
  } finally {
    loading.approve = false
  }
}

const handleReject = async () => {
  try {
    await approveFormRef.value.validate()
    
    if (!form.rejection_reason.trim()) {
      ElMessage.error('请填写拒绝原因')
      return
    }
    
    loading.reject = true
    
    const rejectData = {
      reason: form.rejection_reason.trim()
    }
    
    emit('reject', rejectData)
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('拒绝操作失败')
      console.error(error)
    }
  } finally {
    loading.reject = false
  }
}

const cancelReject = () => {
  showRejectForm.value = false
  form.rejection_reason = ''
  approveFormRef.value?.clearValidate()
}

// 过滤器
const currency = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}
</script>

<style scoped>
.approve-form {
  padding: 0;
}

.summary-card {
  margin-bottom: 20px;
}

.card-header {
  font-weight: bold;
  font-size: 16px;
}

.approve-form-content {
  padding: 0 20px;
}

.action-buttons {
  text-align: center;
  margin: 30px 0 20px;
}

.reject-buttons {
  text-align: center;
  margin: 20px 0;
}

.approve-note {
  margin-top: 30px;
}

.approve-note ul {
  margin: 5px 0 0 0;
  padding-left: 20px;
}

.approve-note li {
  margin-bottom: 5px;
  line-height: 1.4;
}

:deep(.el-form-item__label) {
  font-weight: bold;
}

:deep(.el-descriptions__label) {
  font-weight: bold;
  width: 80px;
}
</style>