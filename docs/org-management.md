# 组织管理系统完整文档

## 📋 概述

组织管理模块是党建系统的核心基础，提供完整的树形组织结构管理功能。系统支持从上到下的多级管理，包括党委、支部、党小组等组织类型，为党员管理、任务分配等上层功能提供数据基础。

## 🏗️ 架构设计

### 系统架构图

```
┌─────────────────────────────────────────────────────────┐
│                     前端层 (Vue 3)                       │
│  ┌──────────────────────────────────────────────────┐   │
│  │          OrgListView.vue (组织管理页面)          │   │
│  │  • 组织列表展示  • 搜索筛选  • CRUD操作          │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                            ↓ (HTTP/RESTful API)
┌─────────────────────────────────────────────────────────┐
│                     后端层 (Go/Gin)                      │
│  ┌──────────────────────────────────────────────────┐   │
│  │ Handler → Service → Repository → GORM           │   │
│  │ (请求处理) (业务逻辑) (数据访问) (ORM操作)      │   │
│  └──────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │       Middleware (权限隔离、用户上下文)          │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                            ↓ (SQL)
┌─────────────────────────────────────────────────────────┐
│                    数据库层 (MySQL)                      │
│  ┌──────────────────────────────────────────────────┐   │
│  │ sys_org | sys_user | t_task | t_attachment      │   │
│  │ t_task_log | ...                                │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

## 📊 数据库设计

### sys_org 表详细说明

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 组织唯一标识 |
| `name` | VARCHAR(100) | NOT NULL | 组织名称 (如"示范党委") |
| `parent_id` | BIGINT UNSIGNED | NULL (可选) | 父级组织ID，为NULL表示顶级组织 |
| `level` | INT | NOT NULL | 组织层级 (1=顶级, 2=二级, 3=三级, 4=四级) |
| `org_type` | VARCHAR(50) | NOT NULL | 组织类型 (党委/支部/党小组等) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 更新时间 |

### 树形结构示例

```
sys_org表中的数据关系:

id=1: 示范党委 (parent_id=NULL, level=1) [顶级]
  ├─ id=3: 第一党支部 (parent_id=1, level=2)
  │   └─ id=5: 第一党小组 (parent_id=3, level=3)
  └─ id=4: 第二党支部 (parent_id=1, level=2)

id=2: 市级党委 (parent_id=NULL, level=1) [顶级]
  └─ id=6: 市级第一党支部 (parent_id=2, level=2)
```

### 数据库约束

```sql
-- 父级索引，加速parent_id查询
KEY idx_sys_org_parent_id (parent_id)

-- 树形结构完整性约束 (应用层实现)
-- 1. parent_id为NULL时，level必须为1
-- 2. parent_id不为NULL时，level不能为1
-- 3. level不能超过4
```

## 💻 后端实现详解

### 项目目录结构

```
backend/
├── cmd/
│   └── server/
│       └── main.go                    # 主程序入口
├── internal/
│   ├── bootstrap/
│   │   ├── migrate.go                 # 数据库迁移
│   │   └── seed.go                    # 初始化示例数据
│   ├── config/
│   │   └── config.go                  # 配置管理
│   ├── middleware/
│   │   └── auth_context.go            # 权限和用户上下文
│   ├── model/
│   │   ├── base.go                    # 基础模型 (通用字段)
│   │   ├── org.go                     # Org模型
│   │   ├── user.go                    # User模型
│   │   └── task.go                    # Task模型
│   ├── dto/
│   │   ├── org.go                     # Org请求/响应DTO
│   │   ├── user.go                    # User DTO
│   │   └── task.go                    # Task DTO
│   ├── handler/
│   │   ├── org.go                     # OrgHandler HTTP处理器
│   │   ├── user.go                    # UserHandler
│   │   └── task.go                    # TaskHandler
│   ├── service/
│   │   ├── org.go                     # OrgService 业务逻辑
│   │   ├── user.go                    # UserService
│   │   └── task.go                    # TaskService
│   ├── repository/
│   │   ├── org.go                     # OrgRepository 数据访问
│   │   ├── user.go                    # UserRepository
│   │   └── task.go                    # TaskRepository
│   └── router/
│       └── router.go                  # 路由配置
├── schema.sql                         # 数据库DDL脚本
├── go.mod                             # Go依赖声明
├── go.sum                             # Go依赖锁定
└── .env.example                       # 环境变量示例
```

### 核心代码详解

#### 1. 模型定义 (internal/model/org.go)

```go
package model

type Org struct {
    BaseModel                          // 包含 ID, CreatedAt, UpdatedAt
    Name     string `gorm:"column:name;size:100;not null" json:"name"`
    ParentID *uint  `gorm:"column:parent_id" json:"parent_id"`
    Level    int    `gorm:"column:level;not null" json:"level"`
    OrgType  string `gorm:"column:org_type;size:50;not null" json:"org_type"`
}

func (Org) TableName() string {
    return "sys_org"                   // 指定数据库表名
}
```

**设计说明:**
- `BaseModel`: 包含通用字段 (ID, CreatedAt, UpdatedAt)
- `ParentID *uint`: 使用指针，支持NULL值 (顶级组织)
- `json:"..."`: 指定JSON序列化的字段名

#### 2. DTO定义 (internal/dto/org.go)

```go
package dto

// 请求体
type OrgRequest struct {
    Name     string `json:"name" binding:"required"`
    ParentID *uint  `json:"parent_id"`
    Level    int    `json:"level" binding:"required"`
    OrgType  string `json:"org_type" binding:"required"`
}

// 响应体
type OrgResponse struct {
    ID       uint   `json:"id"`
    Name     string `json:"name"`
    ParentID *uint  `json:"parent_id"`
    Level    int    `json:"level"`
    OrgType  string `json:"org_type"`
}
```

**设计原则:**
- ✅ 不直接暴露数据库模型
- ✅ 响应体不包含敏感字段
- ✅ 使用Gin binding进行参数验证

#### 3. 业务逻辑 (internal/service/org.go)

```go
func (s *OrgService) Create(req dto.OrgRequest) (*model.Org, error) {
    // 业务规则验证
    if req.ParentID == nil && req.Level != 1 {
        return nil, errors.New("顶级组织的层级必须为1")
    }
    if req.ParentID != nil && req.Level == 1 {
        return nil, errors.New("非顶级组织的层级不能为1")
    }
    
    // 构建模型
    item := &model.Org{
        Name:     req.Name,
        ParentID: req.ParentID,
        Level:    req.Level,
        OrgType:  req.OrgType,
    }
    
    // 调用Repository持久化
    if err := s.repo.Create(item); err != nil {
        return nil, err
    }
    return item, nil
}
```

**关键业务规则:**
```
规则1: ParentID=NULL且Level=1 → 顶级组织 ✓
规则2: ParentID=NULL且Level≠1 → 违反规则 ✗
规则3: ParentID≠NULL且Level=1 → 违反规则 ✗
规则4: ParentID≠NULL且Level≠1 → 子级组织 ✓
```

#### 4. 数据访问层 (internal/repository/org.go)

```go
func (r *OrgRepository) List(orgID uint) ([]model.Org, error) {
    var items []model.Org
    query := r.db.Model(&model.Org{})
    
    // 权限隔离：按orgID筛选
    if orgID != 0 {
        query = query.Where("id = ? OR parent_id = ?", orgID, orgID)
    }
    
    err := query.Order("id desc").Find(&items).Error
    return items, err
}
```

**设计特点:**
- 权限隔离：Repository层自动携带orgID过滤条件
- 支持组织级别的数据隔离
- 返回该组织及其直接子组织

#### 5. HTTP处理器 (internal/handler/org.go)

```go
func (h *OrgHandler) List(c *gin.Context) {
    // 从中间件获取当前用户的orgID
    items, err := h.service.List(middleware.CurrentOrgID(c))
    if err != nil {
        c.JSON(http.StatusInternalServerError, gin.H{"message": err.Error()})
        return
    }
    c.JSON(http.StatusOK, gin.H{"data": items})
}

func (h *OrgHandler) Create(c *gin.Context) {
    var req dto.OrgRequest
    
    // 验证请求参数
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"message": err.Error()})
        return
    }
    
    // 调用Service处理
    item, err := h.service.Create(req)
    if err != nil {
        c.JSON(http.StatusInternalServerError, gin.H{"message": err.Error()})
        return
    }
    
    c.JSON(http.StatusCreated, gin.H{"data": item})
}
```

### 路由配置 (internal/router/router.go)

```go
func SetupRoutes(r *gin.Engine, handlers map[string]interface{}) {
    api := r.Group("/api")
    
    // 组织管理路由
    orgHandler := handlers["orgHandler"].(*handler.OrgHandler)
    api.GET("/orgs", orgHandler.List)           // 列表
    api.GET("/orgs/:id", orgHandler.Get)        // 详情
    api.POST("/orgs", orgHandler.Create)        // 创建
    api.PUT("/orgs/:id", orgHandler.Update)     // 更新
    api.DELETE("/orgs/:id", orgHandler.Delete)  // 删除
}
```

## 🎨 前端实现详解

### 前端目录结构

```
frontend/
├── src/
│   ├── views/
│   │   ├── home/
│   │   │   └── HomeView.vue           # 首页
│   │   ├── org/
│   │   │   └── OrgListView.vue        # 组织管理页面
│   │   ├── user/
│   │   │   └── UserListView.vue       # 党员管理页面
│   │   └── task/
│   │       └── TaskListView.vue       # 任务管理页面
│   ├── api/
│   │   ├── http.js                    # Axios HTTP客户端
│   │   ├── forms.js                   # 表单初始化数据
│   │   └── format.js                  # 数据格式化工具
│   ├── stores/
│   │   └── app.js                     # Pinia全局状态
│   ├── router/
│   │   └── index.js                   # Vue Router配置
│   ├── components/                    # 公共组件
│   ├── App.vue                        # 根组件
│   └── main.js                        # 入口文件
├── vite.config.js                     # Vite构建配置
├── index.html                         # HTML入口
└── package.json                       # 依赖管理
```

### 页面功能详解 (OrgListView.vue)

#### 页面结构

```vue
<template>
  <el-card>
    <!-- 页面头部：标题 + 操作按钮 -->
    <template #header>
      <div class="card-header">
        <span>组织列表</span>
        <div class="actions">
          <el-button type="primary" @click="openCreate">新增组织</el-button>
          <el-button @click="loadData">刷新</el-button>
        </div>
      </div>
    </template>

    <!-- 搜索和筛选区域 -->
    <div class="filters">
      <el-input v-model="filters.keyword" placeholder="搜索组织名称" clearable />
      <el-select v-model="filters.level" clearable placeholder="筛选层级">
        <el-option label="1级" :value="1" />
        <el-option label="2级" :value="2" />
        <el-option label="3级" :value="3" />
        <el-option label="4级" :value="4" />
      </el-select>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <!-- 数据表格 -->
    <el-table :data="filteredItems" v-loading="loading" border>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="组织名称" />
      <el-table-column prop="level" label="层级" width="100" />
      <el-table-column prop="org_type" label="类型" />
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

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="组织名称">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="父级组织">
          <el-select v-model="form.parent_id" placeholder="请选择">
            <el-option 
              v-for="option in parentSelectOptions" 
              :key="option.id ?? 'top'" 
              :label="option.name" 
              :value="option.id" 
            />
          </el-select>
        </el-form-item>
        <el-form-item label="层级">
          <el-input-number v-model="form.level" :min="1" :max="4" />
        </el-form-item>
        <el-form-item label="类型">
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
```

#### 核心脚本逻辑

```javascript
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/http'
import { emptyOrgForm } from '../../api/forms'

// ============ 数据状态 ============
const items = ref([])                      // 组织列表
const loading = ref(false)                 // 加载状态
const dialogVisible = ref(false)           // 弹窗显示状态
const dialogTitle = ref('新增组织')        // 弹窗标题
const editingId = ref(null)                // 编辑中的组织ID
const form = ref(emptyOrgForm())           // 表单数据

// ============ 计算属性 ============
// 过滤后的数据 (按名称和层级)
const filteredItems = computed(() => 
  items.value.filter(item => {
    const keyword = filters.value.keyword.trim()
    const matchesKeyword = !keyword || item.name?.includes(keyword)
    const matchesLevel = !filters.value.level || item.level === filters.value.level
    return matchesKeyword && matchesLevel
  })
)

// 父级组织名称映射表
const parentNameMap = computed(() => 
  Object.fromEntries(items.value.map(item => [item.id, item.name]))
)

// 父级选项 (用于下拉选择)
const parentSelectOptions = computed(() => [
  { id: null, name: '顶级党组织' },
  ...parentOptions.value.map(item => ({ id: item.id, name: item.name }))
])

// ============ 生命周期 ============
onMounted(loadData)  // 页面加载时获取数据

// ============ API调用 ============
const loadData = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/orgs')
    items.value = data.data || []
  } finally {
    loading.value = false
  }
}

// ============ 对话框操作 ============
const openCreate = () => {
  dialogTitle.value = '新增组织'
  editingId.value = null
  form.value = emptyOrgForm()
  dialogVisible.value = true
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
}

// ============ 表单提交 ============
const submitForm = async () => {
  // 验证
  if (!form.value.name.trim()) {
    ElMessage.warning('请输入组织名称')
    return
  }
  
  const payload = {
    ...form.value,
    parent_id: form.value.parent_id,
  }

  try {
    if (editingId.value) {
      // 更新
      await api.put(`/orgs/${editingId.value}`, payload)
      ElMessage.success('组织已更新')
    } else {
      // 创建
      await api.post('/orgs', payload)
      ElMessage.success('组织已创建')
    }
    dialogVisible.value = false
    await loadData()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

// ============ 删除操作 ============
const removeItem = async (row) => {
  await ElMessageBox.confirm(
    `确认删除组织"${row.name}"吗？`,
    '提示',
    { type: 'warning' }
  )
  await api.delete(`/orgs/${row.id}`)
  ElMessage.success('组织已删除')
  await loadData()
}
</script>
```

#### 样式设计

```css
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
```

### API客户端 (api/http.js)

```javascript
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080/api',
  timeout: 10000,
})

export default api
```

**配置说明:**
- 基础URL从环境变量读取，默认为 `http://localhost:8080/api`
- 请求超时设置为 10 秒

### 表单初始化 (api/forms.js)

```javascript
export const emptyOrgForm = () => ({
  name: '',
  parent_id: null,
  level: 1,
  org_type: '党委',
})
```

## 🔌 RESTful API 接口

### 基础信息

- **基础URL**: `http://localhost:8080/api`
- **认证方式**: Bearer Token (可选)
- **响应格式**: JSON

### 接口列表

#### 1. 获取组织列表

```http
GET /api/orgs
```

**响应示例:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "示范党委",
      "parent_id": null,
      "level": 1,
      "org_type": "党委"
    },
    {
      "id": 3,
      "name": "第一党支部",
      "parent_id": 1,
      "level": 2,
      "org_type": "支部"
    },
    {
      "id": 5,
      "name": "第一党小组",
      "parent_id": 3,
      "level": 3,
      "org_type": "党小组"
    }
  ]
}
```

#### 2. 获取单个组织详情

```http
GET /api/orgs/:id
```

**参数:**
- `id` (path): 组织ID

**响应示例:**
```json
{
  "data": {
    "id": 1,
    "name": "示范党委",
    "parent_id": null,
    "level": 1,
    "org_type": "党委"
  }
}
```

#### 3. 创建组织

```http
POST /api/orgs
Content-Type: application/json

{
  "name": "新增支部",
  "parent_id": 1,
  "level": 2,
  "org_type": "支部"
}
```

**请求参数:**
| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| name | string | ✓ | 组织名称，长度1-100 |
| parent_id | number | | 父级组织ID (顶级组织为null) |
| level | number | ✓ | 组织层级 (1-4) |
| org_type | string | ✓ | 组织类型 |

**业务规则:**
- parent_id为null时，level必须为1
- parent_id不为null时，level不能为1

**响应示例:**
```json
{
  "data": {
    "id": 7,
    "name": "新增支部",
    "parent_id": 1,
    "level": 2,
    "org_type": "支部"
  }
}
```

#### 4. 更新组织

```http
PUT /api/orgs/:id
Content-Type: application/json

{
  "name": "更新的支部名称",
  "parent_id": 1,
  "level": 2,
  "org_type": "支部"
}
```

**参数:** 同创建接口

**响应示例:**
```json
{
  "data": {
    "id": 7,
    "name": "更新的支部名称",
    "parent_id": 1,
    "level": 2,
    "org_type": "支部"
  }
}
```

#### 5. 删除组织

```http
DELETE /api/orgs/:id
```

**参数:**
- `id` (path): 组织ID

**响应示例:**
```json
{
  "message": "deleted"
}
```

### 错误响应

```json
{
  "message": "错误信息描述"
}
```

**常见错误:**
- 400: 请求参数无效
- 404: 资源不存在
- 500: 服务器错误

## 🚀 快速开始

### 前置条件

- Node.js 14+ 或 Go 1.23+
- MySQL 8.0+
- Git

### 环境配置

#### 数据库连接信息

```
Host: 172.23.72.148
Port: 3306
Database: party_db
User: djapp
Password: Wmjf2la!
```

### 后端启动

```bash
# 1. 进入后端目录
cd backend

# 2. 安装依赖
go mod download

# 3. 初始化数据库 (首次运行)
# 系统会自动执行 schema.sql 和 seed.go

# 4. 启动服务器
go run cmd/server/main.go

# 输出: [GIN-debug] Listening and serving HTTP on :8080
```

**验证后端:**
```bash
curl http://localhost:8080/api/orgs
```

### 前端启动

```bash
# 1. 进入前端目录
cd frontend

# 2. 安装依赖
npm install

# 3. 配置环境变量 (可选)
# 复制 .env.example 到 .env.local
cp .env.example .env.local

# 4. 启动开发服务器
npm run dev

# 输出: VITE v X.X.X ready in XXX ms
# → Local:     http://localhost:5173/
```

**访问应用:**
- 打开浏览器访问 `http://localhost:5173`
- 点击导航栏中的"组织管理"

## 📊 初始化数据

系统启动时自动执行初始化脚本，创建如下示例数据:

### 组织结构

```
示范党委 (ID=1, Level=1, OrgType=党委)
├── 第一党支部 (ID=3, Level=2, ParentID=1, OrgType=支部)
│   └── 第一党小组 (ID=5, Level=3, ParentID=3, OrgType=党小组)
└── 第二党支部 (ID=4, Level=2, ParentID=1, OrgType=支部)

市级党委 (ID=2, Level=1, OrgType=党委)
└── 市级第一党支部 (ID=6, Level=2, ParentID=2, OrgType=支部)
```

### 关联数据

**党员 (sys_user):**
- 张三 (ID=1): 所属第一党支部 (OrgID=3)
- 李四 (ID=2): 所属第二党支部 (OrgID=4)
- 王五 (ID=3): 所属第一党小组 (OrgID=5)

**任务 (t_task):**
- 示例党建任务: 第一党支部 (OrgID=3)
- 学习党章任务: 第二党支部 (OrgID=4)
- 志愿服务活动: 第一党小组 (OrgID=5)

## 🔐 权限和隔离

### 用户上下文

通过Gin中间件注入用户上下文:

```go
middleware.CurrentOrgID(c)  // 获取当前用户所属的orgID
middleware.CurrentUserID(c) // 获取当前用户ID
```

### 数据隔离

Repository层自动携带orgID过滤条件:

```go
// 只返回当前用户权限内的组织
func (r *OrgRepository) List(orgID uint) ([]model.Org, error) {
    query := r.db.Model(&model.Org{})
    if orgID != 0 {
        query = query.Where("id = ? OR parent_id = ?", orgID, orgID)
    }
    return items, query.Find(&items).Error
}
```

### 权限规则

- 用户只能查看和操作其所属组织及其下属组织
- 管理员可查看和操作所有组织
- 删除组织时需要检查是否有关联的用户或任务

## 🛠️ 故障排查

### 常见问题

#### 1. 前端无法连接后端

**问题:** 前端报错 "Network Error" 或 "CORS error"

**解决方案:**
```bash
# 检查后端是否运行
curl http://localhost:8080/api/orgs

# 检查VITE_API_BASE_URL环境变量
cat frontend/.env.local  # 应包含 VITE_API_BASE_URL=http://localhost:8080/api

# 检查后端CORS配置
# backend/internal/router/router.go 应设置允许前端跨域请求
```

#### 2. 数据库连接失败

**问题:** 后端启动失败，提示 "connection refused"

**解决方案:**
```bash
# 检查MySQL服务是否运行
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" -e "SELECT 1"

# 检查数据库是否存在
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" -e "SHOW DATABASES LIKE 'party_db'"

# 导入schema.sql
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" party_db < backend/schema.sql
```

#### 3. 页面无法加载

**问题:** 访问 http://localhost:5173 显示空白

**解决方案:**
```bash
# 检查Node版本
node --version  # 应为 14.0.0 或以上

# 重新安装依赖
rm -rf frontend/node_modules
npm install

# 清除缓存重新启动
npm run dev -- --force
```

## 📈 性能优化建议

1. **索引优化**
   - 为 `parent_id` 字段添加索引 (已实现)
   - 为高频查询字段添加组合索引

2. **查询优化**
   - 使用分页加载大量组织
   - 缓存组织名称映射表
   - 使用树形缓存算法

3. **前端优化**
   - 虚拟滚动处理大列表
   - 懒加载子组织
   - 缓存API响应

## 🔄 后续扩展

### 已规划功能

- [ ] 组织树形级联选择器
- [ ] 组织批量导入 (Excel/CSV)
- [ ] 组织导出 (Excel/PDF)
- [ ] 组织变更审计日志
- [ ] 组织移动/复制功能
- [ ] 组织成员人数统计
- [ ] 组织权限模型
- [ ] 组织角色管理

### 扩展指南

**添加新字段示例:**

```go
// 1. 更新模型
type Org struct {
    // 现有字段...
    Phone  string  // 新增：组织联系电话
    Leader string  // 新增：组织负责人
}

// 2. 更新DTO
type OrgRequest struct {
    // 现有字段...
    Phone  string
    Leader string
}

// 3. 更新数据库
ALTER TABLE sys_org ADD COLUMN phone VARCHAR(20);
ALTER TABLE sys_org ADD COLUMN leader VARCHAR(100);

// 4. 更新前端表单
<el-form-item label="联系电话">
  <el-input v-model="form.phone" />
</el-form-item>
```

## 📚 相关文档

- [数据库设计文档](./database-design.md)
- [API文档](./api-reference.md)
- [前端开发指南](./frontend-guide.md)
- [后端开发指南](./backend-guide.md)

## 📝 版本历史

### v1.0.0 (2024-01-01)

初始版本功能:
- ✅ 组织树形结构管理
- ✅ 4级组织层级支持
- ✅ CRUD完整操作
- ✅ 搜索和筛选功能
- ✅ 权限隔离和数据安全
- ✅ RESTful API接口
- ✅ 示例数据初始化

## 📞 技术支持

如有问题或建议，请联系开发团队或提交Issue。

---

**最后更新**: 2024-01-01  
**文档版本**: v1.0.0  
**作者**: Claude (Copilot)
