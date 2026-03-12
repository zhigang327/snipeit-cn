<template>
  <div class="disposal-management">
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <div class="left">
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          新建报废申请
        </el-button>
        <el-button @click="handleExport">
          <el-icon><Download /></el-icon>
          导出数据
        </el-button>
      </div>
      
      <div class="right">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索报废编号、资产编号、原因..."
          style="width: 240px; margin-right: 10px;"
          @keyup.enter="handleSearch"
        >
          <template #append>
            <el-button @click="handleSearch">
              <el-icon><Search /></el-icon>
            </el-button>
          </template>
        </el-input>
        
        <el-button @click="showFilters = !showFilters">
          <el-icon><Filter /></el-icon>
          筛选
        </el-button>
      </div>
    </div>

    <!-- 筛选条件 -->
    <div v-if="showFilters" class="filters">
      <el-form :model="filterForm" inline>
        <el-form-item label="状态：">
          <el-select v-model="filterForm.status" placeholder="选择状态" clearable>
            <el-option label="待审批" value="pending" />
            <el-option label="已批准" value="approved" />
            <el-option label="已拒绝" value="rejected" />
            <el-option label="已完成" value="completed" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="报废类型：">
          <el-select v-model="filterForm.disposal_type" placeholder="选择类型" clearable>
            <el-option label="出售" value="sold" />
            <el-option label="报废" value="scrapped" />
            <el-option label="捐赠" value="donated" />
            <el-option label="调拨" value="transferred" />
            <el-option label="丢失" value="lost" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="日期范围：">
          <el-date-picker
            v-model="filterForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="handleFilter">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 统计面板 -->
    <div class="statistics-panel">
      <el-row :gutter="20">
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.total?.count || 0 }}</div>
            <div class="stat-label">总报废记录</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.total?.amount || 0 | currency }}</div>
            <div class="stat-label">报废总额</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.total?.gain_loss || 0 | currency }}</div>
            <div class="stat-label" :class="{ 'text-success': statistics.total?.gain_loss > 0, 'text-danger': statistics.total?.gain_loss < 0 }">
              {{ statistics.total?.gain_loss > 0 ? '盈利' : statistics.total?.gain_loss < 0 ? '亏损' : '持平' }}
            </div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ pendingCount }}</div>
            <div class="stat-label">待审批</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <!-- 数据表格 -->
    <div class="table-container">
      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        style="width: 100%"
        @sort-change="handleSortChange"
      >
        <el-table-column prop="disposal_number" label="报废编号" width="140" sortable />
        <el-table-column label="资产信息" min-width="180">
          <template #default="{ row }">
            <div>
              <div>{{ row.asset?.asset_tag }} - {{ row.asset?.name }}</div>
              <div style="font-size: 12px; color: #666;">{{ row.asset?.brand }} {{ row.asset?.model }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="disposal_type_label" label="报废类型" width="100" />
        <el-table-column prop="disposal_date" label="报废日期" width="120" sortable />
        <el-table-column label="金额信息" width="180">
          <template #default="{ row }">
            <div>
              <div>账面价值: {{ row.book_value | currency }}</div>
              <div>报废金额: {{ row.disposal_amount | currency }}</div>
              <div :class="{ 'text-success': row.gain_loss > 0, 'text-danger': row.gain_loss < 0 }">
                损益: {{ row.gain_loss | currency }}
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status_label" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">
              {{ row.status_label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="user.name" label="申请人" width="120" />
        <el-table-column prop="created_at" label="申请时间" width="160" sortable />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleView(row)">详情</el-button>
            
            <template v-if="row.status === 'pending'">
              <el-button v-if="canApprove(row)" size="small" type="success" @click="handleApprove(row)">
                审批
              </el-button>
              <el-button v-if="canEdit(row)" size="small" @click="handleEdit(row)">编辑</el-button>
              <el-button v-if="canCancel(row)" size="small" type="danger" @click="handleCancel(row)">
                取消
              </el-button>
            </template>
            
            <template v-if="row.status === 'approved'">
              <el-button size="small" type="primary" @click="handleComplete(row)">完成</el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.size"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 新建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="800px"
      @close="handleDialogClose"
    >
      <DisposalForm
        v-if="dialogVisible"
        :form-data="currentRecord"
        :mode="dialogMode"
        @submit="handleFormSubmit"
        @cancel="dialogVisible = false"
      />
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailVisible"
      title="报废记录详情"
      width="900px"
    >
      <DisposalDetail
        v-if="detailVisible"
        :record="currentRecord"
        @close="detailVisible = false"
      />
    </el-dialog>

    <!-- 审批对话框 -->
    <el-dialog
      v-model="approveVisible"
      title="审批报废申请"
      width="500px"
    >
      <ApproveForm
        v-if="approveVisible"
        :record="currentRecord"
        @approve="handleApproveSubmit"
        @reject="handleRejectSubmit"
        @cancel="approveVisible = false"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download, Search, Filter } from '@element-plus/icons-vue'
import DisposalForm from './components/DisposalForm.vue'
import DisposalDetail from './components/DisposalDetail.vue'
import ApproveForm from './components/ApproveForm.vue'
import { disposalApi } from '@/api/export'

// 响应式数据
const searchKeyword = ref('')
const showFilters = ref(false)
const loading = ref(false)
const dialogVisible = ref(false)
const detailVisible = ref(false)
const approveVisible = ref(false)
const dialogMode = ref('create')
const currentRecord = ref(null)

const filterForm = reactive({
  status: '',
  disposal_type: '',
  dateRange: []
})

const pagination = reactive({
  current: 1,
  size: 20,
  total: 0
})

const tableData = ref([])
const statistics = ref({})

// 计算属性
const dialogTitle = computed(() => {
  return dialogMode.value === 'create' ? '新建报废申请' : '编辑报废申请'
})

const pendingCount = computed(() => {
  return statistics.value.by_status?.find(s => s.status === 'pending')?.count || 0
})

// 生命周期
onMounted(() => {
  loadData()
  loadStatistics()
})

// 方法
const loadData = async () => {
  try {
    loading.value = true
    
    const params = {
      page: pagination.current,
      per_page: pagination.size,
      search: searchKeyword.value
    }
    
    if (filterForm.status) params.status = filterForm.status
    if (filterForm.disposal_type) params.disposal_type = filterForm.disposal_type
    if (filterForm.dateRange?.length === 2) {
      params.start_date = filterForm.dateRange[0]
      params.end_date = filterForm.dateRange[1]
    }
    
    const response = await disposalApi.getList(params)
    
    if (response.success) {
      tableData.value = response.data.data
      pagination.total = response.data.total
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('加载数据失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const params = {}
    if (filterForm.dateRange?.length === 2) {
      params.start_date = filterForm.dateRange[0]
      params.end_date = filterForm.dateRange[1]
    }
    
    const response = await disposalApi.getStatistics(params)
    
    if (response.success) {
      statistics.value = response.data
    }
  } catch (error) {
    console.error('加载统计失败:', error)
  }
}

const handleSearch = () => {
  pagination.current = 1
  loadData()
}

const handleFilter = () => {
  pagination.current = 1
  loadData()
  loadStatistics()
}

const handleReset = () => {
  Object.keys(filterForm).forEach(key => {
    filterForm[key] = Array.isArray(filterForm[key]) ? [] : ''
  })
  searchKeyword.value = ''
  pagination.current = 1
  loadData()
  loadStatistics()
}

const handleSizeChange = (size) => {
  pagination.size = size
  pagination.current = 1
  loadData()
}

const handleCurrentChange = (page) => {
  pagination.current = page
  loadData()
}

const handleSortChange = (sort) => {
  // 处理排序
  console.log('排序变化:', sort)
}

const handleCreate = () => {
  dialogMode.value = 'create'
  currentRecord.value = null
  dialogVisible.value = true
}

const handleEdit = (row) => {
  dialogMode.value = 'edit'
  currentRecord.value = { ...row }
  dialogVisible.value = true
}

const handleView = (row) => {
  currentRecord.value = row
  detailVisible.value = true
}

const handleApprove = (row) => {
  currentRecord.value = row
  approveVisible.value = true
}

const handleCancel = async (row) => {
  try {
    await ElMessageBox.confirm('确定要取消此报废申请吗？', '确认取消', {
      type: 'warning'
    })
    
    const response = await disposalApi.cancel(row.id)
    
    if (response.success) {
      ElMessage.success('取消成功')
      loadData()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('取消失败')
    }
  }
}

const handleComplete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要完成此报废流程吗？完成后资产状态将变为已报废。', '确认完成', {
      type: 'warning'
    })
    
    const response = await disposalApi.complete(row.id)
    
    if (response.success) {
      ElMessage.success('完成成功')
      loadData()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('完成失败')
    }
  }
}

const handleFormSubmit = async (formData) => {
  try {
    let response
    
    if (dialogMode.value === 'create') {
      response = await disposalApi.create(formData)
    } else {
      response = await disposalApi.update(currentRecord.value.id, formData)
    }
    
    if (response.success) {
      ElMessage.success(dialogMode.value === 'create' ? '创建成功' : '更新成功')
      dialogVisible.value = false
      loadData()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('操作失败')
    console.error(error)
  }
}

const handleApproveSubmit = async (approvalData) => {
  try {
    const response = await disposalApi.approve(currentRecord.value.id, approvalData)
    
    if (response.success) {
      ElMessage.success('审批成功')
      approveVisible.value = false
      loadData()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('审批失败')
    console.error(error)
  }
}

const handleRejectSubmit = async (rejectData) => {
  try {
    const response = await disposalApi.reject(currentRecord.value.id, rejectData)
    
    if (response.success) {
      ElMessage.success('拒绝成功')
      approveVisible.value = false
      loadData()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('拒绝失败')
    console.error(error)
  }
}

const handleDialogClose = () => {
  currentRecord.value = null
}

const handleExport = async () => {
  try {
    const params = {}
    if (filterForm.dateRange?.length === 2) {
      params.start_date = filterForm.dateRange[0]
      params.end_date = filterForm.dateRange[1]
    }
    
    const response = await disposalApi.export(params)
    
    if (response.success) {
      // 这里可以调用前端导出功能
      ElMessage.success('导出数据准备完成')
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('导出失败')
    console.error(error)
  }
}

// 权限检查
const canApprove = (row) => {
  // 这里可以根据用户角色判断审批权限
  return true // 临时返回true
}

const canEdit = (row) => {
  // 只有申请人可以编辑自己的申请
  return row.status === 'pending' && row.user?.id === currentUser.value?.id
}

const canCancel = (row) => {
  // 只有申请人可以取消自己的申请
  return row.status === 'pending' && row.user?.id === currentUser.value?.id
}

const getStatusTagType = (status) => {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    completed: 'info'
  }
  return types[status] || 'info'
}

// 过滤器
const currency = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}
</script>

<style scoped>
.disposal-management {
  padding: 20px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.filters {
  background: #f5f7fa;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
}

.statistics-panel {
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #409eff;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  color: #666;
}

.table-container {
  background: white;
  border-radius: 4px;
  overflow: hidden;
}

.pagination {
  padding: 20px;
  text-align: right;
}

.text-success {
  color: #67c23a;
}

.text-danger {
  color: #f56c6c;
}
</style>