<template>
  <div class="dashboard">
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background-color: #409eff;">
              <el-icon :size="24" color="#fff"><Document /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.total }}</div>
              <div class="stat-label">总资产数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background-color: #67c23a;">
              <el-icon :size="24" color="#fff"><CircleCheck /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.assigned }}</div>
              <div class="stat-label">已分配</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background-color: #e6a23c;">
              <el-icon :size="24" color="#fff"><Box /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.ready }}</div>
              <div class="stat-label">在库</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background-color: #f56c6c;">
              <el-icon :size="24" color="#fff"><Warning /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.maintenance + stats.broken + stats.lost }}</div>
              <div class="stat-label">需处理</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card>
          <template #header>
            <span>资产状态分布</span>
          </template>
          <div ref="chartRef1" style="width: 100%; height: 300px;"></div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card>
          <template #header>
            <span>资产价值统计</span>
          </template>
          <div class="value-stat">
            <div class="value-item">
              <span class="label">总资产价值:</span>
              <span class="value">¥{{ formatNumber(stats.total_value) }}</span>
            </div>
            <div class="value-item">
              <span class="label">平均资产价值:</span>
              <span class="value">¥{{ formatNumber(averageValue) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import * as echarts from 'echarts'
import { getAssetStatistics } from '@/api/asset'

const stats = ref({
  total: 0,
  ready: 0,
  assigned: 0,
  maintenance: 0,
  broken: 0,
  lost: 0,
  scrapped: 0,
  total_value: 0
})

const averageValue = computed(() => {
  return stats.value.total > 0 ? (stats.value.total_value / stats.value.total).toFixed(2) : '0.00'
})

const formatNumber = (num) => {
  return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const chartRef1 = ref(null)

const loadStatistics = async () => {
  try {
    const response = await getAssetStatistics()
    stats.value = response.data
  } catch (error) {
    console.error('Failed to load statistics:', error)
  }
}

const initChart = () => {
  const chart = echarts.init(chartRef1.value)
  const option = {
    tooltip: {
      trigger: 'item'
    },
    legend: {
      orient: 'vertical',
      left: 'left'
    },
    series: [
      {
        name: '资产状态',
        type: 'pie',
        radius: '50%',
        data: [
          { value: stats.value.ready, name: '在库' },
          { value: stats.value.assigned, name: '已分配' },
          { value: stats.value.maintenance, name: '维修中' },
          { value: stats.value.broken, name: '已损坏' },
          { value: stats.value.lost, name: '已丢失' },
          { value: stats.value.scrapped, name: '已报废' }
        ],
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowOffsetX: 0,
            shadowColor: 'rgba(0, 0, 0, 0.5)'
          }
        }
      }
    ]
  }
  chart.setOption(option)

  window.addEventListener('resize', () => {
    chart.resize()
  })
}

onMounted(async () => {
  await loadStatistics()
  initChart()
})
</script>

<style scoped>
.dashboard {
  padding: 20px;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  cursor: pointer;
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-content {
  display: flex;
  align-items: center;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-top: 5px;
}

.value-stat {
  padding: 20px;
}

.value-item {
  display: flex;
  justify-content: space-between;
  padding: 15px 0;
  border-bottom: 1px solid #f0f0f0;
}

.value-item:last-child {
  border-bottom: none;
}

.value-item .label {
  color: #606266;
  font-size: 16px;
}

.value-item .value {
  color: #409eff;
  font-size: 20px;
  font-weight: bold;
}
</style>
