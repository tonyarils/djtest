<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>任务列表</span>
        <div class="actions">
          <el-button type="danger" @click="openCreate">新增任务</el-button>
          <el-button @click="loadData">刷新</el-button>
        </div>
      </div>
    </template>

    <div class="filters">
      <el-input v-model="filters.keyword" placeholder="搜索任务标题" clearable style="width: 220px" />
      <el-select v-model="filters.taskType" clearable placeholder="筛选任务类型" style="width: 160px">
        <el-option v-for="option in taskTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
      </el-select>
      <el-select v-model="filters.status" clearable placeholder="筛选状态" style="width: 160px">
        <el-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
      </el-select>
      <el-select v-model="filters.orgId" clearable placeholder="筛选组织" style="width: 180px">
        <el-option v-for="option in orgOptions" :key="option.id" :label="option.name" :value="option.id" />
      </el-select>
      <el-select v-model="filters.warningLevel" clearable placeholder="筛选预警级别" style="width: 180px">
        <el-option v-for="option in warningLevelOptions" :key="option.value" :label="option.label" :value="option.value" />
      </el-select>
      <el-checkbox v-model="filters.deadlineOnly">仅看有截止时间</el-checkbox>
      <el-checkbox v-model="filters.assignedOnly">仅看已分配执行人</el-checkbox>
      <el-checkbox v-model="filters.unfinishedOnly">仅看未完成任务</el-checkbox>
      <el-checkbox v-model="filters.dangerOnly">仅看红名预警</el-checkbox>
      <el-checkbox v-model="filters.activeStatusOnly">仅看待处理/进行中</el-checkbox>
      <el-checkbox v-model="filters.pendingCountOnly">仅看待领用</el-checkbox>
      <el-checkbox v-model="filters.warningOnly">仅看一般预警</el-checkbox>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <div v-if="quickFilterTags.length" class="quick-filters">
      <span class="quick-filters-label">快捷筛选：</span>
      <el-tag
        v-for="tag in quickFilterTags"
        :key="tag.key"
        type="info"
        effect="plain"
        closable
        @close="removeQuickFilter(tag.key)"
      >
        {{ tag.label }}
      </el-tag>
      <span class="quick-filters-count">共 {{ filteredItems.length }} 条</span>
      <el-button link type="primary" @click="clearQuickFilters">清空</el-button>
    </div>

    <el-table :data="filteredItems" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="title" label="任务标题" />
      <el-table-column label="任务类型" width="120">
        <template #default="scope">
          <el-tag :type="taskTypeTagType(scope.row.task_type)">{{ taskTypeLabel(scope.row.task_type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="120">
        <template #default="scope">
          <el-tag :type="statusTagType(scope.row.status)">{{ scope.row.status || '-' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="预警级别" width="120">
        <template #default="scope">
          <el-tag :type="warningTagType(scope.row.warning_level)">{{ warningText(scope.row.warning_level) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="所属组织" min-width="140">
        <template #default="scope">{{ orgNameMap[scope.row.org_id] || scope.row.org_id || '-' }}</template>
      </el-table-column>
      <el-table-column label="执行人" min-width="140">
        <template #default="scope">{{ assigneeNameMap[scope.row.assignee_id] || '-' }}</template>
      </el-table-column>
      <el-table-column label="截止时间" min-width="220">
        <template #default="scope">
          <div class="deadline-cell">
            <span>{{ formatDeadline(scope.row.deadline_at) }}</span>
            <el-tag v-if="scope.row.deadline_at" size="small" :type="deadlineTagType(scope.row.deadline_at)">
              {{ deadlineText(scope.row.deadline_at) }}
            </el-tag>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220">
        <template #default="scope">
          <el-button link type="primary" @click="openEdit(scope.row)">编辑</el-button>
          <el-button link type="danger" @click="removeItem(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="620px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="任务标题"><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="任务描述"><el-input v-model="form.description" type="textarea" /></el-form-item>
        <el-form-item label="任务类型">
          <el-select v-model="form.task_type" placeholder="请选择任务类型" style="width: 100%">
            <el-option v-for="option in taskTypeOptions" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" placeholder="请选择状态" style="width: 100%">
            <el-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="所属组织">
          <el-select v-model="form.org_id" placeholder="请选择组织" style="width: 100%">
            <el-option v-for="option in orgOptions" :key="option.id" :label="option.name" :value="option.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="执行人">
          <el-select v-model="form.assignee_id" clearable placeholder="请选择执行人" style="width: 100%">
            <el-option v-for="option in assigneeOptions" :key="option.id" :label="`${option.name}（${option.employee_no}）`" :value="option.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="预警级别">
          <el-select v-model="form.warning_level" placeholder="请选择预警级别" style="width: 100%">
            <el-option v-for="option in warningLevelOptions" :key="option.value" :label="option.label" :value="option.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="截止时间">
          <el-date-picker v-model="form.deadline_at" type="datetime" placeholder="选择截止时间" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/http'
import { emptyTaskForm } from '../../api/forms'
import { normalizeDeadline } from '../../api/format'

const route = useRoute()
const router = useRouter()
const items = ref([])
const orgOptions = ref([])
const assigneeOptions = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('新增任务')
const editingId = ref(null)
const form = ref(emptyTaskForm())
const filters = ref({
  keyword: '',
  taskType: '',
  status: '',
  orgId: null,
  warningLevel: null,
  overdueOnly: false,
  deadlineOnly: false,
  assignedOnly: false,
  unfinishedOnly: false,
  dangerOnly: false,
  activeStatusOnly: false,
  pendingCountOnly: false,
  warningOnly: false,
})

const taskTypeOptions = [
  { label: 'A类任务', value: 'A' },
  { label: 'B类任务', value: 'B' },
  { label: 'C类任务', value: 'C' },
]

const taskTypeMap = Object.fromEntries(taskTypeOptions.map((item) => [item.value, item.label]))

const statusOptions = [
  { label: '待领用', value: '待领用' },
  { label: '待处理', value: '待处理' },
  { label: '进行中', value: '进行中' },
  { label: '已完成', value: '已完成' },
]

const warningLevelOptions = [
  { label: '正常', value: 0 },
  { label: '一般预警', value: 1 },
  { label: '红名预警', value: 2 },
]

const orgNameMap = computed(() => Object.fromEntries(orgOptions.value.map((item) => [item.id, item.name])))
const assigneeNameMap = computed(() => Object.fromEntries(assigneeOptions.value.map((item) => [item.id, item.name])))
const filteredItems = computed(() => items.value.filter((item) => {
  const keyword = filters.value.keyword.trim()
  const matchesKeyword = !keyword || item.title?.includes(keyword)
  const matchesTaskType = !filters.value.taskType || item.task_type === filters.value.taskType
  const matchesStatus = !filters.value.status || item.status === filters.value.status
  const matchesOrg = !filters.value.orgId || item.org_id === filters.value.orgId
  const matchesWarningLevel = filters.value.warningLevel === null || Number(item.warning_level) === filters.value.warningLevel
  const matchesOverdue = !filters.value.overdueOnly || deadlineDiffHours(item.deadline_at) < 0
  const matchesDeadline = !filters.value.deadlineOnly || !!item.deadline_at
  const matchesAssigned = !filters.value.assignedOnly || !!item.assignee_id
  const matchesUnfinished = !filters.value.unfinishedOnly || item.status !== '已完成'
  const matchesDanger = !filters.value.dangerOnly || Number(item.warning_level) >= 2
  const matchesActiveStatus = !filters.value.activeStatusOnly || ['待处理', '进行中'].includes(item.status)
  const matchesPendingCount = !filters.value.pendingCountOnly || item.status === '待领用'
  const matchesWarning = !filters.value.warningOnly || Number(item.warning_level) === 1
  return matchesKeyword && matchesTaskType && matchesStatus && matchesOrg && matchesWarningLevel && matchesOverdue && matchesDeadline && matchesAssigned && matchesUnfinished && matchesDanger && matchesActiveStatus && matchesPendingCount && matchesWarning
}))

const quickFilterTags = computed(() => {
  const tags = []
  if (filters.value.status) tags.push({ key: 'status', label: `状态：${filters.value.status}` })
  if (filters.value.taskType) tags.push({ key: 'taskType', label: `任务类型：${taskTypeLabel(filters.value.taskType)}` })
  if (filters.value.warningLevel !== null) tags.push({ key: 'warningLevel', label: `预警级别：${warningText(filters.value.warningLevel)}` })
  if (filters.value.orgId) tags.push({ key: 'orgId', label: `组织：${orgNameMap.value[filters.value.orgId] || filters.value.orgId}` })
  if (filters.value.overdueOnly) tags.push({ key: 'overdue', label: '仅看超期任务' })
  if (filters.value.deadlineOnly) tags.push({ key: 'deadline', label: '仅看有截止时间' })
  if (filters.value.assignedOnly) tags.push({ key: 'assigned', label: '仅看已分配执行人' })
  if (filters.value.unfinishedOnly) tags.push({ key: 'unfinished', label: '仅看未完成任务' })
  if (filters.value.dangerOnly) tags.push({ key: 'danger', label: '仅看红名预警' })
  if (filters.value.activeStatusOnly) tags.push({ key: 'activeStatus', label: '仅看待处理/进行中' })
  if (filters.value.pendingCountOnly) tags.push({ key: 'pendingCount', label: '仅看待领用' })
  return tags
})

const removeQuickFilter = async (key) => {
  const nextQuery = { ...route.query }
  if (key === 'status') delete nextQuery.status
  if (key === 'taskType') delete nextQuery.taskType
  if (key === 'warningLevel') delete nextQuery.warningLevel
  if (key === 'orgId') delete nextQuery.orgId
  if (key === 'overdue') delete nextQuery.overdue
  if (key === 'deadline') delete nextQuery.deadline
  if (key === 'assigned') delete nextQuery.assigned
  if (key === 'unfinished') delete nextQuery.unfinished
  if (key === 'danger') delete nextQuery.danger
  if (key === 'activeStatus') delete nextQuery.activeStatus
  if (key === 'pendingCount') delete nextQuery.pendingCount
  if (key === 'warning') delete nextQuery.warning
  await router.push({ path: route.path, query: nextQuery })
}

const clearQuickFilters = () => {
  router.push({ path: route.path })
}

const resetFilters = async () => {
  filters.value = {
    keyword: '',
    taskType: '',
    status: '',
    orgId: null,
    warningLevel: null,
    overdueOnly: false,
    deadlineOnly: false,
    assignedOnly: false,
    unfinishedOnly: false,
    dangerOnly: false,
    activeStatusOnly: false,
    pendingCountOnly: false,
    warningOnly: false,
  }
  if (Object.keys(route.query).length) {
    await router.push({ path: route.path })
  }
}

const applyRouteFilters = () => {
  const { keyword, taskType, status, orgId, warningLevel, overdue, deadline, assigned, unfinished, danger, activeStatus, pendingCount, warning } = route.query
  filters.value = {
    keyword: typeof keyword === 'string' ? keyword : '',
    taskType: typeof taskType === 'string' ? taskType : '',
    status: typeof status === 'string' ? status : '',
    orgId: orgId ? Number(orgId) : null,
    warningLevel: warningLevel !== undefined ? Number(warningLevel) : null,
    overdueOnly: overdue === '1',
    deadlineOnly: deadline === '1',
    assignedOnly: assigned === '1',
    unfinishedOnly: unfinished === '1',
    dangerOnly: danger === '1',
    activeStatusOnly: activeStatus === '1',
    pendingCountOnly: pendingCount === '1',
    warningOnly: warning === '1',
  }
}

const buildFilterQuery = (value) => {
  const query = {}
  const keyword = value.keyword.trim()
  if (keyword) query.keyword = keyword
  if (value.taskType) query.taskType = value.taskType
  if (value.status) query.status = value.status
  if (value.orgId) query.orgId = String(value.orgId)
  if (value.warningLevel !== null) query.warningLevel = String(value.warningLevel)
  if (value.overdueOnly) query.overdue = '1'
  if (value.deadlineOnly) query.deadline = '1'
  if (value.assignedOnly) query.assigned = '1'
  if (value.unfinishedOnly) query.unfinished = '1'
  if (value.dangerOnly) query.danger = '1'
  if (value.activeStatusOnly) query.activeStatus = '1'
  if (value.pendingCountOnly) query.pendingCount = '1'
  if (value.warningOnly) query.warning = '1'
  return query
}

const isSameQuery = (left, right) => JSON.stringify(left) === JSON.stringify(right)

const statusTagType = (status) => {
  if (status === '已完成') return 'success'
  if (status === '待领用' || status === '待处理') return 'warning'
  return 'info'
}

const taskTypeLabel = (value) => taskTypeMap[value] || value || '-'

const taskTypeTagType = (value) => {
  if (value === 'A') return 'danger'
  if (value === 'B') return 'warning'
  if (value === 'C') return 'success'
  return 'info'
}

const warningTagType = (level) => {
  if (Number(level) >= 2) return 'danger'
  if (Number(level) === 1) return 'warning'
  return 'success'
}

const warningText = (level) => {
  if (Number(level) >= 2) return '红名预警'
  if (Number(level) === 1) return '一般预警'
  return '正常'
}

const formatDeadline = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString('zh-CN', { hour12: false })
}

const deadlineDiffHours = (value) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return (date.getTime() - Date.now()) / (1000 * 60 * 60)
}

const deadlineTagType = (value) => {
  const diffHours = deadlineDiffHours(value)
  if (diffHours === null) return 'info'
  if (diffHours < 0) return 'danger'
  if (diffHours <= 24) return 'warning'
  return 'success'
}

const deadlineText = (value) => {
  const diffHours = deadlineDiffHours(value)
  if (diffHours === null) return '未知'
  if (diffHours < 0) return '已超期'
  if (diffHours <= 24) return '即将到期'
  return '正常'
}

const loadOptions = async () => {
  const [{ data: orgData }, { data: userData }] = await Promise.all([
    api.get('/orgs'),
    api.get('/users'),
  ])
  orgOptions.value = orgData.data || []
  assigneeOptions.value = userData.data || []
}

const loadData = async () => {
  loading.value = true
  try {
    const [{ data: taskData }] = await Promise.all([
      api.get('/tasks'),
      loadOptions(),
    ])
    items.value = taskData.data || []
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  dialogTitle.value = '新增任务'
  editingId.value = null
  form.value = emptyTaskForm()
  dialogVisible.value = true
}

const openEdit = (row) => {
  dialogTitle.value = '编辑任务'
  editingId.value = row.id
  form.value = {
    title: row.title,
    description: row.description,
    task_type: row.task_type,
    status: row.status,
    org_id: row.org_id,
    assignee_id: row.assignee_id,
    warning_level: Number(row.warning_level || 0),
    deadline_at: row.deadline_at ? new Date(row.deadline_at) : '',
  }
  dialogVisible.value = true
}

const submitForm = async () => {
  const payload = {
    ...form.value,
    org_id: Number(form.value.org_id),
    assignee_id: form.value.assignee_id || null,
    warning_level: Number(form.value.warning_level || 0),
    deadline_at: normalizeDeadline(form.value.deadline_at),
  }
  if (editingId.value) {
    await api.put(`/tasks/${editingId.value}`, payload)
    ElMessage.success('任务已更新')
  } else {
    await api.post('/tasks', payload)
    ElMessage.success('任务已创建')
  }
  dialogVisible.value = false
  await loadData()
}

const removeItem = async (row) => {
  await ElMessageBox.confirm(`确认删除任务“${row.title}”吗？`, '提示', { type: 'warning' })
  await api.delete(`/tasks/${row.id}`)
  ElMessage.success('任务已删除')
  await loadData()
}

watch(() => route.query, applyRouteFilters, { immediate: true })

watch(filters, async (value) => {
  const nextQuery = buildFilterQuery(value)
  const currentQuery = buildFilterQuery({
    keyword: typeof route.query.keyword === 'string' ? route.query.keyword : '',
    taskType: typeof route.query.taskType === 'string' ? route.query.taskType : '',
    status: typeof route.query.status === 'string' ? route.query.status : '',
    orgId: route.query.orgId ? Number(route.query.orgId) : null,
    warningLevel: route.query.warningLevel !== undefined ? Number(route.query.warningLevel) : null,
    overdueOnly: route.query.overdue === '1',
    deadlineOnly: route.query.deadline === '1',
    assignedOnly: route.query.assigned === '1',
    unfinishedOnly: route.query.unfinished === '1',
    dangerOnly: route.query.danger === '1',
    activeStatusOnly: route.query.activeStatus === '1',
    pendingCountOnly: route.query.pendingCount === '1',
    warningOnly: route.query.warning === '1',
  })
  if (isSameQuery(nextQuery, currentQuery)) return
  await router.replace({ path: route.path, query: nextQuery })
}, { deep: true })

onMounted(loadData)
</script>

<style scoped>
.deadline-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.quick-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.quick-filters-label {
  color: #606266;
}
.quick-filters-count {
  color: #909399;
  font-size: 13px;
}
.filters,
.card-header,
.actions {
  display: flex;
  align-items: center;
}
.filters {
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.card-header {
  justify-content: space-between;
}
.actions {
  gap: 12px;
}
</style>
