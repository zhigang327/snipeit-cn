<template>
  <div class="borrow-management">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-cards">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-title">待审批</div>
            <div class="stat-value" style="color: #e6a23c">{{ statistics.pending_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-title">已借出</div>
            <div class="stat-value" style="color: #409eff">{{ statistics.borrowed_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-title">逾期未还</div>
            <div class="stat-value" style="color: #f56c6c">{{ statistics.overdue_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-title">已归还</div>
            <div class="stat-value" style="color: #67c23a">{{ statistics.returned_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作工具栏 -->
    <el-card class="toolbar-card">
      <el-form :inline="true" :model="searchForm" size="small">
        <el-form-item label="状态筛选">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
            <el-option label="待审批" value="pending"></el-option>
            <el-option label="已批准" value="approved"></el-option>
            <el-option label="已拒绝" value="rejected"></el-option>
            <el-option label="已借出" value="borrowed"></el-option>
            <el-option label="已归还" value="returned"></el-option>
            <el-option label="逾期未还" value="overdue"></el-option>
            <el-option label="已取消" value="cancelled"></el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="资产">
          <el-select
            v-model="searchForm.asset_id"
            placeholder="请选择资产"
            filterable
            clearable
            style="width: 200px"
          >
            <el-option
              v-for="asset in assetList"
              :key="asset.id"
              :label="`${asset.asset_tag} - ${asset.name}`"
              :value="asset.id"
            ></el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="借用人">
          <el-select
            v-model="searchForm.borrower_id"
            placeholder="请选择借用人"
            filterable
            clearable
            style="width: 200px"
          >
            <el-option
              v-for="user in userList"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            ></el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="借用日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            @change="handleDateChange"
          ></el-date-picker>
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="searchRecords" icon="Search">搜索</el-button>
          <el-button @click="resetSearch" icon="Refresh">重置</el-button>
          <el-button type="success" @click="openCreateDialog" icon="Plus">新建借用</el-button>
          <el-button type="warning" @click="exportData" icon="Download">导出</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 借用记录表格 -->
    <el-card>
      <el-table
        :data="tableData"
        v-loading="loading"
        style="width: 100%"
        @sort-change="handleSortChange"
      >
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column prop="asset.asset_tag" label="资产编号" width="120"></el-table-column>
        <el-table-column prop="asset.name" label="资产名称" min-width="150"></el-table-column>
        <el-table-column prop="borrower.name" label="借用人" width="100"></el-table-column>
        <el-table-column prop="borrow_date" label="借用日期" width="110"></el-table-column>
        <el-table-column prop="expected_return_date" label="预计归还" width="110">
          <template #default="{ row }">
            <span :class="{ 'overdue-date': row.is_overdue }">{{ row.expected_return_date }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="actual_return_date" label="实际归还" width="110"></el-table-column>
        <el-table-column prop="borrow_purpose" label="借用目的" min-width="150"></el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status_color">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="deposit_amount" label="押金" width="100">
          <template #default="{ row }">
            {{ row.deposit_amount ? '¥' + row.deposit_amount : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" sortable></el-table-column>
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="viewDetail(row)" icon="View">查看</el-button>
            
            <el-button
              v-if="row.status === 'pending' && hasPermission('borrow.approve')"
              size="small"
              type="primary"
              @click="approveBorrow(row)"
              icon="Check"
            >审批</el-button>
            
            <el-button
              v-if="row.status === 'approved'"
              size="small"
              type="success"
              @click="confirmBorrow(row)"
              icon="Tickets"
            >确认借出</el-button>
            
            <el-button
              v-if="row.status === 'borrowed'"
              size="small"
              type="warning"
              @click="openReturnDialog(row)"
              icon="RefreshRight"
            >归还</el-button>
            
            <el-button
              v-if="row.status === 'pending'"
              size="small"
              type="danger"
              @click="cancelBorrow(row)"
              icon="Close"
            >取消</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.current_page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        ></el-pagination>
      </div>
    </el-card>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      :title="`借用记录详情 - ID: ${currentRecord.id}`"
      width="800px"
      @close="detailDialogVisible = false"
    >
      <el-descriptions :column="2" border>
        <el-descriptions-item label="资产信息">
          {{ currentRecord.asset?.name }} ({{ currentRecord.asset?.asset_tag }})
        </el-descriptions-item>
        <el-descriptions-item label="资产类别">
          {{ currentRecord.asset?.category?.name }}
        </el-descriptions-item>
        <el-descriptions-item label="借用人">
          {{ currentRecord.borrower?.name }}
        </el-descriptions-item>
        <el-descriptions-item label="审批人">
          {{ currentRecord.approver?.name || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="借用日期">
          {{ currentRecord.borrow_date }}
        </el-descriptions-item>
        <el-descriptions-item label="预计归还">
          {{ currentRecord.expected_return_date }}
        </el-descriptions-item>
        <el-descriptions-item label="实际归还">
          {{ currentRecord.actual_return_date || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="借用目的">
          {{ currentRecord.borrow_purpose }}
        </el-descriptions-item>
        <el-descriptions-item label="借用条件">
          {{ currentRecord.borrow_conditions || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentRecord.status_color">{{ currentRecord.status_text }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="押金金额">
          {{ currentRecord.deposit_amount ? '¥' + currentRecord.deposit_amount : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="押金是否退还">
          {{ currentRecord.deposit_returned ? '是' : '否' }}
        </el-descriptions-item>
        <el-descriptions-item label="损坏描述" :span="2">
          {{ currentRecord.damage_description || '无' }}
        </el-descriptions-item>
        <el-descriptions-item label="损坏赔偿">
          {{ currentRecord.damage_fee ? '¥' + currentRecord.damage_fee : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="损坏是否处理">
          {{ currentRecord.damage_resolved ? '已处理' : '未处理' }}
        </el-descriptions-item>
        <el-descriptions-item label="拒绝原因" :span="2" v-if="currentRecord.rejection_reason">
          {{ currentRecord.rejection_reason }}
        </el-descriptions-item>
        <el-descriptions-item label="归还备注" :span="2" v-if="currentRecord.return_notes">
          {{ currentRecord.return_notes }}
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">
          {{ formatDateTime(currentRecord.created_at) }}
        </el-descriptions-item>
        <el-descriptions-item label="更新时间">
          {{ formatDateTime(currentRecord.updated_at) }}
        </el-descriptions-item>
      </el-descriptions>
      
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 新建借用对话框 -->
    <el-dialog
      v-model="createDialogVisible"
      title="新建借用申请"
      width="600px"
      @close="resetCreateForm"
    >
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="资产" prop="asset_id">
          <el-select
            v-model="createForm.asset_id"
            placeholder="请选择资产"
            filterable
            style="width: 100%"
            @change="handleAssetChange"
          >
            <el-option
              v-for="asset in availableAssets"
              :key="asset.id"
              :label="`${asset.asset_tag} - ${asset.name} (${asset.category?.name})`"
              :value="asset.id"
            ></el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="借用目的" prop="borrow_purpose">
          <el-input
            v-model="createForm.borrow_purpose"
            type="textarea"
            :rows="2"
            placeholder="请输入借用目的"
            maxlength="500"
            show-word-limit
          ></el-input>
        </el-form-item>
        
        <el-form-item label="借用日期" prop="borrow_date">
          <el-date-picker
            v-model="createForm.borrow_date"
            type="date"
            placeholder="选择借用日期"
            value-format="yyyy-MM-dd"
            :picker-options="borrowDatePickerOptions"
            style="width: 100%"
          ></el-date-picker>
        </el-form-item>
        
        <el-form-item label="预计归还" prop="expected_return_date">
          <el-date-picker
            v-model="createForm.expected_return_date"
            type="date"
            placeholder="选择预计归还日期"
            value-format="yyyy-MM-dd"
            :picker-options="returnDatePickerOptions"
            style="width: 100%"
          ></el-date-picker>
        </el-form-item>
        
        <el-form-item label="押金金额">
          <el-input-number
            v-model="createForm.deposit_amount"
            :min="0"
            :step="100"
            placeholder="押金金额"
            style="width: 100%"
          ></el-input-number>
        </el-form-item>
        
        <el-form-item label="借用条件">
          <el-input
            v-model="createForm.borrow_conditions"
            type="textarea"
            :rows="2"
            placeholder="请输入借用条件（可选）"
            maxlength="1000"
            show-word-limit
          ></el-input>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitCreateForm" :loading="creating">提交申请</el-button>
      </template>
    </el-dialog>

    <!-- 归还对话框 -->
    <el-dialog
      v-model="returnDialogVisible"
      title="归还资产"
      width="500px"
      @close="resetReturnForm"
    >
      <el-form ref="returnFormRef" :model="returnForm" :rules="returnRules" label-width="100px">
        <el-form-item label="归还备注">
          <el-input
            v-model="returnForm.return_notes"
            type="textarea"
            :rows="2"
            placeholder="请输入归还备注（可选）"
            maxlength="1000"
            show-word-limit
          ></el-input>
        </el-form-item>
        
        <el-form-item label="损坏描述" v-if="showDamageFields">
          <el-input
            v-model="returnForm.damage_description"
            type="textarea"
            :rows="2"
            placeholder="如有损坏请描述"
            maxlength="1000"
            show-word-limit
          ></el-input>
        </el-form-item>
        
        <el-form-item label="损坏赔偿" v-if="showDamageFields && returnForm.damage_description">
          <el-input-number
            v-model="returnForm.damage_fee"
            :min="0"
            :step="100"
            placeholder="赔偿金额"
            style="width: 100%"
          ></el-input-number>
        </el-form-item>
        
        <el-form-item label="损坏已处理" v-if="showDamageFields && returnForm.damage_description">
          <el-switch v-model="returnForm.damage_resolved"></el-switch>
        </el-form-item>
        
        <el-form-item label="退还押金" v-if="currentRecord.deposit_amount > 0">
          <el-switch v-model="returnForm.deposit_returned"></el-switch>
          <span style="margin-left: 10px; color: #666">
            押金金额：¥{{ currentRecord.deposit_amount }}
          </span>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="returnDialogVisible = false">取消</el-button>
        <el-button type="warning" @click="confirmReturn" :loading="returning">确认归还</el-button>
      </template>
    </el-dialog>

    <!-- 审批对话框 -->
    <el-dialog
      v-model="approveDialogVisible"
      title="审批借用申请"
      width="500px"
    >
      <p>确定要批准该借用申请吗？</p>
      <p style="margin-top: 10px">
        <strong>资产：</strong>{{ currentRecord.asset?.name }}<br>
        <strong>借用人：</strong>{{ currentRecord.borrower?.name }}<br>
        <strong>借用日期：</strong>{{ currentRecord.borrow_date }}<br>
        <strong>预计归还：</strong>{{ currentRecord.expected_return_date }}
      </p>
      
      <template #footer>
        <el-button @click="approveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmApprove" :loading="approving">批准</el-button>
      </template>
    </el-dialog>

    <!-- 拒绝对话框 -->
    <el-dialog
      v-model="rejectDialogVisible"
      title="拒绝借用申请"
      width="500px"
      @close="rejectDialogVisible = false"
    >
      <el-form ref="rejectFormRef" :model="rejectForm" :rules="rejectRules" label-width="100px">
        <el-form-item label="拒绝原因" prop="rejection_reason">
          <el-input
            v-model="rejectForm.rejection_reason"
            type="textarea"
            :rows="3"
            placeholder="请输入拒绝原因"
            maxlength="500"
            show-word-limit
          ></el-input>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="rejectDialogVisible = false">取消</el-button>
        <el-button type="danger" @click="confirmReject" :loading="rejecting">拒绝</el-button>
      </template>
    </el-dialog>

    <!-- 取消对话框 -->
    <el-dialog
      v-model="cancelDialogVisible"
      title="取消借用申请"
      width="500px"
      @close="cancelDialogVisible = false"
    >
      <el-form ref="cancelFormRef" :model="cancelForm" label-width="100px">
        <el-form-item label="取消原因">
          <el-input
            v-model="cancelForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入取消原因（可选）"
            maxlength="500"
            show-word-limit
          ></el-input>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="cancelDialogVisible = false">取消</el-button>
        <el-button type="danger" @click="confirmCancel" :loading="cancelling">确认取消</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { borrowApi, assetApi, userApi } from '@/api'
import dayjs from 'dayjs'

// 数据定义
const loading = ref(false)
const creating = ref(false)
const approving = ref(false)
const rejecting = ref(false)
const returning = ref(false)
const cancelling = ref(false)

const statistics = ref({})
const tableData = ref([])
const assetList = ref([])
const userList = ref([])
const availableAssets = ref([])
const currentRecord = ref({})

// 分页
const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
})

// 搜索表单
const searchForm = reactive({
  status: '',
  asset_id: '',
  borrower_id: '',
  start_date: '',
  end_date: '',
})

const dateRange = ref([])

// 表单
const createForm = reactive({
  asset_id: '',
  borrow_purpose: '',
  borrow_date: dayjs().format('YYYY-MM-DD'),
  expected_return_date: dayjs().add(7, 'day').format('YYYY-MM-DD'),
  deposit_amount: 0,
  borrow_conditions: '',
})

const returnForm = reactive({
  return_notes: '',
  damage_description: '',
  damage_fee: 0,
  damage_resolved: false,
  deposit_returned: true,
})

const rejectForm = reactive({
  rejection_reason: '',
})

const cancelForm = reactive({
  reason: '',
})

// 表单验证
const createRules = {
  asset_id: [{ required: true, message: '请选择资产', trigger: 'blur' }],
  borrow_purpose: [{ required: true, message: '请输入借用目的', trigger: 'blur' }],
  borrow_date: [{ required: true, message: '请选择借用日期', trigger: 'blur' }],
  expected_return_date: [
    { required: true, message: '请选择预计归还日期', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (createForm.borrow_date && value <= createForm.borrow_date) {
          callback(new Error('预计归还日期必须晚于借用日期'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
}

const rejectRules = {
  rejection_reason: [{ required: true, message: '请输入拒绝原因', trigger: 'blur' }],
}

// 对话框控制
const detailDialogVisible = ref(false)
const createDialogVisible = ref(false)
const returnDialogVisible = ref(false)
const approveDialogVisible = ref(false)
const rejectDialogVisible = ref(false)
const cancelDialogVisible = ref(false)

const showDamageFields = ref(false)

// 日期选择器选项
const borrowDatePickerOptions = computed(() => ({
  disabledDate: (time) => {
    return time.getTime() < Date.now() - 8.64e7 // 不能选择昨天以前的日期
  }
}))

const returnDatePickerOptions = computed(() => ({
  disabledDate: (time) => {
    if (!createForm.borrow_date) return false
    const borrowDate = new Date(createForm.borrow_date)
    return time.getTime() <= borrowDate.getTime()
  }
}))

// 权限检查
const hasPermission = (permission) => {
  // 这里应该从用户信息中获取权限
  return true
}

// 初始化加载数据
onMounted(() => {
  loadStatistics()
  loadRecords()
  loadAssets()
  loadUsers()
  loadAvailableAssets()
})

// 加载统计数据
const loadStatistics = async () => {
  try {
    const response = await borrowApi.getStatistics()
    if (response.success) {
      statistics.value = response.data
    }
  } catch (error) {
    console.error('加载统计失败:', error)
  }
}

// 加载借用记录
const loadRecords = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
      ...searchForm
    }
    
    const response = await borrowApi.getRecords(params)
    if (response.success) {
      tableData.value = response.data
      pagination.total = response.pagination.total
    }
  } catch (error) {
    console.error('加载记录失败:', error)
  } finally {
    loading.value = false
  }
}

// 加载资产列表
const loadAssets = async () => {
  try {
    const response = await assetApi.getAssets({ per_page: 1000 })
    if (response.success) {
      assetList.value = response.data.filter(asset => asset.status !== 'retired')
    }
  } catch (error) {
    console.error('加载资产列表失败:', error)
  }
}

// 加载用户列表
const loadUsers = async () => {
  try {
    const response = await userApi.getUsers({ per_page: 1000 })
    if (response.success) {
      userList.value = response.data
    }
  } catch (error) {
    console.error('加载用户列表失败:', error)
  }
}

// 加载可用资产（状态为ready）
const loadAvailableAssets = async () => {
  try {
    const response = await assetApi.getAssets({ status: 'ready', per_page: 1000 })
    if (response.success) {
      availableAssets.value = response.data
    }
  } catch (error) {
    console.error('加载可用资产失败:', error)
  }
}

// 搜索记录
const searchRecords = () => {
  pagination.current_page = 1
  loadRecords()
}

// 重置搜索
const resetSearch = () => {
  searchForm.status = ''
  searchForm.asset_id = ''
  searchForm.borrower_id = ''
  searchForm.start_date = ''
  searchForm.end_date = ''
  dateRange.value = []
  searchRecords()
}

// 处理日期范围变化
const handleDateChange = (dates) => {
  if (dates && dates.length === 2) {
    searchForm.start_date = dates[0]
    searchForm.end_date = dates[1]
  } else {
    searchForm.start_date = ''
    searchForm.end_date = ''
  }
}

// 处理排序变化
const handleSortChange = ({ prop, order }) => {
  if (prop && order) {
    searchForm.sort_by = prop
    searchForm.sort_order = order === 'ascending' ? 'asc' : 'desc'
  } else {
    delete searchForm.sort_by
    delete searchForm.sort_order
  }
  searchRecords()
}

// 分页处理
const handleSizeChange = (size) => {
  pagination.per_page = size
  pagination.current_page = 1
  loadRecords()
}

const handleCurrentChange = (page) => {
  pagination.current_page = page
  loadRecords()
}

// 查看详情
const viewDetail = (record) => {
  currentRecord.value = record
  detailDialogVisible.value = true
}

// 打开新建对话框
const openCreateDialog = () => {
  createDialogVisible.value = true
}

// 处理资产选择变化
const handleAssetChange = (assetId) => {
  const asset = availableAssets.value.find(a => a.id === assetId)
  if (asset) {
    // 如果资产有押金要求，设置默认押金
    if (asset.deposit_required) {
      createForm.deposit_amount = asset.suggested_deposit || 0
    }
  }
}

// 重置新建表单
const resetCreateForm = () => {
  createForm.asset_id = ''
  createForm.borrow_purpose = ''
  createForm.borrow_date = dayjs().format('YYYY-MM-DD')
  createForm.expected_return_date = dayjs().add(7, 'day').format('YYYY-MM-DD')
  createForm.deposit_amount = 0
  createForm.borrow_conditions = ''
}

// 提交新建表单
const submitCreateForm = async () => {
  try {
    creating.value = true
    
    const response = await borrowApi.createBorrow(createForm)
    if (response.success) {
      ElMessage.success('借用申请已提交，等待审批')
      createDialogVisible.value = false
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('提交申请失败:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    creating.value = false
  }
}

// 审批借用
const approveBorrow = (record) => {
  currentRecord.value = record
  approveDialogVisible.value = true
}

const confirmApprove = async () => {
  try {
    approving.value = true
    
    const response = await borrowApi.approveBorrow(currentRecord.value.id)
    if (response.success) {
      ElMessage.success('借用申请已批准')
      approveDialogVisible.value = false
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('审批失败:', error)
    ElMessage.error(error.response?.data?.message || '审批失败')
  } finally {
    approving.value = false
  }
}

// 拒绝借用
const rejectBorrow = (record) => {
  currentRecord.value = record
  rejectDialogVisible.value = true
}

const confirmReject = async () => {
  try {
    rejecting.value = true
    
    const response = await borrowApi.rejectBorrow(currentRecord.value.id, rejectForm)
    if (response.success) {
      ElMessage.success('借用申请已拒绝')
      rejectDialogVisible.value = false
      rejectForm.rejection_reason = ''
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('拒绝失败:', error)
    ElMessage.error(error.response?.data?.message || '拒绝失败')
  } finally {
    rejecting.value = false
  }
}

// 确认借出
const confirmBorrow = async (record) => {
  try {
    await ElMessageBox.confirm('确认要将该资产借出吗？', '确认借出', {
      type: 'warning'
    })
    
    const response = await borrowApi.confirmBorrow(record.id)
    if (response.success) {
      ElMessage.success('资产借出已确认')
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('确认借出失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

// 打开归还对话框
const openReturnDialog = (record) => {
  currentRecord.value = record
  showDamageFields.value = false
  returnForm.return_notes = ''
  returnForm.damage_description = ''
  returnForm.damage_fee = 0
  returnForm.damage_resolved = false
  returnForm.deposit_returned = record.deposit_amount > 0 ? false : true
  returnDialogVisible.value = true
}

// 重置归还表单
const resetReturnForm = () => {
  returnForm.return_notes = ''
  returnForm.damage_description = ''
  returnForm.damage_fee = 0
  returnForm.damage_resolved = false
  returnForm.deposit_returned = true
  showDamageFields.value = false
}

// 确认归还
const confirmReturn = async () => {
  try {
    returning.value = true
    
    const response = await borrowApi.returnAsset(currentRecord.value.id, returnForm)
    if (response.success) {
      ElMessage.success('资产归还已确认')
      returnDialogVisible.value = false
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('归还失败:', error)
    ElMessage.error(error.response?.data?.message || '归还失败')
  } finally {
    returning.value = false
  }
}

// 取消借用
const cancelBorrow = (record) => {
  currentRecord.value = record
  cancelDialogVisible.value = true
}

const confirmCancel = async () => {
  try {
    cancelling.value = true
    
    const response = await borrowApi.cancelBorrow(currentRecord.value.id, cancelForm)
    if (response.success) {
      ElMessage.success('借用申请已取消')
      cancelDialogVisible.value = false
      cancelForm.reason = ''
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('取消失败:', error)
    ElMessage.error(error.response?.data?.message || '取消失败')
  } finally {
    cancelling.value = false
  }
}

// 导出数据
const exportData = async () => {
  try {
    loading.value = true
    
    const response = await borrowApi.exportRecords(searchForm)
    if (response.success) {
      // 转换为CSV并下载
      const csvContent = response.data.map(row => row.join(',')).join('\n')
      const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      link.href = URL.createObjectURL(blob)
      link.download = `借用记录_${dayjs().format('YYYYMMDDHHmmss')}.csv`
      link.click()
      
      ElMessage.success(`已导出 ${response.total_records} 条记录`)
    }
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败')
  } finally {
    loading.value = false
  }
}

// 格式化日期时间
const formatDateTime = (datetime) => {
  return dayjs(datetime).format('YYYY-MM-DD HH:mm:ss')
}

// 检查逾期
const checkOverdueRecords = async () => {
  try {
    const response = await borrowApi.checkOverdue()
    if (response.success) {
      ElMessage.success(`已检查并更新 ${response.data.updated_count} 条逾期记录`)
      loadRecords()
      loadStatistics()
    }
  } catch (error) {
    console.error('检查逾期失败:', error)
  }
}

// 定时检查逾期（页面停留期间）
setInterval(() => {
  if (document.visibilityState === 'visible') {
    checkOverdueRecords()
  }
}, 5 * 60 * 1000) // 每5分钟检查一次
</script>

<style scoped>
.borrow-management {
  padding: 20px;
}

.stats-cards {
  margin-bottom: 20px;
}

.stat-item {
  text-align: center;
}

.stat-title {
  font-size: 14px;
  color: #666;
  margin-bottom: 5px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
}

.toolbar-card {
  margin-bottom: 20px;
}

.pagination-wrapper {
  margin-top: 20px;
  text-align: right;
}

.overdue-date {
  color: #f56c6c;
  font-weight: bold;
}
</style>