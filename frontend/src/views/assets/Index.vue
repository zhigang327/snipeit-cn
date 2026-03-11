<template>
  <div class="assets-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>资产管理</span>
          <el-button type="primary" @click="handleAdd">添加资产</el-button>
        </div>
      </template>

      <!-- 搜索表单 -->
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.search" placeholder="资产名称/标签/序列号" clearable @keyup.enter="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择" clearable>
            <el-option label="在库" value="ready" />
            <el-option label="已分配" value="assigned" />
            <el-option label="维修中" value="maintenance" />
            <el-option label="已损坏" value="broken" />
            <el-option label="已丢失" value="lost" />
            <el-option label="已报废" value="scrapped" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 资产列表 -->
      <el-table :data="tableData" v-loading="loading" border>
        <el-table-column prop="asset_tag" label="资产标签" width="120" />
        <el-table-column prop="name" label="资产名称" min-width="150" />
        <el-table-column prop="category.name" label="分类" width="120" />
        <el-table-column prop="brand" label="品牌" width="100" />
        <el-table-column prop="model" label="型号" width="120" />
        <el-table-column prop="user.name" label="使用人" width="100">
          <template #default="{ row }">
            {{ row.user ? row.user.name : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="department.name" label="部门" width="120">
          <template #default="{ row }">
            {{ row.department ? row.department.name : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="purchase_price" label="采购价格" width="100">
          <template #default="{ row }">
            ¥{{ row.purchase_price.toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleView(row)">查看</el-button>
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button v-if="row.status === 'ready'" type="success" link @click="handleCheckout(row)">分配</el-button>
            <el-button v-if="row.status === 'assigned'" type="warning" link @click="handleCheckin(row)">归还</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.size"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadAssets"
        @current-change="loadAssets"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>

    <!-- 资产表单对话框 -->
    <el-dialog
      v-model="formDialogVisible"
      :title="formDialogTitle"
      width="800px"
      @close="handleFormDialogClose"
    >
      <el-form :model="assetForm" :rules="assetRules" ref="assetFormRef" label-width="120px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="资产标签" prop="asset_tag">
              <el-input v-model="assetForm.asset_tag" placeholder="例如: AST-0001" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="资产名称" prop="name">
              <el-input v-model="assetForm.name" placeholder="请输入资产名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="分类" prop="category_id">
              <el-select v-model="assetForm.category_id" placeholder="请选择分类">
                <el-option label="电脑" :value="1" />
                <el-option label="显示器" :value="2" />
                <el-option label="打印机" :value="3" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="供应商">
              <el-select v-model="assetForm.supplier_id" placeholder="请选择供应商" clearable>
                <el-option label="供应商A" :value="1" />
                <el-option label="供应商B" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="品牌">
              <el-input v-model="assetForm.brand" placeholder="例如: Dell" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="型号">
              <el-input v-model="assetForm.model" placeholder="例如: Latitude 5420" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="序列号">
              <el-input v-model="assetForm.serial_number" placeholder="SN序列号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="采购价格">
              <el-input-number v-model="assetForm.purchase_price" :min="0" :precision="2" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="采购日期">
              <el-date-picker v-model="assetForm.purchase_date" type="date" placeholder="选择日期" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="保修期(月)">
              <el-input-number v-model="assetForm.warranty_months" :min="0" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="存放位置">
          <el-input v-model="assetForm.location" placeholder="例如: A楼3层305室" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="assetForm.notes" type="textarea" :rows="3" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 分配对话框 -->
    <el-dialog v-model="checkoutDialogVisible" title="分配资产" width="600px">
      <el-form :model="checkoutForm" :rules="checkoutRules" ref="checkoutFormRef" label-width="100px">
        <el-form-item label="使用人" prop="user_id">
          <el-select v-model="checkoutForm.user_id" placeholder="请选择使用人" filterable>
            <el-option label="张三" :value="1" />
            <el-option label="李四" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="部门" prop="department_id">
          <el-tree-select
            v-model="checkoutForm.department_id"
            :data="departmentTree"
            :props="{ label: 'name', value: 'id' }"
            placeholder="请选择部门"
            clearable
          />
        </el-form-item>
        <el-form-item label="预计归还">
          <el-date-picker v-model="checkoutForm.expected_checkin_date" type="date" placeholder="选择日期" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="checkoutForm.notes" type="textarea" :rows="3" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="checkoutDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCheckoutSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getAssets, createAsset, updateAsset, deleteAsset, checkoutAsset, checkinAsset } from '@/api/asset'
import { getDepartmentTree } from '@/api/department'
import { ElMessage, ElMessageBox } from 'element-plus'

const loading = ref(false)
const formDialogVisible = ref(false)
const checkoutDialogVisible = ref(false)
const assetFormRef = ref(null)
const checkoutFormRef = ref(null)

const tableData = ref([])
const departmentTree = ref([])
const currentAsset = ref(null)

const searchForm = reactive({
  search: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  size: 20,
  total: 0
})

const isEdit = ref(false)
const formDialogTitle = computed(() => isEdit.value ? '编辑资产' : '添加资产')

const assetForm = reactive({
  asset_tag: '',
  name: '',
  category_id: null,
  supplier_id: null,
  purchase_price: 0,
  purchase_date: '',
  brand: '',
  model: '',
  serial_number: '',
  warranty_months: 12,
  location: '',
  notes: ''
})

const assetRules = {
  asset_tag: [{ required: true, message: '请输入资产标签', trigger: 'blur' }],
  name: [{ required: true, message: '请输入资产名称', trigger: 'blur' }],
  category_id: [{ required: true, message: '请选择分类', trigger: 'change' }]
}

const checkoutForm = reactive({
  user_id: null,
  department_id: null,
  expected_checkin_date: '',
  notes: ''
})

const checkoutRules = {
  user_id: [{ required: true, message: '请选择使用人', trigger: 'change' }]
}

const getStatusType = (status) => {
  const map = {
    ready: 'success',
    assigned: 'primary',
    maintenance: 'warning',
    broken: 'danger',
    lost: 'info',
    scrapped: 'info'
  }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = {
    ready: '在库',
    assigned: '已分配',
    maintenance: '维修中',
    broken: '已损坏',
    lost: '已丢失',
    scrapped: '已报废'
  }
  return map[status] || status
}

const loadAssets = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      per_page: pagination.size,
      ...searchForm
    }
    const response = await getAssets(params)
    tableData.value = response.data.data
    pagination.total = response.data.total
  } catch (error) {
    console.error('Failed to load assets:', error)
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadAssets()
}

const handleReset = () => {
  Object.assign(searchForm, {
    search: '',
    status: ''
  })
  pagination.page = 1
  loadAssets()
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(assetForm, {
    asset_tag: '',
    name: '',
    category_id: null,
    supplier_id: null,
    purchase_price: 0,
    purchase_date: '',
    brand: '',
    model: '',
    serial_number: '',
    warranty_months: 12,
    location: '',
    notes: ''
  })
  formDialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(assetForm, {
    id: row.id,
    asset_tag: row.asset_tag,
    name: row.name,
    category_id: row.category_id,
    supplier_id: row.supplier_id,
    purchase_price: row.purchase_price,
    purchase_date: row.purchase_date,
    brand: row.brand,
    model: row.model,
    serial_number: row.serial_number,
    warranty_months: row.warranty_months,
    location: row.location,
    notes: row.notes
  })
  formDialogVisible.value = true
}

const handleView = (row) => {
  // 查看详情
  console.log('View asset:', row)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该资产吗?', '提示', {
      type: 'warning'
    })

    await deleteAsset(row.id)
    ElMessage.success('删除成功')
    await loadAssets()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Failed to delete asset:', error)
    }
  }
}

const handleCheckout = async (row) => {
  currentAsset.value = row
  Object.assign(checkoutForm, {
    user_id: null,
    department_id: null,
    expected_checkin_date: '',
    notes: ''
  })
  checkoutDialogVisible.value = true
}

const handleCheckoutSubmit = async () => {
  if (!checkoutFormRef.value) return

  await checkoutFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await checkoutAsset(currentAsset.value.id, checkoutForm)
        ElMessage.success('分配成功')
        checkoutDialogVisible.value = false
        await loadAssets()
      } catch (error) {
        console.error('Failed to checkout asset:', error)
      }
    }
  })
}

const handleCheckin = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要归还资产 ${row.name} 吗?`, '提示', {
      type: 'warning'
    })

    await checkinAsset(row.id, { notes: '归还资产' })
    ElMessage.success('归还成功')
    await loadAssets()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Failed to checkin asset:', error)
    }
  }
}

const handleSubmit = async () => {
  if (!assetFormRef.value) return

  await assetFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isEdit.value) {
          await updateAsset(assetForm.id, assetForm)
          ElMessage.success('更新成功')
        } else {
          await createAsset(assetForm)
          ElMessage.success('创建成功')
        }
        formDialogVisible.value = false
        await loadAssets()
      } catch (error) {
        console.error('Failed to submit:', error)
      }
    }
  })
}

const handleFormDialogClose = () => {
  if (assetFormRef.value) {
    assetFormRef.value.resetFields()
  }
}

onMounted(async () => {
  await loadAssets()
  try {
    const response = await getDepartmentTree()
    departmentTree.value = response.data
  } catch (error) {
    console.error('Failed to load department tree:', error)
  }
})
</script>

<style scoped>
.assets-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-form {
  margin-bottom: 20px;
}
</style>
