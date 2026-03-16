<template>
  <div class="depreciation">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>资产折旧管理</span>
          <el-button type="primary" @click="showBatchDialog = true">
            批量折旧
          </el-button>
        </div>
      </template>

      <!-- 统计信息 -->
      <el-row :gutter="20" class="statistics-row">
        <el-col :span="6">
          <el-card class="stat-card">
            <div class="stat-content">
              <div class="stat-label">配置折旧的资产</div>
              <div class="stat-value">{{ statistics.total_assets }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card class="stat-card">
            <div class="stat-content">
              <div class="stat-label">折旧中</div>
              <div class="stat-value">{{ statistics.depreciating_assets }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card class="stat-card">
            <div class="stat-content">
              <div class="stat-label">已全额折旧</div>
              <div class="stat-value">{{ statistics.depreciated_assets }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card class="stat-card">
            <div class="stat-content">
              <div class="stat-label">累计折旧</div>
              <div class="stat-value">¥{{ formatMoney(statistics.total_accumulated_depreciation) }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 搜索和筛选 -->
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="资产名称">
          <el-input v-model="searchForm.search" placeholder="请输入资产名称或编号" clearable />
        </el-form-item>
        <el-form-item label="折旧方法">
          <el-select v-model="searchForm.depreciation_method" placeholder="请选择" clearable>
            <el-option label="直线法" value="straight_line" />
            <el-option label="双倍余额递减法" value="declining_balance" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadAssets">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 资产列表 -->
      <el-table :data="assets" v-loading="loading" border>
        <el-table-column prop="asset_tag" label="资产编号" width="120" />
        <el-table-column prop="name" label="资产名称" width="150" />
        <el-table-column label="折旧方法" width="150">
          <template #default="{ row }">
            <el-tag v-if="row.depreciation_method === 'straight_line'" type="success">直线法</el-tag>
            <el-tag v-else-if="row.depreciation_method === 'declining_balance'" type="warning">双倍余额递减法</el-tag>
            <el-tag v-else type="info">未配置</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="购买价格" width="120">
          <template #default="{ row }">
            ¥{{ formatMoney(row.purchase_price) }}
          </template>
        </el-table-column>
        <el-table-column label="累计折旧" width="120">
          <template #default="{ row }">
            ¥{{ formatMoney(row.accumulated_depreciation) }}
          </template>
        </el-table-column>
        <el-table-column label="当前账面价值" width="140">
          <template #default="{ row }">
            <span :class="{ 'warning-text': row.current_book_value <= row.salvage_value }">
              ¥{{ formatMoney(row.current_book_value) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="使用年限" width="100">
          <template #default="{ row }">
            {{ row.useful_life_years }}年
          </template>
        </el-table-column>
        <el-table-column label="上次折旧" width="120">
          <template #default="{ row }">
            {{ row.last_depreciation_date || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.depreciation_method"
              type="primary"
              size="small"
              @click="showDepreciationDetail(row)"
            >
              折旧详情
            </el-button>
            <el-button
              v-if="row.depreciation_method"
              type="success"
              size="small"
              @click="executeDepreciation(row)"
            >
              执行折旧
            </el-button>
            <el-button
              v-else
              type="warning"
              size="small"
              @click="configDepreciation(row)"
            >
              配置折旧
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadAssets"
        @current-change="loadAssets"
      />
    </el-card>

    <!-- 折旧详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="折旧详情" width="800px">
      <el-descriptions v-if="currentAsset" :column="2" border>
        <el-descriptions-item label="资产名称">{{ currentAsset.name }}</el-descriptions-item>
        <el-descriptions-item label="资产编号">{{ currentAsset.asset_tag }}</el-descriptions-item>
        <el-descriptions-item label="折旧方法">
          <el-tag v-if="currentAsset.depreciation_method === 'straight_line'" type="success">直线法</el-tag>
          <el-tag v-else type="warning">双倍余额递减法</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="购买价格">¥{{ formatMoney(currentAsset.purchase_price) }}</el-descriptions-item>
        <el-descriptions-item label="残值">¥{{ formatMoney(currentAsset.salvage_value) }}</el-descriptions-item>
        <el-descriptions-item label="使用年限">{{ currentAsset.useful_life_years }}年</el-descriptions-item>
        <el-descriptions-item label="累计折旧">¥{{ formatMoney(currentAsset.accumulated_depreciation) }}</el-descriptions-item>
        <el-descriptions-item label="当前账面价值">
          <span :class="{ 'warning-text': currentAsset.current_book_value <= currentAsset.salvage_value }">
            ¥{{ formatMoney(currentAsset.current_book_value) }}
          </span>
        </el-descriptions-item>
        <el-descriptions-item label="折旧进度" :span="2">
          <el-progress :percentage="depreciationProgress" :color="progressColor" />
        </el-descriptions-item>
      </el-descriptions>

      <el-divider>折旧预测表</el-divider>

      <el-table :data="schedule" border size="small" max-height="300">
        <el-table-column prop="year" label="年份" width="80" />
        <el-table-column label="本期折旧" width="120">
          <template #default="{ row }">
            ¥{{ formatMoney(row.depreciation_amount) }}
          </template>
        </el-table-column>
        <el-table-column label="累计折旧" width="120">
          <template #default="{ row }">
            ¥{{ formatMoney(row.accumulated_depreciation) }}
          </template>
        </el-table-column>
        <el-table-column label="账面价值">
          <template #default="{ row }">
            ¥{{ formatMoney(row.book_value) }}
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 配置折旧对话框 -->
    <el-dialog v-model="showConfigDialog" title="配置资产折旧" width="600px">
      <el-form :model="configForm" label-width="120px">
        <el-form-item label="折旧方法">
          <el-select v-model="configForm.depreciation_method" placeholder="请选择">
            <el-option label="直线法" value="straight_line">
              <div>
                <div>直线法</div>
                <div style="font-size: 12px; color: #909399">
                  年折旧额 = (原值 - 残值) / 使用年限
                </div>
              </div>
            </el-option>
            <el-option label="双倍余额递减法" value="declining_balance">
              <div>
                <div>双倍余额递减法</div>
                <div style="font-size: 12px; color: #909399">
                  折旧额 = 账面价值 × (2 / 使用年限)
                </div>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="残值">
          <el-input-number
            v-model="configForm.salvage_value"
            :min="0"
            :precision="2"
            :step="100"
            controls-position="right"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="使用年限">
          <el-input-number
            v-model="configForm.useful_life_years"
            :min="1"
            :max="50"
            :step="1"
            controls-position="right"
            style="width: 100%"
          />
          <span style="margin-left: 10px">年</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showConfigDialog = false">取消</el-button>
        <el-button type="primary" @click="saveDepreciationConfig">保存</el-button>
      </template>
    </el-dialog>

    <!-- 批量折旧对话框 -->
    <el-dialog v-model="showBatchDialog" title="批量执行折旧" width="600px">
      <el-alert
        type="warning"
        :closable="false"
        style="margin-bottom: 20px"
      >
        将对选中的资产执行折旧计算,请确认后操作
      </el-alert>
      <el-form :model="batchForm" label-width="120px">
        <el-form-item label="折旧日期">
          <el-date-picker
            v-model="batchForm.depreciation_date"
            type="date"
            placeholder="选择日期"
            style="width: 100%"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
        <el-form-item label="选择资产">
          <el-select
            v-model="batchForm.asset_ids"
            multiple
            filterable
            placeholder="请选择要折旧的资产"
            style="width: 100%"
          >
            <el-option
              v-for="asset in assets.filter(a => a.depreciation_method)"
              :key="asset.id"
              :label="`${asset.asset_tag} - ${asset.name}`"
              :value="asset.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchDialog = false">取消</el-button>
        <el-button type="primary" @click="executeBatchDepreciation" :loading="batchLoading">
          执行批量折旧
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import depreciationApi from '@/api/depreciation'
import assetApi from '@/api/asset'

const loading = ref(false)
const batchLoading = ref(false)
const assets = ref([])
const schedule = ref([])
const statistics = ref({})
const searchForm = ref({
  search: '',
  depreciation_method: '',
})
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0,
})

const showDetailDialog = ref(false)
const showConfigDialog = ref(false)
const showBatchDialog = ref(false)
const currentAsset = ref(null)
const configForm = ref({
  depreciation_method: 'straight_line',
  salvage_value: 0,
  useful_life_years: 5,
})
const batchForm = ref({
  depreciation_date: null,
  asset_ids: [],
})

const depreciationProgress = computed(() => {
  if (!currentAsset.value || !currentAsset.value.purchase_price) return 0
  const total = currentAsset.value.purchase_price - (currentAsset.value.salvage_value || 0)
  if (total <= 0) return 0
  return Math.round((currentAsset.value.accumulated_depreciation / total) * 100)
})

const progressColor = computed(() => {
  if (depreciationProgress.value >= 100) return '#909399'
  if (depreciationProgress.value >= 75) return '#E6A23C'
  if (depreciationProgress.value >= 50) return '#F56C6C'
  return '#67C23A'
})

const loadAssets = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
    }
    if (searchForm.value.search) params.search = searchForm.value.search
    if (searchForm.value.depreciation_method) params.depreciation_method = searchForm.value.depreciation_method
    const res = await assetApi.list(params)
    assets.value = res.data?.data || []
    pagination.value.total = res.data?.total || 0
  } catch (error) {
    ElMessage.error('加载资产列表失败')
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const res = await depreciationApi.getStatistics()
    statistics.value = res.data || {}
  } catch (error) {
    console.error('加载统计失败:', error)
  }
}

const showDepreciationDetail = async (asset) => {
  currentAsset.value = asset
  try {
    const res = await depreciationApi.getSchedule(asset.id)
    schedule.value = res.data.data
  } catch (error) {
    ElMessage.error('加载折旧预测失败')
  }
  showDetailDialog.value = true
}

const executeDepreciation = async (asset) => {
  try {
    await ElMessageBox.confirm('确认执行折旧操作?', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })

    await depreciationApi.execute(asset.id)
    ElMessage.success('折旧执行成功')
    loadAssets()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '折旧执行失败')
    }
  }
}

const configDepreciation = (asset) => {
  currentAsset.value = asset
  configForm.value = {
    depreciation_method: asset.depreciation_method || 'straight_line',
    salvage_value: asset.salvage_value || 0,
    useful_life_years: asset.useful_life_years || 5,
  }
  showConfigDialog.value = true
}

const saveDepreciationConfig = async () => {
  try {
    await assetApi.update(currentAsset.value.id, configForm.value)
    ElMessage.success('折旧配置保存成功')
    showConfigDialog.value = false
    loadAssets()
  } catch (error) {
    ElMessage.error('保存失败')
  }
}

const executeBatchDepreciation = async () => {
  if (batchForm.value.asset_ids.length === 0) {
    ElMessage.warning('请选择要折旧的资产')
    return
  }

  try {
    batchLoading.value = true
    const res = await depreciationApi.executeBatch(batchForm.value)
    ElMessage.success(res.data.message)
    showBatchDialog.value = false
    loadAssets()
    loadStatistics()
  } catch (error) {
    ElMessage.error('批量折旧失败')
  } finally {
    batchLoading.value = false
  }
}

const resetSearch = () => {
  searchForm.value = {
    search: '',
    depreciation_method: '',
  }
  pagination.value.page = 1
  loadAssets()
}

const formatMoney = (value) => {
  return Number(value).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

onMounted(() => {
  loadAssets()
  loadStatistics()
})
</script>

<style scoped>
.depreciation {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.statistics-row {
  margin-bottom: 20px;
}

.stat-card {
  text-align: center;
}

.stat-content {
  padding: 10px;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.search-form {
  margin-bottom: 20px;
}

.warning-text {
  color: #F56C6C;
  font-weight: bold;
}

:deep(.el-descriptions__label) {
  font-weight: bold;
}
</style>
