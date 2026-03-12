<template>
  <div class="inventory-management">
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <div class="left">
        <el-button type="primary" @click="handleCreateTask">
          <el-icon><Plus /></el-icon>
          新建盘点任务
        </el-button>
        <el-button @click="handleQuickInventory">
          <el-icon><DocumentChecked /></el-icon>
          快速盘点
        </el-button>
        <el-button @click="handleScanQR">
          <el-icon><Camera /></el-icon>
          扫码盘点
        </el-button>
        <el-button @click="handleExport">
          <el-icon><Download /></el-icon>
          导出数据
        </el-button>
      </div>
      
      <div class="right">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索任务名称、资产编号..."
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
            <el-option label="草稿" value="draft" />
            <el-option label="已激活" value="active" />
            <el-option label="进行中" value="in_progress" />
            <el-option label="已暂停" value="paused" />
            <el-option label="已完成" value="completed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="任务类型：">
          <el-select v-model="filterForm.task_type" placeholder="选择类型" clearable>
            <el-option label="定期盘点" value="periodic" />
            <el-option label="随机抽查" value="random" />
            <el-option label="全面盘点" value="full" />
            <el-option label="现场抽查" value="spot" />
            <el-option label="循环盘点" value="cycle" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="负责人：">
          <el-select
            v-model="filterForm.assigned_to"
            placeholder="选择负责人"
            clearable
            filterable
          >
            <el-option
              v-for="user in users"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
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
            <div class="stat-value">{{ statistics.total || 0 }}</div>
            <div class="stat-label">总盘点任务</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.active || 0 }}</div>
            <div class="stat-label">进行中任务</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.overdue || 0 }}</div>
            <div class="stat-label">逾期任务</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.pending_reviews || 0 }}</div>
            <div class="stat-label">待审核记录</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <!-- 任务列表 -->
    <div class="tasks-section">
      <div class="section-header">
        <h3>盘点任务</h3>
        <el-button type="primary" text @click="activeTab = 'tasks'">查看全部</el-button>
      </div>
      
      <el-table
        :data="taskList"
        v-loading="loading"
        stripe
        style="width: 100%"
        @row-click="handleTaskClick"
      >
        <el-table-column prop="task_number" label="任务编号" width="140" />
        <el-table-column prop="task_name" label="任务名称" min-width="160" />
        <el-table-column prop="task_type_label" label="任务类型" width="100" />
        <el-table-column label="日期范围" width="180">
          <template #default="{ row }">
            <div>{{ row.start_date }} 至 {{ row.end_date }}</div>
            <div v-if="row.is_overdue" style="color: #f56c6c; font-size: 12px;">
              <el-icon><Warning /></el-icon> 已逾期
            </div>
            <div v-else-if="row.is_due_today" style="color: #e6a23c; font-size: 12px;">
              <el-icon><Clock /></el-icon> 今日到期
            </div>
          </template>
        </el-table-column>
        <el-table-column label="资产数量" width="120">
          <template #default="{ row }">
            {{ row.completed_assets }}/{{ row.total_assets }}
          </template>
        </el-table-column>
        <el-table-column label="进度" width="180">
          <template #default="{ row }">
            <el-progress 
              :percentage="row.completion_rate" 
              :status="getProgressStatus(row.completion_rate)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="status_label" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">
              {{ row.status_label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="assignee.name" label="负责人" width="120" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click.stop="handleViewTask(row)">详情</el-button>
            
            <template v-if="row.status === 'draft'">
              <el-button size="small" type="primary" @click.stop="handleEditTask(row)">
                编辑
              </el-button>
              <el-button size="small" @click.stop="handleStartTask(row)">
                开始
              </el-button>
            </template>
            
            <template v-if="row.status === 'in_progress'">
              <el-button size="small" type="success" @click.stop="handleCompleteTask(row)">
                完成
              </el-button>
              <el-button size="small" @click.stop="handleTaskRecords(row)">
                盘点
              </el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <!-- 任务分页 -->
      <div class="pagination">
        <el-pagination
          v-model:current-page="taskPagination.current"
          v-model:page-size="taskPagination.size"
          :page-sizes="[10, 20, 50, 100]"
          :total="taskPagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleTaskSizeChange"
          @current-change="handleTaskCurrentChange"
        />
      </div>
    </div>

    <!-- 今日待办 -->
    <div class="todays-tasks" v-if="todaysTasks.length > 0">
      <div class="section-header">
        <h3>今日待办</h3>
        <el-button type="primary" text @click="loadTodaysTasks">刷新</el-button>
      </div>
      
      <el-row :gutter="20">
        <el-col :span="8" v-for="task in todaysTasks" :key="task.id">
          <el-card class="task-card">
            <template #header>
              <div class="task-card-header">
                <span>{{ task.task_name }}</span>
                <el-tag :type="getStatusTagType(task.status)" size="small">
                  {{ task.status_label }}
                </el-tag>
              </div>
            </template>
            
            <div class="task-card-content">
              <div class="task-info">
                <div><el-icon><Calendar /></el-icon> {{ task.start_date }} ~ {{ task.end_date }}</div>
                <div><el-icon><User /></el-icon> {{ task.assignee?.name || '未分配' }}</div>
                <div><el-icon><DataBoard /></el-icon> {{ task.completed_assets }}/{{ task.total_assets }} 资产</div>
              </div>
              
              <el-progress 
                :percentage="task.completion_rate" 
                :status="getProgressStatus(task.completion_rate)"
              />
              
              <div class="task-actions">
                <el-button size="small" type="primary" @click="handleTaskRecords(task)">
                  开始盘点
                </el-button>
                <el-button size="small" @click="handleViewTask(task)">
                  查看详情
                </el-button>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <!-- 盘点记录标签页 -->
    <div class="tabs-section">
      <el-tabs v-model="activeTab" @tab-click="handleTabChange">
        <el-tab-pane label="盘点记录" name="records">
          <el-table
            :data="recordList"
            v-loading="loadingRecords"
            stripe
            style="width: 100%"
            @row-click="handleRecordClick"
          >
            <el-table-column prop="inventory_number" label="盘点编号" width="140" />
            <el-table-column label="资产信息" min-width="180">
              <template #default="{ row }">
                <div>
                  <div>{{ row.asset?.asset_tag }} - {{ row.asset?.name }}</div>
                  <div style="font-size: 12px; color: #666;">{{ row.asset?.brand }} {{ row.asset?.model }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="inventory_type_label" label="盘点类型" width="100" />
            <el-table-column prop="inventory_date" label="盘点日期" width="120" />
            <el-table-column prop="physical_status_label" label="实物状态" width="100">
              <template #default="{ row }">
                <el-tag :type="getPhysicalStatusTagType(row.physical_status)" size="small">
                  {{ row.physical_status_label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status_match_label" label="状态匹配" width="120">
              <template #default="{ row }">
                <el-tag :type="getMatchStatusTagType(row.status_match)" size="small">
                  {{ row.status_match_label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="condition_description" label="状况" width="80" />
            <el-table-column prop="review_status_label" label="审核状态" width="100">
              <template #default="{ row }">
                <el-tag :type="getReviewStatusTagType(row.review_status)" size="small">
                  {{ row.review_status_label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="user.name" label="盘点员" width="120" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="handleViewRecord(row)">详情</el-button>
                <el-button 
                  v-if="row.review_status === 'pending' && canReview" 
                  size="small" 
                  type="success"
                  @click.stop="handleReviewRecord(row)"
                >
                  审核
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <!-- 记录分页 -->
          <div class="pagination">
            <el-pagination
              v-model:current-page="recordPagination.current"
              v-model:page-size="recordPagination.size"
              :page-sizes="[10, 20, 50, 100]"
              :total="recordPagination.total"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleRecordSizeChange"
              @current-change="handleRecordCurrentChange"
            />
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="待审核" name="pending_reviews">
          <div v-if="pendingReviews.length === 0" class="empty-state">
            <el-empty description="暂无待审核记录" />
          </div>
          
          <div v-else class="review-list">
            <el-card v-for="record in pendingReviews" :key="record.id" class="review-card">
              <template #header>
                <div class="review-header">
                  <div class="review-title">
                    <span>{{ record.asset?.asset_tag }} - {{ record.asset?.name }}</span>
                    <el-tag :type="getPhysicalStatusTagType(record.physical_status)" size="small">
                      {{ record.physical_status_label }}
                    </el-tag>
                  </div>
                  <div class="review-meta">
                    <span>{{ record.inventory_number }}</span>
                    <span>盘点员: {{ record.user?.name }}</span>
                    <span>{{ record.inventory_date }}</span>
                  </div>
                </div>
              </template>
              
              <div class="review-content">
                <div class="review-info">
                  <div>状态匹配: <el-tag :type="getMatchStatusTagType(record.status_match)" size="small">
                    {{ record.status_match_label }}
                  </el-tag></div>
                  <div>资产状况: {{ record.condition_description }}</div>
                  <div v-if="record.has_issues" style="color: #f56c6c;">
                    <el-icon><Warning /></el-icon> 异常: {{ record.issue_description }}
                  </div>
                </div>
                
                <div class="review-actions">
                  <el-button type="success" size="small" @click="handleApproveReview(record)">
                    <el-icon><Check /></el-icon> 通过
                  </el-button>
                  <el-button type="danger" size="small" @click="handleRejectReview(record)">
                    <el-icon><Close /></el-icon> 拒绝
                  </el-button>
                  <el-button size="small" @click="handleViewRecord(record)">查看详情</el-button>
                </div>
              </div>
            </el-card>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="异常记录" name="issue_records">
          <div v-if="issueRecords.length === 0" class="empty-state">
            <el-empty description="暂无异常记录" />
          </div>
          
          <div v-else class="issue-list">
            <el-card v-for="record in issueRecords" :key="record.id" class="issue-card">
              <div class="issue-card-header">
                <div class="issue-title">
                  <span>{{ record.asset?.asset_tag }} - {{ record.asset?.name }}</span>
                  <el-tag type="danger" size="small">异常</el-tag>
                </div>
                <div class="issue-meta">
                  <span>{{ record.inventory_number }}</span>
                  <span>{{ record.inventory_date }}</span>
                  <span>盘点员: {{ record.user?.name }}</span>
                </div>
              </div>
              
              <div class="issue-content">
                <div class="issue-details">
                  <div><strong>问题描述:</strong> {{ record.issue_description }}</div>
                  <div><strong>实物状态:</strong> {{ record.physical_status_label }}</div>
                  <div><strong>状态匹配:</strong> {{ record.status_match_label }}</div>
                  <div v-if="record.damage_description"><strong>损坏描述:</strong> {{ record.damage_description }}</div>
                  <div v-if="record.estimated_repair_cost"><strong>预估维修成本:</strong> {{ record.estimated_repair_cost | currency }}</div>
                </div>
                
                <div class="issue-actions">
                  <el-button type="warning" size="small" @click="handleFollowUpIssue(record)">
                    <el-icon><Link /></el-icon> 跟进
                  </el-button>
                  <el-button size="small" @click="handleViewRecord(record)">查看详情</el-button>
                </div>
              </div>
            </el-card>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 新建/编辑任务对话框 -->
    <el-dialog
      v-model="taskDialogVisible"
      :title="taskDialogTitle"
      width="900px"
      @close="handleTaskDialogClose"
    >
      <InventoryTaskForm
        v-if="taskDialogVisible"
        :task-data="currentTask"
        :mode="taskDialogMode"
        @submit="handleTaskFormSubmit"
        @cancel="taskDialogVisible = false"
      />
    </el-dialog>

    <!-- 任务详情对话框 -->
    <el-dialog
      v-model="taskDetailVisible"
      title="盘点任务详情"
      width="1000px"
    >
      <InventoryTaskDetail
        v-if="taskDetailVisible"
        :task="currentTask"
        @close="taskDetailVisible = false"
      />
    </el-dialog>

    <!-- 盘点记录对话框 -->
    <el-dialog
      v-model="recordDialogVisible"
      title="盘点记录"
      width="900px"
    >
      <InventoryRecordForm
        v-if="recordDialogVisible"
        :task-id="currentTaskId"
        @submit="handleRecordFormSubmit"
        @cancel="recordDialogVisible = false"
      />
    </el-dialog>

    <!-- 记录详情对话框 -->
    <el-dialog
      v-model="recordDetailVisible"
      title="盘点记录详情"
      width="1000px"
    >
      <InventoryRecordDetail
        v-if="recordDetailVisible"
        :record="currentRecord"
        @close="recordDetailVisible = false"
      />
    </el-dialog>

    <!-- 审核对话框 -->
    <el-dialog
      v-model="reviewDialogVisible"
      title="审核盘点记录"
      width="600px"
    >
      <ReviewForm
        v-if="reviewDialogVisible"
        :record="currentRecord"
        @approve="handleReviewSubmit"
        @cancel="reviewDialogVisible = false"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Plus, DocumentChecked, Camera, Download, Search, Filter,
  Warning, Clock, Calendar, User, DataBoard, Check, Close, Link
} from '@element-plus/icons-vue'

import InventoryTaskForm from './components/InventoryTaskForm.vue'
import InventoryTaskDetail from './components/InventoryTaskDetail.vue'
import InventoryRecordForm from './components/InventoryRecordForm.vue'
import InventoryRecordDetail from './components/InventoryRecordDetail.vue'
import ReviewForm from './components/ReviewForm.vue'
import { inventoryApi } from '@/api/export'

// 响应式数据
const searchKeyword = ref('')
const showFilters = ref(false)
const loading = ref(false)
const loadingRecords = ref(false)
const taskDialogVisible = ref(false)
const taskDetailVisible = ref(false)
const recordDialogVisible = ref(false)
const recordDetailVisible = ref(false)
const reviewDialogVisible = ref(false)
const activeTab = ref('records')

const taskDialogMode = ref('create')
const taskDialogTitle = ref('')
const currentTask = ref(null)
const currentTaskId = ref(null)
const currentRecord = ref(null)

const users = ref([])
const taskList = ref([])
const recordList = ref([])
const pendingReviews = ref([])
const issueRecords = ref([])
const todaysTasks = ref([])

const filterForm = reactive({
  status: '',
  task_type: '',
  assigned_to: '',
  dateRange: []
})

const statistics = reactive({
  total: 0,
  active: 0,
  overdue: 0,
  pending_reviews: 0
})

const taskPagination = reactive({
  current: 1,
  size: 10,
  total: 0
})

const recordPagination = reactive({
  current: 1,
  size: 20,
  total: 0
})

// 计算属性
const taskDialogTitle = computed(() => {
  return taskDialogMode.value === 'create' ? '新建盘点任务' : '编辑盘点任务'
})

const canReview = computed(() => {
  // 这里可以根据用户角色判断审核权限
  return true // 临时返回true
})

// 生命周期
onMounted(() => {
  loadStatistics()
  loadTasks()
  loadRecords()
  loadPendingReviews()
  loadIssueRecords()
  loadTodaysTasks()
  loadUsers()
})

// 方法
const loadStatistics = async () => {
  try {
    // TODO: 调用统计API
    // 暂时使用模拟数据
    statistics.total = 15
    statistics.active = 3
    statistics.overdue = 2
    statistics.pending_reviews = 5
  } catch (error) {
    console.error('加载统计失败:', error)
  }
}

const loadTasks = async () => {
  try {
    loading.value = true
    
    const params = {
      page: taskPagination.current,
      per_page: taskPagination.size,
      search: searchKeyword.value
    }
    
    if (filterForm.status) params.status = filterForm.status
    if (filterForm.task_type) params.task_type = filterForm.task_type
    if (filterForm.assigned_to) params.assigned_to = filterForm.assigned_to
    if (filterForm.dateRange?.length === 2) {
      params.start_date = filterForm.dateRange[0]
      params.end_date = filterForm.dateRange[1]
    }
    
    const response = await inventoryApi.getTasks(params)
    
    if (response.success) {
      taskList.value = response.data.data
      taskPagination.total = response.data.total
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('加载任务列表失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

const loadRecords = async () => {
  try {
    loadingRecords.value = true
    
    const params = {
      page: recordPagination.current,
      per_page: recordPagination.size
    }
    
    const response = await inventoryApi.getRecords(params)
    
    if (response.success) {
      recordList.value = response.data.data
      recordPagination.total = response.data.total
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('加载盘点记录失败')
    console.error(error)
  } finally {
    loadingRecords.value = false
  }
}

const loadPendingReviews = async () => {
  try {
    const response = await inventoryApi.getPendingReviews()
    
    if (response.success) {
      pendingReviews.value = response.data.data
    }
  } catch (error) {
    console.error('加载待审核记录失败:', error)
  }
}

const loadIssueRecords = async () => {
  try {
    const response = await inventoryApi.getIssueRecords()
    
    if (response.success) {
      issueRecords.value = response.data.data
    }
  } catch (error) {
    console.error('加载异常记录失败:', error)
  }
}

const loadTodaysTasks = async () => {
  try {
    const response = await inventoryApi.getTodaysTasks()
    
    if (response.success) {
      todaysTasks.value = response.data.data
    }
  } catch (error) {
    console.error('加载今日待办失败:', error)
  }
}

const loadUsers = async () => {
  try {
    // TODO: 调用用户列表API
    // 暂时使用模拟数据
    users.value = [
      { id: 1, name: '张三' },
      { id: 2, name: '李四' },
      { id: 3, name: '王五' }
    ]
  } catch (error) {
    console.error('加载用户列表失败:', error)
  }
}

const handleSearch = () => {
  taskPagination.current = 1
  loadTasks()
}

const handleFilter = () => {
  taskPagination.current = 1
  loadTasks()
}

const handleReset = () => {
  Object.keys(filterForm).forEach(key => {
    filterForm[key] = Array.isArray(filterForm[key]) ? [] : ''
  })
  searchKeyword.value = ''
  taskPagination.current = 1
  loadTasks()
}

const handleTaskSizeChange = (size) => {
  taskPagination.size = size
  taskPagination.current = 1
  loadTasks()
}

const handleTaskCurrentChange = (page) => {
  taskPagination.current = page
  loadTasks()
}

const handleRecordSizeChange = (size) => {
  recordPagination.size = size
  recordPagination.current = 1
  loadRecords()
}

const handleRecordCurrentChange = (page) => {
  recordPagination.current = page
  loadRecords()
}

const handleTabChange = (tab) => {
  if (tab.paneName === 'pending_reviews') {
    loadPendingReviews()
  } else if (tab.paneName === 'issue_records') {
    loadIssueRecords()
  }
}

const handleCreateTask = () => {
  taskDialogMode.value = 'create'
  currentTask.value = null
  taskDialogVisible.value = true
}

const handleEditTask = (task) => {
  taskDialogMode.value = 'edit'
  currentTask.value = { ...task }
  taskDialogVisible.value = true
}

const handleViewTask = (task) => {
  currentTask.value = task
  taskDetailVisible.value = true
}

const handleTaskClick = (task) => {
  // 可以在这里实现点击行跳转到详情
  console.log('点击任务:', task)
}

const handleStartTask = async (task) => {
  try {
    const response = await inventoryApi.startTask(task.id)
    
    if (response.success) {
      ElMessage.success('任务开始成功')
      loadTasks()
      loadTodaysTasks()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('开始任务失败')
    console.error(error)
  }
}

const handleCompleteTask = async (task) => {
  try {
    await ElMessageBox.confirm('确定要完成此盘点任务吗？', '确认完成', {
      type: 'warning'
    })
    
    const response = await inventoryApi.completeTask(task.id)
    
    if (response.success) {
      ElMessage.success('任务完成成功')
      loadTasks()
      loadTodaysTasks()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('完成任务失败')
    }
  }
}

const handleTaskRecords = (task) => {
  currentTaskId.value = task.id
  recordDialogVisible.value = true
}

const handleQuickInventory = () => {
  currentTaskId.value = null
  recordDialogVisible.value = true
}

const handleScanQR = () => {
  ElMessage.info('二维码扫描功能正在开发中')
  // TODO: 实现二维码扫描功能
}

const handleExport = async () => {
  try {
    const response = await inventoryApi.export()
    
    if (response.success) {
      ElMessage.success('导出数据准备完成')
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('导出失败')
    console.error(error)
  }
}

const handleTaskFormSubmit = async (formData) => {
  try {
    let response
    
    if (taskDialogMode.value === 'create') {
      response = await inventoryApi.createTask(formData)
    } else {
      response = await inventoryApi.updateTask(currentTask.value.id, formData)
    }
    
    if (response.success) {
      ElMessage.success(taskDialogMode.value === 'create' ? '创建成功' : '更新成功')
      taskDialogVisible.value = false
      loadTasks()
      loadStatistics()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('操作失败')
    console.error(error)
  }
}

const handleTaskDialogClose = () => {
  currentTask.value = null
}

const handleRecordFormSubmit = async (formData) => {
  try {
    const response = await inventoryApi.createRecord(formData)
    
    if (response.success) {
      ElMessage.success('盘点记录创建成功')
      recordDialogVisible.value = false
      loadRecords()
      loadPendingReviews()
      loadIssueRecords()
      
      // 如果有关联任务，重新加载任务进度
      if (currentTaskId.value) {
        loadTasks()
      }
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('创建盘点记录失败')
    console.error(error)
  }
}

const handleViewRecord = (record) => {
  currentRecord.value = record
  recordDetailVisible.value = true
}

const handleRecordClick = (record) => {
  console.log('点击记录:', record)
}

const handleReviewRecord = (record) => {
  currentRecord.value = record
  reviewDialogVisible.value = true
}

const handleApproveReview = (record) => {
  currentRecord.value = record
  reviewDialogVisible.value = true
}

const handleRejectReview = (record) => {
  currentRecord.value = record
  reviewDialogVisible.value = true
}

const handleFollowUpIssue = (record) => {
  ElMessage.info('跟进功能正在开发中')
  // TODO: 实现跟进功能
}

const handleReviewSubmit = async (reviewData) => {
  try {
    const response = await inventoryApi.reviewRecord(currentRecord.value.id, reviewData)
    
    if (response.success) {
      ElMessage.success('审核成功')
      reviewDialogVisible.value = false
      loadRecords()
      loadPendingReviews()
    } else {
      ElMessage.error(response.message)
    }
  } catch (error) {
    ElMessage.error('审核失败')
    console.error(error)
  }
}

// 状态标签类型
const getStatusTagType = (status) => {
  const types = {
    draft: 'info',
    active: 'primary',
    in_progress: 'success',
    paused: 'warning',
    completed: 'success',
    cancelled: 'danger'
  }
  return types[status] || 'info'
}

const getPhysicalStatusTagType = (status) => {
  const types = {
    found: 'success',
    not_found: 'danger',
    damaged: 'warning',
    scrapped: 'info',
    transferred: 'primary'
  }
  return types[status] || 'info'
}

const getMatchStatusTagType = (status) => {
  const types = {
    matched: 'success',
    location_mismatch: 'warning',
    user_mismatch: 'warning',
    both_mismatch: 'danger'
  }
  return types[status] || 'info'
}

const getReviewStatusTagType = (status) => {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getProgressStatus = (percentage) => {
  if (percentage >= 100) return 'success'
  if (percentage >= 70) return 'primary'
  if (percentage >= 30) return 'warning'
  return 'exception'
}

// 过滤器
const currency = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}
</script>

<style scoped>
.inventory-management {
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

.tasks-section {
  background: white;
  border-radius: 4px;
  padding: 20px;
  margin-bottom: 20px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.section-header h3 {
  margin: 0;
  font-size: 18px;
  color: #333;
}

.pagination {
  padding: 20px 0;
  text-align: right;
}

.todays-tasks {
  background: white;
  border-radius: 4px;
  padding: 20px;
  margin-bottom: 20px;
}

.task-card {
  margin-bottom: 20px;
  height: 100%;
}

.task-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.task-card-content {
  padding: 10px 0;
}

.task-info {
  margin-bottom: 15px;
  font-size: 14px;
}

.task-info div {
  margin-bottom: 5px;
  color: #666;
}

.task-info .el-icon {
  margin-right: 5px;
  color: #409eff;
}

.task-actions {
  margin-top: 15px;
  text-align: center;
}

.tabs-section {
  background: white;
  border-radius: 4px;
  padding: 20px;
}

.empty-state {
  padding: 40px;
  text-align: center;
  color: #999;
}

.review-list, .issue-list {
  display: grid;
  gap: 15px;
}

.review-card, .issue-card {
  margin-bottom: 15px;
}

.review-header, .issue-card-header {
  padding: 0 0 10px;
}

.review-title, .issue-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 5px;
  font-weight: bold;
}

.review-meta, .issue-meta {
  display: flex;
  gap: 15px;
  font-size: 12px;
  color: #666;
}

.review-content, .issue-content {
  padding: 10px 0;
}

.review-info, .issue-details {
  margin-bottom: 15px;
  font-size: 14px;
}

.review-info div, .issue-details div {
  margin-bottom: 5px;
}

.review-actions, .issue-actions {
  text-align: right;
}
</style>