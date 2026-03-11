<template>
  <div class="departments-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>部门管理</span>
          <el-button type="primary" @click="handleAdd">添加部门</el-button>
        </div>
      </template>

      <el-table :data="tableData" row-key="id" :tree-props="{ children: 'children', hasChildren: 'hasChildren' }" v-loading="loading">
        <el-table-column prop="name" label="部门名称" min-width="200" />
        <el-table-column prop="code" label="部门编码" width="120" />
        <el-table-column prop="manager.name" label="负责人" width="120">
          <template #default="{ row }">
            {{ row.manager ? row.manager.name : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column prop="is_active" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="部门名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入部门名称" />
        </el-form-item>
        <el-form-item label="部门编码" prop="code">
          <el-input v-model="form.code" placeholder="请输入部门编码" />
        </el-form-item>
        <el-form-item label="父部门" prop="parent_id">
          <el-tree-select
            v-model="form.parent_id"
            :data="departmentTree"
            :props="{ label: 'name', value: 'id' }"
            placeholder="请选择父部门"
            clearable
            check-strictly
          />
        </el-form-item>
        <el-form-item label="负责人" prop="manager_id">
          <el-select v-model="form.manager_id" placeholder="请选择负责人" clearable filterable>
            <el-option
              v-for="user in users"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
        <el-form-item label="地址" prop="location">
          <el-input v-model="form.location" placeholder="请输入地址" />
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="form.phone" placeholder="请输入电话" />
        </el-form-item>
        <el-form-item label="状态" prop="is_active">
          <el-switch v-model="form.is_active" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请输入描述" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { getDepartmentTree, createDepartment, updateDepartment, deleteDepartment } from '@/api/department'
import { ElMessage, ElMessageBox } from 'element-plus'

const loading = ref(false)
const dialogVisible = ref(false)
const dialogTitle = computed(() => isEdit.value ? '编辑部门' : '添加部门')
const isEdit = ref(false)
const formRef = ref(null)

const tableData = ref([])
const departmentTree = ref([])
const users = ref([])

const form = reactive({
  name: '',
  code: '',
  parent_id: null,
  manager_id: null,
  sort: 0,
  location: '',
  phone: '',
  is_active: true,
  description: ''
})

const rules = {
  name: [{ required: true, message: '请输入部门名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入部门编码', trigger: 'blur' }]
}

const loadDepartments = async () => {
  loading.value = true
  try {
    const response = await getDepartmentTree()
    departmentTree.value = response.data
    tableData.value = JSON.parse(JSON.stringify(response.data))
  } catch (error) {
    console.error('Failed to load departments:', error)
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(form, {
    name: '',
    code: '',
    parent_id: null,
    manager_id: null,
    sort: 0,
    location: '',
    phone: '',
    is_active: true,
    description: ''
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(form, {
    id: row.id,
    name: row.name,
    code: row.code,
    parent_id: row.parent_id,
    manager_id: row.manager_id,
    sort: row.sort,
    location: row.location,
    phone: row.phone,
    is_active: row.is_active,
    description: row.description
  })
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该部门吗?', '提示', {
      type: 'warning'
    })

    await deleteDepartment(row.id)
    ElMessage.success('删除成功')
    await loadDepartments()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Failed to delete department:', error)
    }
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isEdit.value) {
          await updateDepartment(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createDepartment(form)
          ElMessage.success('创建成功')
        }
        dialogVisible.value = false
        await loadDepartments()
      } catch (error) {
        console.error('Failed to submit:', error)
      }
    }
  })
}

const handleDialogClose = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
}

onMounted(() => {
  loadDepartments()
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
