<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>组织列表</span>
        <div class="actions">
          <el-button type="primary" @click="openCreate">新增组织</el-button>
          <el-button @click="loadData">刷新</el-button>
        </div>
      </div>
    </template>

    <div class="filters">
      <el-input v-model="filters.keyword" placeholder="搜索组织名称" clearable style="width: 220px" />
      <el-select v-model="filters.level" clearable placeholder="筛选层级" style="width: 160px">
        <el-option label="1级" :value="1" />
        <el-option label="2级" :value="2" />
        <el-option label="3级" :value="3" />
        <el-option label="4级" :value="4" />
      </el-select>
      <el-select v-model="selectedAction" placeholder="选择操作" style="width: 160px" @change="handleActionChange">
        <el-option label="新建顶级组织" value="createTopLevel" />
      </el-select>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <el-table :data="filteredItems" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="组织名称" />
      <el-table-column prop="level" label="层级" width="100" />
      <el-table-column prop="org_type" label="组织类型" />
      <el-table-column label="父级组织" min-width="140">
        <template #default="scope">{{ parentNameMap[scope.row.parent_id] || '-' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="220">
        <template #default="scope">
          <el-button link type="primary" @click="openEdit(scope.row)">编辑</el-button>
          <el-button link type="danger" @click="removeItem(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="组织名称">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="父级组织">
          <el-select v-model="form.parent_id" placeholder="请选择父级组织" style="width: 100%">
            <el-option v-for="option in parentSelectOptions" :key="option.id" :label="option.name" :value="option.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="层级">
          <el-input-number v-model="form.level" :min="1" :max="4" />
        </el-form-item>
        <el-form-item label="组织类型">
          <el-input v-model="form.org_type" />
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
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/http'
import { emptyOrgForm } from '../../api/forms'

const items = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('新增组织')
const editingId = ref(null)
const form = ref(emptyOrgForm())
const filters = ref({
  keyword: '',
  level: null,
})
const selectedAction = ref('')

const parentOptions = computed(() => items.value.filter((item) => item.id !== editingId.value && item.id !== topLevelParentId.value))
const topLevelParentId = computed(() => items.value.find((item) => item.level === 1)?.id || null)
const parentNameMap = computed(() => Object.fromEntries(items.value.map((item) => [item.id, item.name])))
const filteredItems = computed(() => items.value.filter((item) => {
  const keyword = filters.value.keyword.trim()
  const matchesKeyword = !keyword || item.name?.includes(keyword)
  const matchesLevel = !filters.value.level || item.level === filters.value.level
  return matchesKeyword && matchesLevel
}))

const syncParentId = () => {
  if (!dialogVisible.value) return
  if (!parentOptions.value.length) {
    form.value.parent_id = null
    return
  }
  const matched = parentOptions.value.some((item) => item.id === form.value.parent_id)
  if (!matched) {
    form.value.parent_id = topLevelParentId.value || parentOptions.value[0].id
  }
}

const resetFilters = () => {
  filters.value = {
    keyword: '',
    level: null,
  }
}

const handleActionChange = (value) => {
  if (value === 'createTopLevel') {
    openCreateTopLevel()
  }
  selectedAction.value = ''
}

const openCreateTopLevel = () => {
  dialogTitle.value = '新增顶级组织'
  editingId.value = null
  form.value = emptyOrgForm()
  form.value.parent_id = null
  form.value.level = 1
  dialogVisible.value = true
  syncParentId()
}

const loadData = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/orgs')
    items.value = data.data || []
    syncParentId()
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  dialogTitle.value = '新增组织'
  editingId.value = null
  form.value = emptyOrgForm()
  form.value.parent_id = topLevelParentId.value
  dialogVisible.value = true
  syncParentId()
}

const openEdit = (row) => {
  dialogTitle.value = '编辑组织'
  editingId.value = row.id
  form.value = {
    name: row.name,
    parent_id: row.parent_id,
    level: row.level,
    org_type: row.org_type,
  }
  dialogVisible.value = true
  syncParentId()
}

const submitForm = async () => {
  if (!form.value.name.trim()) {
    ElMessage.warning('请输入组织名称')
    return
  }
  if (!form.value.org_type.trim()) {
    ElMessage.warning('请输入组织类型')
    return
  }
  if (!form.value.parent_id) {
    ElMessage.warning('请先选择父级组织')
    return
  }

  const payload = {
    ...form.value,
    parent_id: form.value.parent_id,
  }

  try {
    if (editingId.value) {
      await api.put(`/orgs/${editingId.value}`, payload)
      ElMessage.success('组织已更新')
    } else {
      await api.post('/orgs', payload)
      ElMessage.success('组织已创建')
    }
    dialogVisible.value = false
    await loadData()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

const removeItem = async (row) => {
  await ElMessageBox.confirm(`确认删除组织“${row.name}”吗？`, '提示', { type: 'warning' })
  await api.delete(`/orgs/${row.id}`)
  ElMessage.success('组织已删除')
  await loadData()
}

watch(() => form.value.level, () => {
  syncParentId()
})

watch(items, () => {
  syncParentId()
}, { deep: true })

onMounted(loadData)
</script>

<style scoped>
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
