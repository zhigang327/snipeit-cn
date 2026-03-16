<template>
  <div class="users-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>用户管理</span>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon> 添加用户
          </el-button>
        </div>
      </template>

      <!-- 搜索表单 -->
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.search"
            placeholder="姓名/邮箱/工号/职位"
            clearable
            style="width: 200px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="部门">
          <el-select v-model="searchForm.department_id" placeholder="请选择" clearable style="width: 150px">
            <el-option
              v-for="dept in departments"
              :key="dept.id"
              :label="dept.name"
              :value="dept.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.is_active" placeholder="请选择" clearable style="width: 110px">
            <el-option label="正常" :value="true" />
            <el-option label="禁用" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 用户列表 -->
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="姓名" min-width="120">
          <template #default="{ row }">
            <div class="user-name-cell">
              <el-avatar :size="28" :src="row.avatar" :icon="UserFilled" />
              <span style="margin-left: 8px">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="email" label="邮箱" min-width="180" />
        <el-table-column prop="employee_id" label="工号" width="100">
          <template #default="{ row }">{{ row.employee_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="position" label="职位" width="120">
          <template #default="{ row }">{{ row.position || '-' }}</template>
        </el-table-column>
        <el-table-column label="部门" width="130">
          <template #default="{ row }">{{ row.department?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="phone" label="手机" width="130">
          <template #default="{ row }">{{ row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
              {{ row.is_active ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="入职日期" width="110">
          <template #default="{ row }">
            {{ row.hire_date ? row.hire_date.slice(0, 10) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="warning" link @click="handleResetPwd(row)">改密</el-button>
            <el-button
              type="danger"
              link
              :disabled="row.id === currentUserId"
              @click="handleDelete(row)"
            >删除</el-button>
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
        style="margin-top: 16px; justify-content: flex-end"
        @size-change="loadUsers"
        @current-change="loadUsers"
      />
    </el-card>

    <!-- 新增/编辑 Dialog -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑用户' : '添加用户'"
      width="560px"
      @close="resetForm"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="form.name" placeholder="请输入姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="form.email" placeholder="请输入邮箱" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="密码" prop="password">
              <el-input
                v-model="form.password"
                type="password"
                :placeholder="isEdit ? '留空表示不修改' : '请输入密码'"
                show-password
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机" prop="phone">
              <el-input v-model="form.phone" placeholder="请输入手机号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="工号" prop="employee_id">
              <el-input v-model="form.employee_id" placeholder="请输入工号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="职位" prop="position">
              <el-input v-model="form.position" placeholder="请输入职位" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="部门" prop="department_id">
              <el-select v-model="form.department_id" placeholder="请选择部门" clearable style="width: 100%">
                <el-option
                  v-for="dept in departments"
                  :key="dept.id"
                  :label="dept.name"
                  :value="dept.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="入职日期" prop="hire_date">
              <el-date-picker
                v-model="form.hire_date"
                type="date"
                placeholder="请选择"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="状态" prop="is_active">
              <el-switch
                v-model="form.is_active"
                active-text="正常"
                inactive-text="禁用"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注" prop="notes">
          <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 修改密码 Dialog -->
    <el-dialog v-model="pwdDialogVisible" title="修改密码" width="400px" @close="resetPwdForm">
      <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="90px">
        <el-form-item label="新密码" prop="new_password">
          <el-input v-model="pwdForm.new_password" type="password" placeholder="请输入新密码（至少6位）" show-password />
        </el-form-item>
        <el-form-item label="确认密码" prop="confirm_password">
          <el-input v-model="pwdForm.confirm_password" type="password" placeholder="请再次输入新密码" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pwdDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="pwdSubmitting" @click="handlePwdSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, UserFilled } from '@element-plus/icons-vue'
import userApi from '@/api/user'
import request from '@/api/index'

// ── 状态 ──────────────────────────────────────────────────────────────
const loading = ref(false)
const submitting = ref(false)
const tableData = ref([])
const departments = ref([])
const currentUserId = ref(null)

const pagination = reactive({ page: 1, per_page: 20, total: 0 })
const searchForm = reactive({ search: '', department_id: null, is_active: null })

// ── 加载 ──────────────────────────────────────────────────────────────
const loadUsers = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, per_page: pagination.per_page }
    if (searchForm.search) params.search = searchForm.search
    if (searchForm.department_id != null) params.department_id = searchForm.department_id
    if (searchForm.is_active != null) params.is_active = searchForm.is_active

    const res = await userApi.list(params)
    // 后端直接返回：{ success, data:[], total, current_page, per_page, last_page }
    tableData.value = res.data || []
    pagination.total = res.total || 0
  } catch (e) {
    ElMessage.error('加载用户列表失败')
  } finally {
    loading.value = false
  }
}

const loadDepartments = async () => {
  try {
    const res = await request({ url: '/departments', method: 'get', params: { per_page: 200 } })
    // 兼容分页和非分页两种结构
    departments.value = Array.isArray(res.data) ? res.data : (res.data?.data || [])
  } catch (e) {
    // 部门加载失败不影响主功能
  }
}

const loadCurrentUser = async () => {
  try {
    const res = await userApi.list({ per_page: 1 })
    // 从 profile 接口获取当前用户 id
    const profileRes = await request({ url: '/users/profile', method: 'get' })
    currentUserId.value = profileRes.data?.id || null
  } catch (e) {}
}

// ── 搜索/重置 ─────────────────────────────────────────────────────────
const handleSearch = () => {
  pagination.page = 1
  loadUsers()
}

const handleReset = () => {
  searchForm.search = ''
  searchForm.department_id = null
  searchForm.is_active = null
  pagination.page = 1
  loadUsers()
}

// ── 新增/编辑 Dialog ──────────────────────────────────────────────────
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const editId = ref(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  phone: '',
  employee_id: '',
  position: '',
  department_id: null,
  hire_date: null,
  is_active: true,
  notes: '',
})

const rules = {
  name:     [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  email:    [{ required: true, message: '请输入邮箱', trigger: 'blur' }, { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  password: [{ validator: (rule, val, cb) => {
    if (!isEdit.value && !val) return cb(new Error('请输入密码'))
    if (val && val.length < 6) return cb(new Error('密码至少6位'))
    cb()
  }, trigger: 'blur' }],
}

const handleAdd = () => {
  isEdit.value = false
  editId.value = null
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  editId.value = row.id
  Object.assign(form, {
    name: row.name || '',
    email: row.email || '',
    password: '',
    phone: row.phone || '',
    employee_id: row.employee_id || '',
    position: row.position || '',
    department_id: row.department_id || null,
    hire_date: row.hire_date || null,
    is_active: row.is_active !== false,
    notes: row.notes || '',
  })
  dialogVisible.value = true
}

const resetForm = () => {
  Object.assign(form, {
    name: '', email: '', password: '', phone: '',
    employee_id: '', position: '', department_id: null,
    hire_date: null, is_active: true, notes: '',
  })
  formRef.value?.clearValidate()
}

const handleSubmit = async () => {
  await formRef.value?.validate()
  submitting.value = true
  try {
    const payload = { ...form }
    if (isEdit.value && !payload.password) delete payload.password
    if (!payload.phone) payload.phone = null
    if (!payload.employee_id) payload.employee_id = null
    if (!payload.position) payload.position = null
    if (!payload.notes) payload.notes = null

    if (isEdit.value) {
      await userApi.update(editId.value, payload)
      ElMessage.success('用户更新成功')
    } else {
      await userApi.create(payload)
      ElMessage.success('用户创建成功')
    }
    dialogVisible.value = false
    loadUsers()
  } catch (e) {
    const msg = e.response?.data?.message || (isEdit.value ? '更新失败' : '创建失败')
    ElMessage.error(msg)
  } finally {
    submitting.value = false
  }
}

// ── 删除 ──────────────────────────────────────────────────────────────
const handleDelete = async (row) => {
  await ElMessageBox.confirm(`确定要删除用户「${row.name}」吗？`, '删除确认', {
    confirmButtonText: '删除',
    cancelButtonText: '取消',
    type: 'warning',
  })
  try {
    await userApi.delete(row.id)
    ElMessage.success('用户已删除')
    loadUsers()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '删除失败')
  }
}

// ── 修改密码 Dialog ───────────────────────────────────────────────────
const pwdDialogVisible = ref(false)
const pwdSubmitting = ref(false)
const pwdFormRef = ref(null)
const pwdTargetId = ref(null)

const pwdForm = reactive({ new_password: '', confirm_password: '' })

const pwdRules = {
  new_password:     [{ required: true, message: '请输入新密码', trigger: 'blur' }, { min: 6, message: '至少6位', trigger: 'blur' }],
  confirm_password: [{ validator: (rule, val, cb) => {
    if (!val) return cb(new Error('请再次输入密码'))
    if (val !== pwdForm.new_password) return cb(new Error('两次密码不一致'))
    cb()
  }, trigger: 'blur' }],
}

const handleResetPwd = (row) => {
  pwdTargetId.value = row.id
  pwdForm.new_password = ''
  pwdForm.confirm_password = ''
  pwdDialogVisible.value = true
}

const resetPwdForm = () => {
  pwdFormRef.value?.clearValidate()
}

const handlePwdSubmit = async () => {
  await pwdFormRef.value?.validate()
  pwdSubmitting.value = true
  try {
    await userApi.update(pwdTargetId.value, { password: pwdForm.new_password })
    ElMessage.success('密码修改成功')
    pwdDialogVisible.value = false
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '修改失败')
  } finally {
    pwdSubmitting.value = false
  }
}

// ── 初始化 ────────────────────────────────────────────────────────────
onMounted(() => {
  loadUsers()
  loadDepartments()
  loadCurrentUser()
})
</script>

<style scoped>
.users-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-form {
  margin-bottom: 16px;
}

.user-name-cell {
  display: flex;
  align-items: center;
}
</style>
