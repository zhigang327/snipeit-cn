<template>
  <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="任务名称" prop="task_name">
          <el-input v-model="form.task_name" placeholder="请输入任务名称" />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="任务类型" prop="task_type">
          <el-select v-model="form.task_type" placeholder="请选择任务类型" style="width:100%">
            <el-option label="定期盘点" value="periodic" />
            <el-option label="随机抽查" value="random" />
            <el-option label="全面盘点" value="full" />
            <el-option label="现场抽查" value="spot" />
            <el-option label="循环盘点" value="cycle" />
          </el-select>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="开始日期" prop="start_date">
          <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD"
            placeholder="选择开始日期" style="width:100%" />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="结束日期" prop="end_date">
          <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD"
            placeholder="选择结束日期" style="width:100%" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-form-item label="任务描述">
      <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请输入任务描述" />
    </el-form-item>

    <el-form-item>
      <el-button type="primary" @click="handleSubmit">确认</el-button>
      <el-button @click="$emit('cancel')">取消</el-button>
    </el-form-item>
  </el-form>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'

const props = defineProps({
  taskData: { type: Object, default: null },
  mode: { type: String, default: 'create' }
})
const emit = defineEmits(['submit', 'cancel'])

const formRef = ref(null)
const form = reactive({
  task_name: '',
  task_type: '',
  start_date: '',
  end_date: '',
  description: ''
})

const rules = {
  task_name: [{ required: true, message: '请输入任务名称', trigger: 'blur' }],
  task_type: [{ required: true, message: '请选择任务类型', trigger: 'change' }],
  start_date: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  end_date: [{ required: true, message: '请选择结束日期', trigger: 'change' }]
}

watch(() => props.taskData, (val) => {
  if (val) Object.assign(form, val)
}, { immediate: true })

const handleSubmit = async () => {
  await formRef.value.validate()
  emit('submit', { ...form })
}
</script>
