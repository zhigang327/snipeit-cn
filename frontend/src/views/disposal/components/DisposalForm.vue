<template>
  <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
    <el-form-item label="资产选择" prop="asset_id" required>
      <el-select
        v-model="form.asset_id"
        placeholder="请选择要报废的资产"
        filterable
        style="width: 100%"
        @change="handleAssetChange"
      >
        <el-option
          v-for="asset in availableAssets"
          :key="asset.id"
          :label="`${asset.asset_tag} - ${asset.name}`"
          :value="asset.id"
        >
          <span style="float: left">{{ asset.asset_tag }} - {{ asset.name }}</span>
          <span style="float: right; color: #8492a6; font-size: 13px">
            {{ asset.brand }} {{ asset.model }}
          </span>
        </el-option>
      </el-select>
    </el-form-item>

    <el-form-item label="报废类型" prop="disposal_type" required>
      <el-select v-model="form.disposal_type" placeholder="请选择报废类型" style="width: 100%">
        <el-option label="出售" value="sold" />
        <el-option label="报废" value="scrapped" />
        <el-option label="捐赠" value="donated" />
        <el-option label="调拨" value="transferred" />
        <el-option label="丢失" value="lost" />
      </el-select>
    </el-form-item>

    <el-form-item label="报废日期" prop="disposal_date" required>
      <el-date-picker
        v-model="form.disposal_date"
        type="date"
        placeholder="选择报废日期"
        style="width: 100%"
        value-format="YYYY-MM-DD"
      />
    </el-form-item>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="账面价值" prop="book_value">
          <el-input v-model="form.book_value" disabled>
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="报废金额" prop="disposal_amount">
          <el-input v-model="form.disposal_amount" type="number" min="0" step="0.01">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="残值" prop="salvage_value">
          <el-input v-model="form.salvage_value" type="number" min="0" step="0.01">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="处置损益" prop="gain_loss">
          <el-input v-model="form.gain_loss" disabled>
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-form-item label="报废原因" prop="reason" required>
      <el-input
        v-model="form.reason"
        type="textarea"
        :rows="3"
        placeholder="请详细说明报废原因"
        maxlength="500"
        show-word-limit
      />
    </el-form-item>

    <el-form-item label="详细描述" prop="description">
      <el-input
        v-model="form.description"
        type="textarea"
        :rows="3"
        placeholder="可选的详细描述信息"
        maxlength="1000"
        show-word-limit
      />
    </el-form-item>

    <el-form-item label="接收方信息" v-if="showRecipientInfo">
      <el-row :gutter="20">
        <el-col :span="12">
          <el-input v-model="form.recipient_name" placeholder="接收方名称" />
        </el-col>
        <el-col :span="12">
          <el-input v-model="form.recipient_contact" placeholder="联系方式" />
        </el-col>
      </el-row>
    </el-form-item>

    <el-form-item label="相关单据号" prop="document_number">
      <el-input v-model="form.document_number" placeholder="如发票号、审批单号等" />
    </el-form-item>

    <el-form-item label="最终去向" prop="final_location">
      <el-input v-model="form.final_location" placeholder="资产处置后的最终去向" />
    </el-form-item>

    <el-form-item label="交接说明" prop="handover_notes">
      <el-input
        v-model="form.handover_notes"
        type="textarea"
        :rows="2"
        placeholder="交接过程中的注意事项"
        maxlength="500"
        show-word-limit
      />
    </el-form-item>

    <el-form-item label="环境影响说明" prop="environmental_impact">
      <el-input
        v-model="form.environmental_impact"
        type="textarea"
        :rows="2"
        placeholder="处置过程对环境的影响说明"
        maxlength="500"
        show-word-limit
      />
    </el-form-item>

    <el-form-item>
      <el-button type="primary" @click="handleSubmit">提交</el-button>
      <el-button @click="$emit('cancel')">取消</el-button>
    </el-form-item>
  </el-form>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { assetApi } from '@/api/export'

const props = defineProps({
  formData: {
    type: Object,
    default: () => ({})
  },
  mode: {
    type: String,
    default: 'create'
  }
})

const emit = defineEmits(['submit', 'cancel'])

const formRef = ref()
const availableAssets = ref([])

const form = reactive({
  asset_id: '',
  disposal_type: '',
  disposal_date: '',
  disposal_amount: 0,
  salvage_value: 0,
  book_value: 0,
  gain_loss: 0,
  reason: '',
  description: '',
  recipient_name: '',
  recipient_contact: '',
  document_number: '',
  final_location: '',
  handover_notes: '',
  environmental_impact: ''
})

const rules = {
  asset_id: [
    { required: true, message: '请选择要报废的资产', trigger: 'change' }
  ],
  disposal_type: [
    { required: true, message: '请选择报废类型', trigger: 'change' }
  ],
  disposal_date: [
    { required: true, message: '请选择报废日期', trigger: 'change' }
  ],
  reason: [
    { required: true, message: '请输入报废原因', trigger: 'blur' },
    { min: 10, message: '报废原因至少10个字符', trigger: 'blur' }
  ]
}

// 计算属性
const showRecipientInfo = computed(() => {
  return ['sold', 'donated', 'transferred'].includes(form.disposal_type)
})

// 生命周期
onMounted(() => {
  loadAvailableAssets()
  
  if (props.mode === 'edit' && props.formData) {
    Object.keys(form).forEach(key => {
      if (props.formData[key] !== undefined) {
        form[key] = props.formData[key]
      }
    })
  } else {
    // 新建时设置默认日期为今天
    form.disposal_date = new Date().toISOString().split('T')[0]
  }
})

// 监听器
watch(() => form.disposal_amount, (newVal) => {
  calculateGainLoss()
})

watch(() => form.book_value, (newVal) => {
  calculateGainLoss()
})

// 方法
const loadAvailableAssets = async () => {
  try {
    const response = await assetApi.getList({
      status: 'available', // 只显示可用的资产
      per_page: 1000
    })
    
    if (response.success) {
      availableAssets.value = response.data.data
    }
  } catch (error) {
    console.error('加载可用资产失败:', error)
    ElMessage.error('加载可用资产失败')
  }
}

const handleAssetChange = (assetId) => {
  const asset = availableAssets.value.find(a => a.id === assetId)
  if (asset) {
    form.book_value = asset.current_book_value || asset.purchase_price || 0
    calculateGainLoss()
  }
}

const calculateGainLoss = () => {
  const disposalAmount = parseFloat(form.disposal_amount) || 0
  const bookValue = parseFloat(form.book_value) || 0
  form.gain_loss = disposalAmount - bookValue
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    // 准备提交数据
    const submitData = { ...form }
    
    // 转换金额为数字
    submitData.disposal_amount = parseFloat(submitData.disposal_amount) || 0
    submitData.salvage_value = parseFloat(submitData.salvage_value) || 0
    submitData.book_value = parseFloat(submitData.book_value) || 0
    submitData.gain_loss = parseFloat(submitData.gain_loss) || 0
    
    emit('submit', submitData)
  } catch (error) {
    ElMessage.error('请检查表单数据')
  }
}
</script>

<style scoped>
.el-form {
  padding: 20px 0;
}

.el-row {
  margin-bottom: 0;
}

.el-col {
  margin-bottom: 20px;
}
</style>