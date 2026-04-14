<template>
  <div class="home-page">
    <div class="stat-grid">
      <el-card class="stat-card success clickable dashboard-action" @click="goToOrgs">
        <div class="stat-label">组织总数</div>
        <div class="stat-value">{{ stats.orgCount }}</div>
      </el-card>
      <el-card class="stat-card warning clickable dashboard-action" @click="goToUsers">
        <div class="stat-label">党员总数</div>
        <div class="stat-value">{{ stats.userCount }}</div>
      </el-card>
      <el-card class="stat-card primary clickable dashboard-action" @click="goToTasks">
        <div class="stat-label">任务总数</div>
        <div class="stat-value">{{ stats.taskCount }}</div>
      </el-card>
      <el-card class="stat-card danger clickable dashboard-action" @click="goToOverdueTasks">
        <div class="stat-label">超期任务</div>
        <div class="stat-value">{{ stats.overdueCount }}</div>
      </el-card>
    </div>

    <div class="status-grid two-columns">
      <el-card>
        <template #header>
          <div class="panel-header clickable-header dashboard-action" @click="goToTasks">任务状态分布</div>
        </template>
        <div class="status-list">
          <div class="status-item clickable" @click="goToTasksWithStatus('待领用')">
            <span>待领用</span>
            <el-tag type="warning">{{ statusStats.pendingCount }}</el-tag>
          </div>
          <div class="status-item clickable" @click="goToTasksWithStatus('待处理')">
            <span>待处理</span>
            <el-tag type="warning">{{ statusStats.todoCount }}</el-tag>
          </div>
          <div class="status-item clickable" @click="goToTasksWithStatus('进行中')">
            <span>进行中</span>
            <el-tag type="primary">{{ statusStats.processingCount }}</el-tag>
          </div>
          <div class="status-item clickable" @click="goToTasksWithStatus('已完成')">
            <span>已完成</span>
            <el-tag type="success">{{ statusStats.doneCount }}</el-tag>
          </div>
        </div>
      </el-card>

      <el-card>
        <template #header>
          <div class="panel-header clickable-header dashboard-action" @click="goToTasks">预警级别分布</div>
        </template>
        <div class="status-list warning-list">
          <div class="status-item clickable" @click="goToTasksWithWarningLevel(0)">
            <span>正常</span>
            <el-tag type="success">{{ warningStats.normalCount }}</el-tag>
          </div>
          <div class="status-item clickable" @click="goToTasksWithWarningLevel(1)">
            <span>一般预警</span>
            <el-tag type="warning">{{ warningStats.warningCount }}</el-tag>
          </div>
          <div class="status-item clickable" @click="goToTasksWithWarningLevel(2)">
            <span>红名预警</span>
            <el-tag type="danger">{{ warningStats.dangerCount }}</el-tag>
          </div>
        </div>
      </el-card>
    </div>

    <el-card>
      <template #header>
        <div class="panel-header clickable-header dashboard-action" @click="goToTasks">任务类型分布</div>
      </template>
      <div class="status-list type-list">
        <div class="status-item clickable" @click="goToTasksWithTaskType('A')">
          <span>A类任务</span>
          <el-tag type="danger">{{ taskTypeStats.typeACount }}</el-tag>
        </div>
        <div class="status-item clickable" @click="goToTasksWithTaskType('B')">
          <span>B类任务</span>
          <el-tag type="warning">{{ taskTypeStats.typeBCount }}</el-tag>
        </div>
        <div class="status-item clickable" @click="goToTasksWithTaskType('C')">
          <span>C类任务</span>
          <el-tag type="success">{{ taskTypeStats.typeCCount }}</el-tag>
        </div>
      </div>
    </el-card>

    <el-card>
      <template #header>
        <div class="panel-header-row">
          <div class="panel-header clickable-header dashboard-action" @click="goToTasks">近期任务提醒</div>
          <el-button link type="primary" @click="goToTasks">查看全部</el-button>
        </div>
      </template>
      <el-empty v-if="!recentTasks.length" description="暂无未完成任务" />
      <el-table v-else :data="recentTasks" border @row-click="goToTasks" :row-class-name="recentTaskRowClassName">
        <el-table-column prop="title" label="任务标题" min-width="220">
          <template #default="scope">
            <el-tooltip :content="scope.row.description || '暂无任务描述'" placement="top" :show-after="200">
              <el-button link type="primary" class="task-link" @click="goToTasks">{{ scope.row.title }}</el-button>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="所属组织" min-width="160">
          <template #default="scope">{{ orgNameMap[scope.row.org_id] || scope.row.org_id || '-' }}</template>
        </el-table-column>
        <el-table-column label="执行人" min-width="140">
          <template #default="scope">{{ assigneeNameMap[scope.row.assignee_id] || '-' }}</template>
        </el-table-column>
        <el-table-column label="任务类型" width="120">
          <template #default="scope">
            <el-tag :type="taskTypeTagType(scope.row.task_type)">
              <span class="task-type-indicator" :class="`task-type-indicator-${taskTypeTagType(scope.row.task_type)}`">{{ taskTypeIcon(scope.row.task_type) }}</span>
              {{ taskTypeLabel(scope.row.task_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="预警级别" width="120">
          <template #default="scope">
            <el-tag :type="warningTagType(scope.row.warning_level)">
              <span class="warning-indicator" :class="`warning-indicator-${warningTagType(scope.row.warning_level)}`">{{ warningIcon(scope.row.warning_level) }}</span>
              {{ warningText(scope.row.warning_level) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="scope">
            <el-tag :type="statusTagType(scope.row.status)">{{ scope.row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="截止时间" min-width="220">
          <template #default="scope">
            <div class="deadline-cell">
              <span>{{ formatDeadline(scope.row.deadline_at) }}</span>
              <el-tag size="small" :type="deadlineTagType(scope.row.deadline_at)">
                <span class="deadline-indicator" :class="`deadline-indicator-${deadlineTagType(scope.row.deadline_at)}`">{{ deadlineIcon(scope.row.deadline_at) }}</span>
                {{ deadlineText(scope.row.deadline_at) }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api/http'

const router = useRouter()
const orgs = ref([])
const users = ref([])
const tasks = ref([])

const stats = computed(() => ({
  orgCount: orgs.value.length,
  userCount: users.value.length,
  taskCount: tasks.value.length,
  overdueCount: tasks.value.filter((item) => isOverdue(item.deadline_at)).length,
}))

const orgNameMap = computed(() => Object.fromEntries(orgs.value.map((item) => [item.id, item.name])))
const assigneeNameMap = computed(() => Object.fromEntries(users.value.map((item) => [item.id, item.name])))

const statusStats = computed(() => ({
  pendingCount: tasks.value.filter((item) => item.status === '待领用').length,
  todoCount: tasks.value.filter((item) => item.status === '待处理').length,
  processingCount: tasks.value.filter((item) => item.status === '进行中').length,
  doneCount: tasks.value.filter((item) => item.status === '已完成').length,
}))

const warningStats = computed(() => ({
  normalCount: tasks.value.filter((item) => Number(item.warning_level) === 0).length,
  warningCount: tasks.value.filter((item) => Number(item.warning_level) === 1).length,
  dangerCount: tasks.value.filter((item) => Number(item.warning_level) >= 2).length,
}))

const taskTypeStats = computed(() => ({
  typeACount: tasks.value.filter((item) => item.task_type === 'A').length,
  typeBCount: tasks.value.filter((item) => item.task_type === 'B').length,
  typeCCount: tasks.value.filter((item) => item.task_type === 'C').length,
}))

const recentTasks = computed(() => [...tasks.value]
  .filter((item) => item.deadline_at && item.status !== '已完成')
  .sort((a, b) => {
    const priorityDiff = deadlinePriority(a.deadline_at) - deadlinePriority(b.deadline_at)
    if (priorityDiff !== 0) return priorityDiff
    return new Date(a.deadline_at).getTime() - new Date(b.deadline_at).getTime()
  })
  .slice(0, 5))

const loadData = async () => {
  const [{ data: orgData }, { data: userData }, { data: taskData }] = await Promise.all([
    api.get('/orgs'),
    api.get('/users'),
    api.get('/tasks'),
  ])
  orgs.value = orgData.data || []
  users.value = userData.data || []
  tasks.value = taskData.data || []
}

const isOverdue = (value) => {
  if (!value) return false
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return false
  return date.getTime() < Date.now()
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

const deadlinePriority = (value) => {
  const diffHours = deadlineDiffHours(value)
  if (diffHours === null) return 3
  if (diffHours < 0) return 0
  if (diffHours <= 24) return 1
  return 2
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

const deadlineIcon = (value) => {
  const diffHours = deadlineDiffHours(value)
  if (diffHours === null) return '?'
  if (diffHours < 0) return '!'
  if (diffHours <= 24) return '⏰'
  return '✓'
}

const taskTypeLabel = (value) => {
  if (value === 'A') return 'A类任务'
  if (value === 'B') return 'B类任务'
  if (value === 'C') return 'C类任务'
  return value || '-'
}

const taskTypeIcon = (value) => {
  if (value === 'A') return 'A'
  if (value === 'B') return 'B'
  if (value === 'C') return 'C'
  return '?'
}

const taskTypeTagType = (value) => {
  if (value === 'A') return 'danger'
  if (value === 'B') return 'warning'
  if (value === 'C') return 'success'
  return 'info'
}

const statusTagType = (status) => {
  if (status === '已完成') return 'success'
  if (status === '待领用' || status === '待处理') return 'warning'
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

const warningIcon = (level) => {
  if (Number(level) >= 2) return '●'
  if (Number(level) === 1) return '▲'
  return '✓'
}

const recentTaskRowClassName = ({ row }) => {
  if (row.status === '已完成') return 'recent-task-row-done'
  if (row.status === '进行中') return 'recent-task-row-processing'
  if (row.status === '待领用' || row.status === '待处理') return 'recent-task-row-pending'
  return ''
}

const goToTasks = () => {
  router.push('/tasks')
}

const goToTasksWithStatus = (status) => {
  router.push({ path: '/tasks', query: { status } })
}

const goToTasksWithTaskType = (taskType) => {
  router.push({ path: '/tasks', query: { taskType } })
}

const goToTasksWithWarningLevel = (warningLevel) => {
  router.push({ path: '/tasks', query: { warningLevel: String(warningLevel) } })
}

const goToOverdueTasks = () => {
  router.push({ path: '/tasks', query: { overdue: '1' } })
}

const goToOrgs = () => {
  router.push('/orgs')
}

const goToUsers = () => {
  router.push('/users')
}

onMounted(loadData)
</script>

<style scoped>
.home-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.status-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}
.status-grid.two-columns {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.status-list {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.warning-list,
.type-list {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.status-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border: 1px solid #ebeef5;
  border-radius: 10px;
  background: #fafafa;
}
.status-item.clickable {
  cursor: pointer;
}
.clickable-header {
  cursor: pointer;
}
.dashboard-action {
  transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}
.dashboard-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(64, 158, 255, 0.12);
  background-color: #f5f9ff;
}
.deadline-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.task-link {
  padding: 0;
  min-height: auto;
  white-space: normal;
  text-align: left;
}
.warning-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  margin-right: 4px;
  font-weight: 700;
}
.task-type-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  margin-right: 4px;
  font-weight: 700;
}
.deadline-indicator {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  margin-right: 4px;
  font-weight: 700;
}
.task-type-indicator-success {
  color: #67c23a;
}
.task-type-indicator-warning {
  color: #e6a23c;
}
.task-type-indicator-danger {
  color: #f56c6c;
}
.warning-indicator-success {
  color: #67c23a;
}
.warning-indicator-warning {
  color: #e6a23c;
}
.warning-indicator-danger {
  color: #f56c6c;
}
.deadline-indicator-success {
  color: #67c23a;
}
.deadline-indicator-warning {
  color: #e6a23c;
}
.deadline-indicator-danger {
  color: #f56c6c;
}
:deep(.el-table__row) {
  cursor: pointer;
  transition: background-color 0.2s ease, transform 0.2s ease;
}
:deep(.el-table__body tr:hover > td.el-table__cell) {
  background-color: #f5f9ff !important;
}
:deep(.el-table__body tr:hover) {
  transform: translateY(-1px);
}
:deep(.recent-task-row-pending) {
  --el-table-tr-bg-color: rgba(230, 162, 60, 0.08);
}
:deep(.recent-task-row-processing) {
  --el-table-tr-bg-color: rgba(64, 158, 255, 0.08);
}
:deep(.recent-task-row-done) {
  --el-table-tr-bg-color: rgba(103, 194, 58, 0.08);
}
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.stat-card {
  border-radius: 12px;
}
.stat-card.clickable {
  cursor: pointer;
}
.stat-card :deep(.el-card__body) {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.stat-label {
  font-size: 14px;
  color: #606266;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
}
.stat-card.success {
  border-left: 5px solid #67c23a;
}
.stat-card.warning {
  border-left: 5px solid #e6a23c;
}
.stat-card.primary {
  border-left: 5px solid #409eff;
}
.stat-card.danger {
  border-left: 5px solid #f56c6c;
}
.panel-header {
  font-weight: 600;
}
.panel-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.clickable-header {
  cursor: pointer;
}

@media (max-width: 1200px) {
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .status-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .warning-list,
  .type-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .status-grid.two-columns {
    grid-template-columns: minmax(0, 1fr);
  }

  .warning-list,
  .type-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .stat-grid,
  .status-list,
  .warning-list,
  .type-list {
    grid-template-columns: minmax(0, 1fr);
  }

  .status-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .stat-value {
    font-size: 28px;
  }
}
</style>
