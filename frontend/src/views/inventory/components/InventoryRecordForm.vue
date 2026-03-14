<template>
  <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
    <el-form-item label="资产编号" prop="asset_id">
      <el-input v-model="form.asset_tag" placeholder="请输入资产编号或扫描二维码" />
    </el-form-item>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="盘点类型" prop="inventory_type">
          <el-select v-model="form.inventory_type" placeholder="请选择盘点类型" style="width:100%">
            <el-option label="定期盘点" value="periodic" />
            <el-option label="抽查盘点" value="spot" />
            <el-option label="专项盘点" value="special" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="盘点日期" prop="inventory_date">
          <el-date-picker v-model="form.inventory_date" type="date" value-format="YYYY-MM-DD"
            placeholder="选择盘点日期" style="width:100%" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-form-item label="实物状态" prop="physical_status">
      <el-select v-model="form.physical_status" placeholder="请选择实物状态" style="width:100%">
        <el-option label="已找到" value="found" />
        <el-option label="未找到" value="not_found" />
        <el-option label="已损坏" value="damaged" />
        <el-option label="已报废" value="scrapped" />
        <el-option label="已转移" value="transferred" />
      </el-select>
    </el-form-item>

    <el-form-item label="状况描述">
      <el-input v-model="form.condition_description" type="textarea" :rows="2" placeholder="请描述资产状况" />
    </el-form-item>

    <el-form-item label="是否有问题">
      <el-switch v-model="form.has_issues" />
    </el-form-item>

    <el-form-item v-if="form.has_issues" label="问题描述">
      <el-input v-model="form.issue_description" type="textarea" :rows="2" placeholder="请描述发现的问题" />
    </el-form-item>

    <el-form-item label="备注">
      <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="请输入备注" />
    </el-form-item>

    <el-form-item>
      <el-button type="primary" @click="handleSubmit">提交盘点</el-button>
      <el-button @click="$emit('cancel')">取消</el-button>
    </el-form-item>
  </el-form>
</template>

<script setup>
import { ref, reactive } from 'vue'

const props = defineProps({ taskId: { type: [Number, String], default: null } })
const emit = defineEmits(['submit', 'cancel'])

const formRef = ref(null)
const form = reactive({
  task_id: props.taskId,
  asset_tag: '',
  inventory_type: 'periodic',
  inventory_date: new Date().toISOString().slice(0, 10),
  physical_status: 'found',
  condition_description: '',
  has_issues: false,
  issue_description: '',
  notes: ''
})

const rules = {
  asset_tag: [{ required: true, message: '请输入资产编号', trigger: 'blur' }],
  inventory_type: [{ required: true, message: '请选择盘点类型', trigger: 'change' }],
  inventory_date: [{ required: true, message: '请选择盘点日期', trigger: 'change' }],
  physical_status: [{ required: true, message: '请选择实物状态', trigger: 'change' }]
}

const handleSubmit = async () => {
  await formRef.value.validate()
  emit('submit', { ...form })
}
</script>
