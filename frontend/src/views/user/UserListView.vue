<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>党员列表</span>
        <div class="actions">
          <el-button type="warning" @click="openCreate">新增党员</el-button>
          <el-button @click="loadData">刷新</el-button>
        </div>
      </div>
    </template>

    <div class="filters">
      <el-input v-model="filters.keyword" placeholder="搜索姓名/工号" clearable style="width: 220px" />
      <el-select v-model="filters.orgId" clearable placeholder="筛选组织" style="width: 180px">
        <el-option v-for="option in orgOptions" :key="option.id" :label="option.name" :value="option.id" />
      </el-select>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <el-table :data="filteredItems" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="姓名" />
      <el-table-column prop="employee_no" label="工作证号" />
      <el-table-column prop="party_role" label="党内职务" />
      <el-table-column prop="job_title" label="岗位职务" />
      <el-table-column label="所属组织" min-width="140">
        <template #default="scope">{{ orgNameMap[scope.row.org_id] || scope.row.org_id || '-' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="220">
        <template #default="scope">
          <el-button link type="primary" @click="openEdit(scope.row)">编辑</el-button>
          <el-button link type="danger" @click="removeItem(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="姓名"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="工作证号"><el-input v-model="form.employee_no" /></el-form-item>
        <el-form-item label="所属组织">
          <el-select v-model="form.org_id" placeholder="请选择组织" style="width: 100%">
            <el-option v-for="option in orgOptions" :key="option.id" :label="option.name" :value="option.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="党内职务"><el-input v-model="form.party_role" /></el-form-item>
        <el-form-item label="岗位职务"><el-input v-model="form.job_title" /></el-form-item>
        <el-form-item label="性别"><el-input v-model="form.gender" /></el-form-item>
        <el-form-item label="学历"><el-input v-model="form.education" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">保存</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/http'
import { emptyUserForm } from '../../api/forms'

const items = ref([])
const orgOptions = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('新增党员')
const editingId = ref(null)
const form = ref(emptyUserForm())
const filters = ref({
  keyword: '',
  orgId: null,
})

const orgNameMap = computed(() => Object.fromEntries(orgOptions.value.map((item) => [item.id, item.name])))
const filteredItems = computed(() => items.value.filter((item) => {
  const keyword = filters.value.keyword.trim()
  const matchesKeyword = !keyword || item.name?.includes(keyword) || item.employee_no?.includes(keyword)
  const matchesOrg = !filters.value.orgId || item.org_id === filters.value.orgId
  return matchesKeyword && matchesOrg
}))

const resetFilters = () => {
  filters.value = {
    keyword: '',
    orgId: null,
  }
}

const loadOrgOptions = async () => {
  const { data } = await api.get('/orgs')
  orgOptions.value = data.data || []
}

const loadData = async () => {
  loading.value = true
  try {
    const [{ data: userData }] = await Promise.all([
      api.get('/users'),
      loadOrgOptions(),
    ])
    items.value = userData.data || []
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  dialogTitle.value = '新增党员'
  editingId.value = null
  form.value = emptyUserForm()
  dialogVisible.value = true
}

const openEdit = (row) => {
  dialogTitle.value = '编辑党员'
  editingId.value = row.id
  form.value = {
    name: row.name,
    employee_no: row.employee_no,
    org_id: row.org_id,
    party_role: row.party_role,
    job_title: row.job_title,
    gender: row.gender,
    education: row.education,
    remark: row.remark,
  }
  dialogVisible.value = true
}

const submitForm = async () => {
  const payload = {
    ...form.value,
    org_id: Number(form.value.org_id),
  }
  if (editingId.value) {
    await api.put(`/users/${editingId.value}`, payload)
    ElMessage.success('党员已更新')
  } else {
    await api.post('/users', payload)
    ElMessage.success('党员已创建')
  }
  dialogVisible.value = false
  await loadData()
}

const removeItem = async (row) => {
  await ElMessageBox.confirm(`确认删除党员“${row.name}”吗？`, '提示', { type: 'warning' })
  await api.delete(`/users/${row.id}`)
  ElMessage.success('党员已删除')
  await loadData()
}

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
