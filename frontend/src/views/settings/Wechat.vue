<template>
  <div class="wechat-config">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>微信通知配置</span>
          <el-switch
            v-model="config.enabled"
            @change="handleToggle"
          />
        </div>
      </template>

      <el-alert
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <template #title>
          使用企业微信机器人发送通知，请按以下步骤配置：
          <br>1. 在企业微信群中添加群机器人
          <br>2. 复制Webhook地址到下方输入框
          <br>3. 点击"保存配置"并测试
        </template>
      </el-alert>

      <el-form
        ref="formRef"
        :model="form"
        label-width="120px"
      >
        <el-form-item
          label="Webhook地址"
          prop="webhook_url"
        >
          <el-input
            v-model="form.webhook_url"
            placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=..."
            type="textarea"
            :rows="3"
          />
          <div class="form-tip">
            企业微信群机器人Webhook地址，仅在群聊中有效
          </div>
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            @click="saveConfig"
            :loading="saving"
          >
            保存配置
          </el-button>
          <el-button
            @click="testNotification"
            :loading="testing"
            :disabled="!form.webhook_url"
          >
            发送测试消息
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 20px">
      <template #header>
        <span>通知开关</span>
      </template>

      <el-form label-width="200px">
        <el-form-item label="资产到期提醒">
          <el-switch v-model="notifications.asset_expiring" @change="updateNotifications" />
          <div class="form-tip">
            资产即将到期时发送提醒通知
          </div>
        </el-form-item>

        <el-form-item label="资产变动通知">
          <el-switch v-model="notifications.asset_changed" @change="updateNotifications" />
          <div class="form-tip">
            资产领用、归还时发送通知
          </div>
        </el-form-item>

        <el-form-item label="盘点任务创建通知">
          <el-switch v-model="notifications.inventory_created" @change="updateNotifications" />
          <div class="form-tip">
            创建盘点任务时发送通知
          </div>
        </el-form-item>

        <el-form-item label="盘点完成通知">
          <el-switch v-model="notifications.inventory_completed" @change="updateNotifications" />
          <div class="form-tip">
            盘点任务完成时发送通知
          </div>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 20px">
      <template #header>
        <span>使用说明</span>
      </template>

      <el-timeline>
        <el-timeline-item timestamp="步骤1" placement="top">
          <el-card>
            <h4>添加群机器人</h4>
            <p>在需要接收通知的企业微信群中，点击右上角"..." → "添加群机器人" → "新建"</p>
          </el-card>
        </el-timeline-item>
        <el-timeline-item timestamp="步骤2" placement="top">
          <el-card>
            <h4>获取Webhook地址</h4>
            <p>机器人创建成功后，会显示Webhook地址，复制该地址</p>
          </el-card>
        </el-timeline-item>
        <el-timeline-item timestamp="步骤3" placement="top">
          <el-card>
            <h4>配置系统</h4>
            <p>将Webhook地址粘贴到上方输入框，点击"保存配置"</p>
          </el-card>
        </el-timeline-item>
        <el-timeline-item timestamp="步骤4" placement="top">
          <el-card>
            <h4>测试通知</h4>
            <p>点击"发送测试消息"，确认配置正确</p>
          </el-card>
        </el-timeline-item>
      </el-timeline>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import wechatApi from '@/api/wechat'

const formRef = ref(null)
const config = ref({
  enabled: false,
  webhook_url: ''
})
const form = ref({
  webhook_url: ''
})
const notifications = ref({
  asset_expiring: true,
  asset_changed: true,
  inventory_created: true,
  inventory_completed: true
})
const saving = ref(false)
const testing = ref(false)

const loadConfig = async () => {
  try {
    const res = await wechatApi.getConfig()
    config.value = res.data
    form.value.webhook_url = res.data.webhook_url || ''
  } catch (error) {
    console.error('加载配置失败:', error)
  }
}

const loadNotifications = async () => {
  try {
    const res = await wechatApi.getNotificationSettings()
    notifications.value = res.data
  } catch (error) {
    console.error('加载通知设置失败:', error)
  }
}

const handleToggle = async () => {
  await saveConfig()
}

const saveConfig = async () => {
  saving.value = true
  try {
    await wechatApi.updateConfig({
      enabled: config.value.enabled,
      webhook_url: form.value.webhook_url
    })
    ElMessage.success('配置保存成功')
    await loadConfig()
  } catch (error) {
    ElMessage.error('配置保存失败')
  } finally {
    saving.value = false
  }
}

const testNotification = async () => {
  testing.value = true
  try {
    const res = await wechatApi.testNotification()
    if (res.data.status === 'success') {
      ElMessage.success('测试消息发送成功，请检查企业微信群')
    } else {
      ElMessage.error(res.data.message || '测试消息发送失败')
    }
  } catch (error) {
    ElMessage.error('测试消息发送失败')
  } finally {
    testing.value = false
  }
}

const updateNotifications = async () => {
  try {
    await wechatApi.updateNotificationSettings(notifications.value)
    ElMessage.success('通知设置更新成功')
  } catch (error) {
    ElMessage.error('通知设置更新失败')
  }
}

onMounted(() => {
  loadConfig()
  loadNotifications()
})
</script>

<style scoped>
.wechat-config {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}

.el-timeline {
  padding-left: 0;
}

.el-card :deep(.el-card__body) {
  padding: 15px;
}
</style>
