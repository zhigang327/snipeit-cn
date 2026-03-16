<template>
  <div class="departments-page">

    <!-- ── 公司管理 ── -->
    <el-card style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header">
          <span>
            <el-icon style="vertical-align: -2px; margin-right: 4px;"><OfficeBuilding /></el-icon>
            公司管理
          </span>
          <el-button type="primary" @click="handleAddCompany">
            <el-icon><Plus /></el-icon> 添加公司
          </el-button>
        </div>
      </template>

      <el-table :data="companies" v-loading="loading" border stripe>
        <el-table-column prop="name" label="公司名称" min-width="180" />
        <el-table-column prop="code" label="编码" width="120" />
        <el-table-column label="负责人" width="120">
          <template #default="{ row }">{{ row.manager?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="phone" label="电话" width="140">
          <template #default="{ row }">{{ row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column prop="location" label="地址" min-width="160">
          <template #default="{ row }">{{ row.location || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row, true)">编辑</el-button>
            <el-button type="success" link @click="handleAddSubDept(row)">添加部门</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && companies.length === 0" description="暂无公司，请先添加" :image-size="60" />
    </el-card>

    <!-- ── 部门树 ── -->
    <el-card>
      <template #header>
        <div class="card-header">
          <span>
            <el-icon style="vertical-align: -2px; margin-right: 4px;"><Folder /></el-icon>
            部门结构
          </span>
          <el-button type="primary" :disabled="companies.length === 0" @click="handleAddDept">
            <el-icon><Plus /></el-icon> 添加部门
          </el-button>
        </div>
      </template>

      <el-table
        :data="treeData"
        row-key="id"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        v-loading="loading"
        border
        default-expand-all
      >
        <el-table-column prop="name" label="部门名称" min-width="200" />
        <el-table-column prop="code" label="编码" width="120" />
        <el-table-column label="负责人" width="120">
          <template #default="{ row }">{{ row.manager?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="上级" width="140">
          <template #default="{ row }">{{ row.parent?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="70" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row, false)">编辑</el-button>
            <el-button type="success" link @click="handleAddSubDept(row)">添加子部门</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && treeData.length === 0" description="暂无部门数据" :image-size="60" />
    </el-card>

    <!-- ── 新增/编辑 Dialog ── -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="580px"
      @close="handleDialogClose"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-width="90px">
        <el-form-item :label="isCompany ? '公司名称' : '部门名称'" prop="name">
          <el-input v-model="form.name" :placeholder="isCompany ? '请输入公司名称' : '请输入部门名称'" />
        </el-form-item>
        <el-form-item label="编码" prop="code">
          <el-input v-model="form.code" placeholder="唯一编码，如 HQ / TECH-01" />
        </el-form-item>

        <!-- 部门才显示父级选择 -->
        <el-form-item v-if="!isCompany" label="父级" prop="parent_id">
          <el-tree-select
            v-model="form.parent_id"
            :data="allNodes"
            :props="{ label: 'name', value: 'id' }"
            placeholder="请选择上级（公司或部门）"
            clearable
            check-strictly
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="负责人" prop="manager_id">
          <el-select v-model="form.manager_id" placeholder="请选择负责人" clearable filterable style="width: 100%">
            <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="电话" prop="phone">
              <el-input v-model="form.phone" placeholder="联系电话" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序" prop="sort">
              <el-input-number v-model="form.sort" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="地址" prop="location">
          <el-input v-model="form.location" placeholder="请输入地址" />
        </el-form-item>
        <el-form-item label="状态" prop="is_active">
          <el-switch v-model="form.is_active" active-text="启用" inactive-text="禁用" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="2" placeholder="备注描述" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { OfficeBuilding, Plus, Folder } from '@element-plus/icons-vue'
import { getDepartments, getDepartmentTree, createDepartment, updateDepartment, deleteDepartment } from '@/api/department'
import request from '@/api/index'

// ── 状态 ──────────────────────────────────────────────────────────
const loading   = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const isEdit    = ref(false)
const isCompany = ref(false)   // true = 操作的是公司（顶层），false = 部门
const formRef   = ref(null)

const companies = ref([])   // parent_id = null 的顶层
const treeData  = ref([])   // 完整树（tree 接口返回）
const allNodes  = ref([])   // 所有节点（用于父级下拉，扁平化树）
const users     = ref([])

const form = reactive({
  id: null,
  name: '',
  code: '',
  parent_id: null,
  manager_id: null,
  sort: 0,
  location: '',
  phone: '',
  is_active: true,
  description: '',
})

const rules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入编码', trigger: 'blur' }],
}

const dialogTitle = computed(() => {
  if (isCompany.value) return isEdit.value ? '编辑公司' : '添加公司'
  return isEdit.value ? '编辑部门' : '添加部门'
})

// ── 数据加载 ──────────────────────────────────────────────────────
const flattenTree = (nodes, result = []) => {
  for (const n of nodes) {
    result.push({ id: n.id, name: n.name, children: n.children })
    if (n.children?.length) flattenTree(n.children, result)
  }
  return result
}

const loadAll = async () => {
  loading.value = true
  try {
    // 1. 顶层公司列表（直接用 list 接口，过滤 parent_id=null）
    const listRes = await getDepartments({ per_page: 200 })
    const all = listRes.data?.data || listRes.data || []
    companies.value = all.filter(d => !d.parent_id)

    // 2. 完整树（用于部门树展示）
    const treeRes = await getDepartmentTree()
    const tree = treeRes.data || []
    // tree 接口返回的是包含公司的全树，把公司的子节点单独提取出来展示在"部门"区域
    treeData.value = tree.flatMap(company => company.children || [])
    // 所有节点（公司 + 部门）用于父级下拉
    allNodes.value = flattenTree(tree)
  } catch (e) {
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  try {
    const res = await request({ url: '/users', method: 'get', params: { per_page: 200 } })
    users.value = res.data || []
  } catch (e) {}
}

// ── 新增公司 ──────────────────────────────────────────────────────
const handleAddCompany = () => {
  isEdit.value = false
  isCompany.value = true
  resetForm()
  dialogVisible.value = true
}

// ── 新增部门（手动点按钮，不预设父级）──────────────────────────────
const handleAddDept = () => {
  isEdit.value = false
  isCompany.value = false
  resetForm()
  dialogVisible.value = true
}

// ── 在某个节点下新增子部门 ────────────────────────────────────────
const handleAddSubDept = (row) => {
  isEdit.value = false
  isCompany.value = false
  resetForm()
  form.parent_id = row.id
  dialogVisible.value = true
}

// ── 编辑 ──────────────────────────────────────────────────────────
const handleEdit = (row, asCompany) => {
  isEdit.value = true
  isCompany.value = asCompany
  Object.assign(form, {
    id:          row.id,
    name:        row.name        || '',
    code:        row.code        || '',
    parent_id:   row.parent_id   || null,
    manager_id:  row.manager_id  || null,
    sort:        row.sort        ?? 0,
    location:    row.location    || '',
    phone:       row.phone       || '',
    is_active:   row.is_active   !== false,
    description: row.description || '',
  })
  dialogVisible.value = true
}

// ── 删除 ──────────────────────────────────────────────────────────
const handleDelete = async (row) => {
  await ElMessageBox.confirm(
    `确定删除「${row.name}」吗？若其下有子部门或用户则无法删除。`,
    '删除确认',
    { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' }
  )
  try {
    await deleteDepartment(row.id)
    ElMessage.success('删除成功')
    loadAll()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '删除失败')
  }
}

// ── 提交 ──────────────────────────────────────────────────────────
const handleSubmit = async () => {
  await formRef.value?.validate()
  submitting.value = true
  try {
    const payload = { ...form }
    if (isCompany.value) payload.parent_id = null
    if (!payload.phone)       payload.phone       = null
    if (!payload.location)    payload.location    = null
    if (!payload.description) payload.description = null

    if (isEdit.value) {
      await updateDepartment(payload.id, payload)
      ElMessage.success('更新成功')
    } else {
      await createDepartment(payload)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadAll()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// ── 工具 ──────────────────────────────────────────────────────────
const resetForm = () => {
  Object.assign(form, {
    id: null, name: '', code: '', parent_id: null, manager_id: null,
    sort: 0, location: '', phone: '', is_active: true, description: '',
  })
}

const handleDialogClose = () => {
  formRef.value?.resetFields()
}

onMounted(() => {
  loadAll()
  loadUsers()
})
</script>

<style scoped>
.departments-page {
  padding: 20px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
