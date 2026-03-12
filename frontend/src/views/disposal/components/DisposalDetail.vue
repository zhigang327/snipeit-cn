<template>
  <div class="disposal-detail">
    <!-- 基本信息 -->
    <el-card class="section-card">
      <template #header>
        <div class="card-header">
          <span>基本信息</span>
        </div>
      </template>
      
      <el-descriptions :column="2" border>
        <el-descriptions-item label="报废编号">
          <el-tag type="primary">{{ record.disposal_number }}</el-tag>
        </el-descriptions-item>
        
        <el-descriptions-item label="报废类型">
          {{ record.disposal_type_label }}
        </el-descriptions-item>
        
        <el-descriptions-item label="报废日期">
          {{ record.disposal_date }}
        </el-descriptions-item>
        
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusTagType(record.status)">
            {{ record.status_label }}
          </el-tag>
        </el-descriptions-item>
        
        <el-descriptions-item label="申请人">
          {{ record.user?.name }}
        </el-descriptions-item>
        
        <el-descriptions-item label="申请时间">
          {{ record.created_at }}
        </el-descriptions-item>
        
        <el-descriptions-item label="审批人" v-if="record.approved_by">
          {{ record.approver?.name }}
        </el-descriptions-item>
        
        <el-descriptions-item label="审批时间" v-if="record.approved_at">
          {{ record.approved_at }}
        </el-descriptions-item>
        
        <el-descriptions-item label="审批单号" v-if="record.approval_number">
          {{ record.approval_number }}
        </el-descriptions-item>
        
        <el-descriptions-item label="相关单据号" v-if="record.document_number">
          {{ record.document_number }}
        </el-descriptions-item>
      </el-descriptions>
    </el-card>

    <!-- 资产信息 -->
    <el-card class="section-card">
      <template #header>
        <div class="card-header">
          <span>资产信息</span>
        </div>
      </template>
      
      <div v-if="record.asset" class="asset-info">
        <el-row :gutter="20">
          <el-col :span="8">
            <div class="info-item">
              <label>资产编号:</label>
              <span>{{ record.asset.asset_tag }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>资产名称:</label>
              <span>{{ record.asset.name }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>品牌型号:</label>
              <span>{{ record.asset.brand }} {{ record.asset.model }}</span>
            </div>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="8">
            <div class="info-item">
              <label>序列号:</label>
              <span>{{ record.asset.serial_number || '无' }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>当前状态:</label>
              <span>{{ getAssetStatusLabel(record.asset.status) }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>当前用户:</label>
              <span>{{ record.asset.user?.name || '未分配' }}</span>
            </div>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="8">
            <div class="info-item">
              <label>购买价格:</label>
              <span>{{ record.asset.purchase_price | currency }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>购买日期:</label>
              <span>{{ record.asset.purchase_date }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="info-item">
              <label>所属部门:</label>
              <span>{{ record.asset.department?.name || '无' }}</span>
            </div>
          </el-col>
        </el-row>
      </div>
    </el-card>

    <!-- 金额信息 -->
    <el-card class="section-card">
      <template #header>
        <div class="card-header">
          <span>金额信息</span>
        </div>
      </template>
      
      <el-row :gutter="20">
        <el-col :span="6">
          <div class="amount-item">
            <div class="amount-label">账面价值</div>
            <div class="amount-value">{{ record.book_value | currency }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="amount-item">
            <div class="amount-label">报废金额</div>
            <div class="amount-value">{{ record.disposal_amount | currency }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="amount-item">
            <div class="amount-label">残值</div>
            <div class="amount-value">{{ record.salvage_value | currency }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="amount-item">
            <div class="amount-label">处置损益</div>
            <div class="amount-value" :class="getAmountClass(record.gain_loss)">
              {{ record.gain_loss | currency }}
            </div>
            <div class="amount-type">{{ record.gain_loss_type_label }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 报废详情 -->
    <el-card class="section-card">
      <template #header>
        <div class="card-header">
          <span>报废详情</span>
        </div>
      </template>
      
      <el-descriptions :column="1" border>
        <el-descriptions-item label="报废原因">
          {{ record.reason }}
        </el-descriptions-item>
        
        <el-descriptions-item label="详细描述" v-if="record.description">
          {{ record.description }}
        </el-descriptions-item>
        
        <el-descriptions-item label="拒绝原因" v-if="record.rejection_reason">
          <span style="color: #f56c6c;">{{ record.rejection_reason }}</span>
        </el-descriptions-item>
        
        <el-descriptions-item label="接收方名称" v-if="record.recipient_name">
          {{ record.recipient_name }}
        </el-descriptions-item>
        
        <el-descriptions-item label="接收方联系方式" v-if="record.recipient_contact">
          {{ record.recipient_contact }}
        </el-descriptions-item>
        
        <el-descriptions-item label="最终去向" v-if="record.final_location">
          {{ record.final_location }}
        </el-descriptions-item>
        
        <el-descriptions-item label="交接说明" v-if="record.handover_notes">
          {{ record.handover_notes }}
        </el-descriptions-item>
        
        <el-descriptions-item label="环境影响说明" v-if="record.environmental_impact">
          {{ record.environmental_impact }}
        </el-descriptions-item>
      </el-descriptions>
    </el-card>

    <!-- 操作按钮 -->
    <div class="action-buttons">
      <el-button @click="$emit('close')">关闭</el-button>
      
      <template v-if="record.status === 'pending'">
        <el-button v-if="canApprove" type="success" @click="handleApprove">
          审批
        </el-button>
        <el-button v-if="canEdit" @click="handleEdit">编辑</el-button>
        <el-button v-if="canCancel" type="danger" @click="handleCancel">
          取消
        </el-button>
      </template>
      
      <template v-if="record.status === 'approved'">
        <el-button type="primary" @click="handleComplete">完成报废</el-button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ElMessageBox } from 'element-plus'
import { disposalApi } from '@/api/export'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'approve', 'edit', 'cancel', 'complete'])

// 计算属性
const canApprove = computed(() => {
  return props.record.status === 'pending'
})

const canEdit = computed(() => {
  return props.record.status === 'pending' && props.record.user?.id === currentUser.value?.id
})

const canCancel = computed(() => {
  return props.record.status === 'pending' && props.record.user?.id === currentUser.value?.id
})

// 方法
const getStatusTagType = (status) => {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    completed: 'info'
  }
  return types[status] || 'info'
}

const getAssetStatusLabel = (status) => {
  const labels = {
    available: '可用',
    assigned: '已分配',
    maintenance: '维修中',
    disposed: '已报废'
  }
  return labels[status] || status
}

const getAmountClass = (amount) => {
  if (amount > 0) return 'text-success'
  if (amount < 0) return 'text-danger'
  return ''
}

const handleApprove = () => {
  emit('approve', props.record)
}

const handleEdit = () => {
  emit('edit', props.record)
}

const handleCancel = async () => {
  try {
    await ElMessageBox.confirm('确定要取消此报废申请吗？', '确认取消', {
      type: 'warning'
    })
    
    const response = await disposalApi.cancel(props.record.id)
    
    if (response.success) {
      ElMessage.success('取消成功')
      emit('close')
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('取消失败')
    }
  }
}

const handleComplete = async () => {
  try {
    await ElMessageBox.confirm('确定要完成此报废流程吗？完成后资产状态将变为已报废。', '确认完成', {
      type: 'warning'
    })
    
    const response = await disposalApi.complete(props.record.id)
    
    if (response.success) {
      ElMessage.success('完成成功')
      emit('close')
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('完成失败')
    }
  }
}

// 过滤器
const currency = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}

// 模拟当前用户（实际项目中应该从store获取）
const currentUser = computed(() => {
  return { id: 1, name: '管理员' } // 临时模拟用户
})
</script>

<style scoped>
.disposal-detail {
  padding: 0;
}

.section-card {
  margin-bottom: 20px;
}

.card-header {
  font-weight: bold;
  font-size: 16px;
}

.asset-info {
  padding: 10px 0;
}

.info-item {
  margin-bottom: 15px;
}

.info-item label {
  display: inline-block;
  width: 80px;
  color: #666;
  font-weight: bold;
}

.info-item span {
  color: #333;
}

.amount-item {
  text-align: center;
  padding: 15px;
  border-radius: 4px;
  background: #f8f9fa;
}

.amount-label {
  font-size: 14px;
  color: #666;
  margin-bottom: 5px;
}

.amount-value {
  font-size: 20px;
  font-weight: bold;
  color: #409eff;
}

.amount-type {
  font-size: 12px;
  color: #666;
  margin-top: 5px;
}

.action-buttons {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.text-success {
  color: #67c23a;
}

.text-danger {
  color: #f56c6c;
}
</style>