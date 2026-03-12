<template>
  <div class="maintenance-index">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>维修记录管理</span>
          <el-button type="primary" @click="handleCreate">新增维修记录</el-button>
        </div>
      </template>

      <!-- 搜索和筛选 -->
      <el-form :model="searchForm" inline>
        <el-form-item label="资产搜索">
          <el-input v-model="searchForm.search" placeholder="资产名称/编号" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
            <el-option label="待处理" value="pending" />
            <el-option label="处理中" value="in_progress" />
            <el-option label="已完成" value="completed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-select v-model="searchForm.priority" placeholder="全部优先级" clearable>
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="紧急" value="urgent" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="全部类型" clearable>
            <el-option label="硬件" value="hardware" />
            <el-option label="软件" value="software" />
            <el-option label="网络" value="network" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="报修日期">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadRecords">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 统计卡片 -->
      <el-row :gutter="16" style="margin-bottom: 20px">
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card">
              <div class="stat-title">总维修记录</div>
              <div class="stat-value">{{ statistics.total || 0 }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card">
              <div class="stat-title">待处理</div>
              <div class="stat-value" style="color: #e6a23c">{{ statistics.by_status?.pending || 0 }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card">
              <div class="stat-title">处理中</div>
              <div class="stat-value" style="color: #409eff">{{ statistics.by_status?.in_progress || 0 }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover">
            <div class="stat-card">
              <div class="stat-title">完成率</div>
              <div class="stat-value" style="color: #67c23a">{{ statistics.completion_rate || 0 }}%</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 维修记录表格 -->
      <el-table :data="records" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="资产信息" min-width="200">
          <template #default="{ row }">
            <div>
              <div><strong>{{ row.asset?.name }}</strong></div>
              <div style="font-size: 12px; color: #909399;">{{ row.asset?.asset_tag }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="维修标题" width="180" />
        <el-table-column label="报修人">
          <template #default="{ row }">{{ row.reported_by?.name }}</template>
        </el-table-column>
        <el-table-column label="维修人员">
          <template #default="{ row }">{{ row.assigned_to?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status_color">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="优先级" width="80">
          <template #default="{ row }">
            <el-tag :type="row.priority_color" size="small">{{ row.priority_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="报修日期" width="120">
          <template #default="{ row }">{{ formatDate(row.reported_date) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleView(row)">查看</el-button>
            <el-button size="small" type="primary" @click="handleEdit(row)" v-if="canEdit(row)">编辑</el-button>
            <el-button size="small" type="warning" @click="handleAssign(row)" v-if="canAssign(row)">分配</el-button>
            <el-button size="small" type="success" @click="handleComplete(row)" v-if="canComplete(row)">完成</el-button>
            <el-button size="small" type="danger" @click="handleCancel(row)" v-if="canCancel(row)">取消</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.current"
        v-model:page-size="pagination.pageSize"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="800px"
      :before-close="handleDialogClose"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="资产" prop="asset_id">
              <el-select
                v-model="form.asset_id"
                placeholder="选择资产"
                filterable
                style="width: 100%"
                @change="handleAssetChange"
              >
                <el-option
                  v-for="asset in availableAssets"
                  :key="asset.id"
                  :label="`${asset.asset_tag} - ${asset.name}`"
                  :value="asset.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="维修标题" prop="title">
              <el-input v-model="form.title" placeholder="请输入维修标题" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="优先级" prop="priority">
              <el-select v-model="form.priority" placeholder="选择优先级" style="width: 100%">
                <el-option label="低" value="low" />
                <el-option label="中" value="medium" />
                <el-option label="高" value="high" />
                <el-option label="紧急" value="urgent" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="维修类型" prop="type">
              <el-select v-model="form.type" placeholder="选择类型" style="width: 100%">
                <el-option label="硬件" value="hardware" />
                <el-option label="软件" value="software" />
                <el-option label="网络" value="network" />
                <el-option label="其他" value="other" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="报修日期" prop="reported_date">
          <el-date-picker
            v-model="form.reported_date"
            type="date"
            placeholder="选择报修日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="故障描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="详细描述故障现象"
          />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="预估工时(小时)">
              <el-input-number v-model="form.estimated_hours" :min="1" :precision="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预估费用">
              <el-input-number v-model="form.estimated_cost" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="使用配件">
          <el-input
            v-model="form.parts_used"
            type="textarea"
            :rows="2"
            placeholder="使用的配件清单"
          />
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="form.notes"
            type="textarea"
            :rows="2"
            placeholder="其他备注信息"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleDialogClose">取消</el-button>
          <el-button type="primary" @click="handleSubmit" :loading="submitting">
            确定
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 查看详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="维修记录详情"
      width="900px"
    >
      <el-descriptions :column="2" border v-if="currentRecord">
        <el-descriptions-item label="资产信息">
          <div>
            <strong>{{ currentRecord.asset?.name }}</strong><br/>
            <span style="color: #909399;">{{ currentRecord.asset?.asset_tag }}</span>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="维修标题">
          {{ currentRecord.title }}
        </el-descriptions-item>
        <el-descriptions-item label="报修人">
          {{ currentRecord.reported_by?.name }}
        </el-descriptions-item>
        <el-descriptions-item label="维修人员">
          {{ currentRecord.assigned_to?.name || '未分配' }}
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentRecord.status_color">{{ currentRecord.status_text }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="优先级">
          <el-tag :type="currentRecord.priority_color">{{ currentRecord.priority_text }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="维修类型">
          {{ currentRecord.type_text }}
        </el-descriptions-item>
        <el-descriptions-item label="报修日期">
          {{ formatDate(currentRecord.reported_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="开始日期">
          {{ formatDate(currentRecord.start_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="完成日期">
          {{ formatDate(currentRecord.completed_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="故障描述" :span="2">
          {{ currentRecord.description }}
        </el-descriptions-item>
        <el-descriptions-item label="故障诊断" :span="2" v-if="currentRecord.diagnosis">
          {{ currentRecord.diagnosis }}
        </el-descriptions-item>
        <el-descriptions-item label="解决方案" :span="2" v-if="currentRecord.solution">
          {{ currentRecord.solution }}
        </el-descriptions-item>
        <el-descriptions-item label="使用配件" :span="2" v-if="currentRecord.parts_used">
          {{ currentRecord.parts_used }}
        </el-descriptions-item>
        <el-descriptions-item label="维修统计">
          <div>预估工时: {{ currentRecord.estimated_hours || 0 }}小时</div>
          <div>实际工时: {{ currentRecord.actual_hours || 0 }}小时</div>
          <div>预估费用: {{ formatCurrency(currentRecord.estimated_cost) }}</div>
          <div>实际费用: {{ formatCurrency(currentRecord.actual_cost) }}</div>
        </el-descriptions-item>
        <el-descriptions-item label="供应商信息" v-if="currentRecord.vendor">
          <div>供应商: {{ currentRecord.vendor }}</div>
          <div>联系方式: {{ currentRecord.vendor_contact }}</div>
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2" v-if="currentRecord.notes">
          {{ currentRecord.notes }}
        </el-descriptions-item>
        <el-descriptions-item label="创建信息">
          <div>创建人: {{ currentRecord.created_by?.name }}</div>
          <div>创建时间: {{ formatDateTime(currentRecord.created_at) }}</div>
        </el-descriptions-item>
        <el-descriptions-item label="更新信息" v-if="currentRecord.updated_by">
          <div>更新人: {{ currentRecord.updated_by?.name }}</div>
          <div>更新时间: {{ formatDateTime(currentRecord.updated_at) }}</div>
        </el-descriptions-item>
      </el-descriptions>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="detailDialogVisible = false">关闭</el-button>
          <el-button type="primary" @click="printDetail" v-if="currentRecord">打印</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 分配对话框 -->
    <el-dialog
      v-model="assignDialogVisible"
      title="分配维修人员"
      width="500px"
    >
      <el-form :model="assignForm" label-width="100px">
        <el-form-item label="选择人员" prop="assigned_to">
          <el-select
            v-model="assignForm.assigned_to"
            placeholder="选择维修人员"
            filterable
            style="width: 100%"
          >
            <el-option
              v-for="user in availableUsers"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="assignDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleAssignSubmit">确定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 完成对话框 -->
    <el-dialog
      v-model="completeDialogVisible"
      title="完成维修"
      width="700px"
    >
      <el-form :model="completeForm" label-width="120px">
        <el-form-item label="故障诊断">
          <el-input
            v-model="completeForm.diagnosis"
            type="textarea"
            :rows="3"
            placeholder="填写故障诊断结果"
          />
        </el-form-item>
        <el-form-item label="解决方案">
          <el-input
            v-model="completeForm.solution"
            type="textarea"
            :rows="3"
            placeholder="填写解决方案"
          />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="实际工时(小时)">
              <el-input-number v-model="completeForm.actual_hours" :min="1" :precision="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="实际费用">
              <el-input-number v-model="completeForm.actual_cost" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="供应商" v-if="completeForm.vendor">
          <el-input v-model="completeForm.vendor" placeholder="维修供应商" />
        </el-form-item>
        <el-form-item label="联系方式" v-if="completeForm.vendor_contact">
          <el-input v-model="completeForm.vendor_contact" placeholder="供应商联系方式" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="completeForm.notes"
            type="textarea"
            :rows="2"
            placeholder="其他备注"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="completeDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleCompleteSubmit">完成维修</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 取消对话框 -->
    <el-dialog
      v-model="cancelDialogVisible"
      title="取消维修"
      width="500px"
    >
      <el-form :model="cancelForm" label-width="100px">
        <el-form-item label="取消原因">
          <el-input
            v-model="cancelForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请填写取消原因"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="cancelDialogVisible = false">取消</el-button>
          <el-button type="danger" @click="handleCancelSubmit">确认取消</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import maintenanceApi from '@/api/maintenance'
import assetApi from '@/api/asset'
import userApi from '@/api/user'
import { formatDate, formatDateTime, formatCurrency } from '@/utils/formatter'

const loading = ref(false)
const records = ref([])
const statistics = ref({})
const availableAssets = ref([])
const availableUsers = ref([])

// 搜索表单
const searchForm = reactive({
  search: '',
  status: '',
  priority: '',
  type: '',
  dateRange: []
})

// 分页
const pagination = reactive({
  current: 1,
  pageSize: 20,
  total: 0
})

// 对话框控制
const dialogVisible = ref(false)
const detailDialogVisible = ref(false)
const assignDialogVisible = ref(false)
const completeDialogVisible = ref(false)
const cancelDialogVisible = ref(false)
const dialogTitle = ref('')
const currentRecord = ref(null)
const submitting = ref(false)

// 表单数据
const form = reactive({
  asset_id: '',
  title: '',
  description: '',
  priority: 'medium',
  type: 'hardware',
  reported_date: new Date().toISOString().split('T')[0],
  estimated_hours: null,
  estimated_cost: null,
  parts_used: '',
  notes: ''
})

const formRef = ref(null)

// 表单验证规则
const rules = {
  asset_id: [{ required: true, message: '请选择资产', trigger: 'change' }],
  title: [{ required: true, message: '请输入维修标题', trigger: 'blur' }],
  description: [{ required: true, message: '请输入故障描述', trigger: 'blur' }],
  priority: [{ required: true, message: '请选择优先级', trigger: 'change' }],
  type: [{ required: true, message: '请选择维修类型', trigger: 'change' }],
  reported_date: [{ required: true, message: '请选择报修日期', trigger: 'change' }]
}

// 分配表单
const assignForm = reactive({
  assigned_to: ''
})

// 完成表单
const completeForm = reactive({
  diagnosis: '',
  solution: '',
  actual_hours: null,
  actual_cost: null,
  vendor: '',
  vendor_contact: '',
  notes: ''
})

// 取消表单
const cancelForm = reactive({
  reason: ''
})

// 加载数据
const loadRecords = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.current,
      per_page: pagination.pageSize,
      search: searchForm.search,
      status: searchForm.status,
      priority: searchForm.priority,
      type: searchForm.type
    }

    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.start_date = searchForm.dateRange[0]
      params.end_date = searchForm.dateRange[1]
    }

    const res = await maintenanceApi.list(params)
    records.value = res.data.data
    pagination.total = res.data.total
  } catch (error) {
    console.error('加载维修记录失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

// 加载统计
const loadStatistics = async () => {
  try {
    const res = await maintenanceApi.statistics()
    statistics.value = res.data
  } catch (error) {
    console.error('加载统计失败:', error)
  }
}

// 加载可用资产
const loadAvailableAssets = async () => {
  try {
    const res = await assetApi.list({ status: 'ready', per_page: 100 })
    availableAssets.value = res.data.data.filter(asset => 
      asset.status !== 'scrapped' && asset.status !== 'maintenance'
    )
  } catch (error) {
    console.error('加载资产失败:', error)
  }
}

// 加载可用用户
const loadAvailableUsers = async () => {
  try {
    const res = await userApi.list()
    availableUsers.value = res.data.data || []
  } catch (error) {
    console.error('加载用户失败:', error)
  }
}

// 权限检查
const canEdit = (record) => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  return user.id === record.reported_by || user.role === 'admin'
}

const canAssign = (record) => {
  return record.status === 'pending' && currentUser.value.role === 'admin'
}

const canComplete = (record) => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  return (record.status === 'pending' || record.status === 'in_progress') && 
         (user.id === record.assigned_to || user.role === 'admin')
}

const canCancel = (record) => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  return (record.status === 'pending' || record.status === 'in_progress') && 
         (user.id === record.reported_by || user.role === 'assigned_to' || user.role === 'admin')
}

// 获取当前用户
const currentUser = ref({})
try {
  currentUser.value = JSON.parse(localStorage.getItem('user') || '{}')
} catch {
  currentUser.value = {}
}

// 事件处理
const handleCreate = () => {
  resetForm()
  dialogTitle.value = '新增维修记录'
  dialogVisible.value = true
}

const handleEdit = (record) => {
  Object.assign(form, {
    asset_id: record.asset_id,
    title: record.title,
    description: record.description,
    priority: record.priority,
    type: record.type,
    reported_date: record.reported_date,
    estimated_hours: record.estimated_hours,
    estimated_cost: record.estimated_cost,
    parts_used: record.parts_used,
    notes: record.notes
  })
  dialogTitle.value = '编辑维修记录'
  currentRecord.value = record
  dialogVisible.value = true
}

const handleView = (record) => {
  currentRecord.value = record
  detailDialogVisible.value = true
}

const handleAssign = (record) => {
  currentRecord.value = record
  assignForm.assigned_to = ''
  assignDialogVisible.value = true
}

const handleComplete = (record) => {
  currentRecord.value = record
  completeForm.diagnosis = record.diagnosis || ''
  completeForm.solution = record.solution || ''
  completeForm.actual_hours = record.actual_hours
  completeForm.actual_cost = record.actual_cost
  completeForm.vendor = record.vendor || ''
  completeForm.vendor_contact = record.vendor_contact || ''
  completeForm.notes = record.notes || ''
  completeDialogVisible.value = true
}

const handleCancel = (record) => {
  currentRecord.value = record
  cancelForm.reason = ''
  cancelDialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      if (currentRecord.value) {
        // 更新
        await maintenanceApi.update(currentRecord.value.id, form)
        ElMessage.success('更新成功')
      } else {
        // 创建
        await maintenanceApi.create(form)
        ElMessage.success('创建成功')
      }
      dialogVisible.value = false
      loadRecords()
      loadStatistics()
    } catch (error) {
      console.error('保存失败:', error)
      ElMessage.error('保存失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleAssignSubmit = async () => {
  if (!assignForm.assigned_to) {
    ElMessage.warning('请选择维修人员')
    return
  }

  try {
    await maintenanceApi.assign(currentRecord.value.id, assignForm)
    ElMessage.success('分配成功')
    assignDialogVisible.value = false
    loadRecords()
    loadStatistics()
  } catch (error) {
    console.error('分配失败:', error)
    ElMessage.error('分配失败')
  }
}

const handleCompleteSubmit = async () => {
  if (!completeForm.diagnosis && !completeForm.solution) {
    ElMessage.warning('请至少填写故障诊断或解决方案')
    return
  }

  try {
    await maintenanceApi.complete(currentRecord.value.id, completeForm)
    ElMessage.success('维修完成')
    completeDialogVisible.value = false
    loadRecords()
    loadStatistics()
  } catch (error) {
    console.error('完成失败:', error)
    ElMessage.error('完成失败')
  }
}

const handleCancelSubmit = async () => {
  if (!cancelForm.reason.trim()) {
    ElMessage.warning('请填写取消原因')
    return
  }

  try {
    await maintenanceApi.cancel(currentRecord.value.id, cancelForm)
    ElMessage.success('已取消')
    cancelDialogVisible.value = false
    loadRecords()
    loadStatistics()
  } catch (error) {
    console.error('取消失败:', error)
    ElMessage.error('取消失败')
  }
}

const handleAssetChange = (assetId) => {
  const asset = availableAssets.value.find(a => a.id === assetId)
  if (asset) {
    form.title = `${asset.name} 维修申请`
  }
}

const handleDialogClose = () => {
  dialogVisible.value = false
  currentRecord.value = null
}

const resetForm = () => {
  Object.assign(form, {
    asset_id: '',
    title: '',
    description: '',
    priority: 'medium',
    type: 'hardware',
    reported_date: new Date().toISOString().split('T')[0],
    estimated_hours: null,
    estimated_cost: null,
    parts_used: '',
    notes: ''
  })
  if (formRef.value) {
    formRef.value.clearValidate()
  }
}

const resetSearch = () => {
  Object.assign(searchForm, {
    search: '',
    status: '',
    priority: '',
    type: '',
    dateRange: []
  })
  pagination.current = 1
  loadRecords()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  pagination.current = 1
  loadRecords()
}

const handleCurrentChange = (page) => {
  pagination.current = page
  loadRecords()
}

const printDetail = () => {
  window.print()
}

// 初始化
onMounted(() => {
  loadRecords()
  loadStatistics()
  loadAvailableAssets()
  loadAvailableUsers()
})
</script>

<style scoped>
.maintenance-index {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-card {
  text-align: center;
  padding: 20px 0;
}

.stat-title {
  font-size: 14px;
  color: #909399;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #409eff;
}

@media print {
  .maintenance-index {
    display: none;
  }
  
  .el-dialog {
    position: static !important;
    width: 100% !important;
    margin: 0 !important;
  }
  
  .el-dialog__header,
  .el-dialog__footer {
    display: none !important;
  }
  
  .el-dialog__body {
    padding: 0 !important;
  }
}
</style>