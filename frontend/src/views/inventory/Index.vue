<template>
  <div class="inventory-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>资产盘点</span>
          <el-button type="primary" @click="handleStartInventory">开始盘点</el-button>
        </div>
      </template>

      <!-- 盘点列表 -->
      <el-table :data="inventoryList" v-loading="loading">
        <el-table-column prop="name" label="盘点名称" width="200" />
        <el-table-column prop="department.name" label="部门" width="150">
          <template #default="{ row }">
            {{ row.department ? row.department.name : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="completed_at" label="完成时间" width="180">
          <template #default="{ row }">
            {{ row.completed_at ? formatDate(row.completed_at) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="进度" width="120">
          <template #default="{ row }">
            <el-progress :percentage="getProgress(row)" :color="getProgressColor(row)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'in_progress'" type="primary" link @click="handleScan(row)">扫码盘点</el-button>
            <el-button type="primary" link @click="handleView(row)">查看详情</el-button>
            <el-button v-if="row.status === 'in_progress'" type="success" link @click="handleComplete(row)">完成盘点</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 开始盘点对话框 -->
    <el-dialog v-model="startDialogVisible" title="开始盘点" width="600px">
      <el-form :model="startForm" :rules="startRules" ref="startFormRef" label-width="100px">
        <el-form-item label="盘点名称" prop="name">
          <el-input v-model="startForm.name" placeholder="例如: 2024年第一季度盘点" />
        </el-form-item>
        <el-form-item label="盘点部门" prop="department_id">
          <el-tree-select
            v-model="startForm.department_id"
            :data="departmentTree"
            :props="{ label: 'name', value: 'id' }"
            placeholder="选择盘点部门"
            clearable
          />
        </el-form-item>
        <el-form-item label="备注" prop="description">
          <el-input v-model="startForm.description" type="textarea" :rows="3" placeholder="盘点说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="startDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleStartSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 扫码盘点对话框 -->
    <el-dialog v-model="scanDialogVisible" title="扫码盘点" width="800px" fullscreen>
      <div class="scan-container">
        <!-- 扫码区域 -->
        <div class="scan-area">
          <el-input
            v-model="scanCode"
            placeholder="扫描资产二维码或输入资产标签"
            size="large"
            @keyup.enter="handleScanSubmit"
            ref="scanInputRef"
            autofocus
          >
            <template #append>
              <el-button :icon="Camera" @click="handleOpenCamera">打开摄像头</el-button>
            </template>
          </el-input>

          <!-- 摄像头区域 -->
          <div v-if="cameraOpen" class="camera-area">
            <video ref="videoRef" autoplay playsinline></video>
            <canvas ref="canvasRef" style="display: none;"></canvas>
            <el-button type="danger" @click="handleCloseCamera" style="margin-top: 10px;">关闭摄像头</el-button>
          </div>
        </div>

        <!-- 盘点进度 -->
        <div class="scan-progress">
          <el-card>
            <template #header>
              <div class="progress-header">
                <span>盘点进度</span>
                <el-button size="small" @click="loadProgress">刷新</el-button>
              </div>
            </template>
            <el-row :gutter="20">
              <el-col :span="6">
                <div class="stat-item">
                  <div class="stat-value">{{ progress.total }}</div>
                  <div class="stat-label">已扫描</div>
                </div>
              </el-col>
              <el-col :span="6">
                <div class="stat-item">
                  <div class="stat-value" style="color: #67c23a;">{{ progress.found }}</div>
                  <div class="stat-label">正常</div>
                </div>
              </el-col>
              <el-col :span="6">
                <div class="stat-item">
                  <div class="stat-value" style="color: #f56c6c;">{{ progress.lost + progress.damaged }}</div>
                  <div class="stat-label">异常</div>
                </div>
              </el-col>
              <el-col :span="6">
                <div class="stat-item">
                  <div class="stat-value">{{ progress.progress }}%</div>
                  <div class="stat-label">进度</div>
                </div>
              </el-col>
            </el-row>
          </el-card>
        </div>

        <!-- 最近扫描记录 -->
        <div class="scan-history">
          <el-card>
            <template #header>
              <span>最近扫描记录</span>
            </template>
            <el-table :data="scannedAssets" max-height="300">
              <el-table-column prop="asset_tag" label="资产标签" width="120" />
              <el-table-column prop="asset.name" label="资产名称" min-width="150" />
              <el-table-column prop="actual_location" label="实际位置" width="150" />
              <el-table-column prop="status" label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="getScanStatusType(row.status)">
                    {{ getScanStatusText(row.status) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="scanned_at" label="扫描时间" width="180">
                <template #default="{ row }">
                  {{ formatDate(row.scanned_at) }}
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
      </div>
    </el-dialog>

    <!-- 扫描结果对话框 -->
    <el-dialog v-model="resultDialogVisible" title="扫描结果" width="600px">
      <el-descriptions v-if="scannedAsset" :column="2" border>
        <el-descriptions-item label="资产标签">{{ scannedAsset.asset_tag }}</el-descriptions-item>
        <el-descriptions-item label="资产名称">{{ scannedAsset.name }}</el-descriptions-item>
        <el-descriptions-item label="分类">{{ scannedAsset.category?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="使用人">{{ scannedAsset.user?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="预期位置">{{ expectedLocation }}</el-descriptions-item>
        <el-descriptions-item label="当前状态">
          <el-tag>{{ getStatusText(scannedAsset.status) }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <el-form :model="scanForm" :rules="scanRules" ref="scanFormRef" label-width="100px" style="margin-top: 20px;">
        <el-form-item label="盘点状态" prop="status">
          <el-radio-group v-model="scanForm.status">
            <el-radio label="found">正常</el-radio>
            <el-radio label="not_found">未找到</el-radio>
            <el-radio label="lost">丢失</el-radio>
            <el-radio label="damaged">损坏</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="实际位置" prop="location">
          <el-input v-model="scanForm.location" placeholder="输入实际位置" />
        </el-form-item>
        <el-form-item label="备注" prop="notes">
          <el-input v-model="scanForm.notes" type="textarea" :rows="3" placeholder="备注信息" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="resultDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmScan">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { Camera } from '@element-plus/icons-vue'
import { getInventories, startInventory, scanAsset, getInventoryProgress, completeInventory } from '@/api/inventory'
import { getDepartmentTree } from '@/api/department'
import { scanAsset as scanAssetInfo } from '@/api/asset'
import { ElMessage, ElMessageBox } from 'element-plus'
import dayjs from 'dayjs'

const loading = ref(false)
const inventoryList = ref([])
const departmentTree = ref([])
const currentInventory = ref(null)

const startDialogVisible = ref(false)
const scanDialogVisible = ref(false)
const resultDialogVisible = ref(false)

const startFormRef = ref(null)
const scanFormRef = ref(null)
const scanInputRef = ref(null)

const videoRef = ref(null)
const canvasRef = ref(null)
const cameraOpen = ref(false)
const scanCode = ref('')
const scannedAsset = ref(null)
const expectedLocation = ref('')

const startForm = reactive({
  name: '',
  department_id: null,
  description: ''
})

const scanForm = reactive({
  status: 'found',
  location: '',
  notes: ''
})

const progress = ref({
  total: 0,
  found: 0,
  lost: 0,
  damaged: 0,
  not_found: 0,
  progress: 0
})

const scannedAssets = ref([])

const startRules = {
  name: [{ required: true, message: '请输入盘点名称', trigger: 'blur' }]
}

const scanRules = {
  status: [{ required: true, message: '请选择盘点状态', trigger: 'change' }]
}

const getStatusType = (status) => {
  const map = {
    pending: 'info',
    in_progress: 'warning',
    completed: 'success'
  }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = {
    pending: '未开始',
    in_progress: '进行中',
    completed: '已完成'
  }
  return map[status] || status
}

const getScanStatusType = (status) => {
  const map = {
    found: 'success',
    not_found: 'warning',
    lost: 'danger',
    damaged: 'danger'
  }
  return map[status] || 'info'
}

const getScanStatusText = (status) => {
  const map = {
    found: '正常',
    not_found: '未找到',
    lost: '丢失',
    damaged: '损坏'
  }
  return map[status] || status
}

const getProgress = (inventory) => {
  if (!inventory.items || inventory.items.length === 0) return 0
  const found = inventory.items.filter(item => item.status === 'found').length
  return Math.round((found / inventory.items.length) * 100)
}

const getProgressColor = (inventory) => {
  const progress = getProgress(inventory)
  if (progress === 100) return '#67c23a'
  if (progress >= 50) return '#409eff'
  return '#e6a23c'
}

const formatDate = (date) => {
  return dayjs(date).format('YYYY-MM-DD HH:mm:ss')
}

const loadInventories = async () => {
  loading.value = true
  try {
    const response = await getInventories()
    inventoryList.value = response.data.data
  } catch (error) {
    console.error('Failed to load inventories:', error)
  } finally {
    loading.value = false
  }
}

const handleStartInventory = () => {
  Object.assign(startForm, {
    name: '',
    department_id: null,
    description: ''
  })
  startDialogVisible.value = true
}

const handleStartSubmit = async () => {
  if (!startFormRef.value) return

  await startFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await startInventory(startForm)
        ElMessage.success('盘点创建成功')
        startDialogVisible.value = false
        await loadInventories()
      } catch (error) {
        console.error('Failed to start inventory:', error)
      }
    }
  })
}

const handleScan = (inventory) => {
  currentInventory.value = inventory
  scanCode.value = ''
  scannedAssets.value = []
  scanDialogVisible.value = true
  loadProgress()

  nextTick(() => {
    if (scanInputRef.value) {
      scanInputRef.value.focus()
    }
  })
}

const handleScanSubmit = async () => {
  if (!scanCode.value) {
    ElMessage.warning('请扫描或输入资产标签')
    return
  }

  try {
    // 获取资产信息
    const response = await scanAssetInfo({ code: scanCode.value })
    if (response.success) {
      scannedAsset.value = response.data
      expectedLocation.value = response.data.location || '未设置'

      // 预填充实际位置
      scanForm.location = expectedLocation.value

      resultDialogVisible.value = true
    }
  } catch (error) {
    ElMessage.error('未找到该资产')
  }
}

const handleConfirmScan = async () => {
  if (!scanFormRef.value) return

  await scanFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await scanAsset(currentInventory.value.id, {
          asset_tag: scanCode.value,
          ...scanForm
        })

        ElMessage.success('资产盘点成功')

        // 添加到已扫描列表
        scannedAssets.value.unshift({
          asset: scannedAsset.value,
          asset_tag: scannedAsset.value.asset_tag,
          asset_name: scannedAsset.value.name,
          actual_location: scanForm.location,
          status: scanForm.status,
          scanned_at: new Date()
        })

        resultDialogVisible.value = false
        scanCode.value = ''
        await loadProgress()

        nextTick(() => {
          if (scanInputRef.value) {
            scanInputRef.value.focus()
          }
        })
      } catch (error) {
        console.error('Failed to scan asset:', error)
      }
    }
  })
}

const loadProgress = async () => {
  try {
    const response = await getInventoryProgress(currentInventory.value.id)
    progress.value = response.data
  } catch (error) {
    console.error('Failed to load progress:', error)
  }
}

const handleComplete = async (inventory) => {
  try {
    await ElMessageBox.confirm(`确定要完成盘点"${inventory.name}"吗?`, '提示', {
      type: 'warning'
    })

    await completeInventory(inventory.id)
    ElMessage.success('盘点已完成')
    await loadInventories()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Failed to complete inventory:', error)
    }
  }
}

const handleView = (inventory) => {
  // 查看盘点详情
  console.log('View inventory:', inventory)
}

// 摄像头扫码功能(简化版,实际需要集成二维码扫描库)
const handleOpenCamera = () => {
  cameraOpen.value = true
  ElMessage.info('摄像头扫码功能需要集成二维码扫描库')
}

const handleCloseCamera = () => {
  cameraOpen.value = false
  if (videoRef.value && videoRef.value.srcObject) {
    videoRef.value.srcObject.getTracks().forEach(track => track.stop())
  }
}

onMounted(async () => {
  await loadInventories()
  try {
    const response = await getDepartmentTree()
    departmentTree.value = response.data
  } catch (error) {
    console.error('Failed to load department tree:', error)
  }
})
</script>

<style scoped>
.inventory-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.scan-container {
  max-height: 80vh;
  overflow-y: auto;
}

.scan-area {
  margin-bottom: 20px;
}

.camera-area {
  margin-top: 20px;
  text-align: center;
}

.camera-area video {
  max-width: 100%;
  max-height: 400px;
}

.scan-progress {
  margin-bottom: 20px;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-item {
  text-align: center;
  padding: 10px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-top: 5px;
}
</style>
