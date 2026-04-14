# 组织管理模块 - 快速参考

## 📍 系统org表中的字段说明

### sys_org 表结构

| 字段 | 类型 | 描述 | 示例 |
|------|------|------|------|
| `id` | BIGINT | 组织唯一ID | 1 |
| `name` | VARCHAR(100) | 组织名称 | "示范党委" |
| `parent_id` | BIGINT (NULL) | 父级组织ID | 1 或 NULL(顶级) |
| `level` | INT | 层级 1-4 | 1, 2, 3, 4 |
| `org_type` | VARCHAR(50) | 组织类型 | "党委", "支部", "党小组" |
| `created_at` | DATETIME | 创建时间 | 2024-01-01 10:00:00 |
| `updated_at` | DATETIME | 更新时间 | 2024-01-01 10:00:00 |

### 树形结构示意

```
id=1 "示范党委" (parent_id=NULL, level=1)
├── id=3 "第一党支部" (parent_id=1, level=2)
│   └── id=5 "第一党小组" (parent_id=3, level=3)
└── id=4 "第二党支部" (parent_id=1, level=2)

id=2 "市级党委" (parent_id=NULL, level=1)
└── id=6 "市级第一党支部" (parent_id=2, level=2)
```

## 🔧 后端API快速参考

### 基础URL: `http://localhost:8080/api`

| 方法 | 端点 | 功能 | 请求体 |
|------|------|------|--------|
| **GET** | `/orgs` | 获取所有组织 | - |
| **GET** | `/orgs/:id` | 获取单个组织 | - |
| **POST** | `/orgs` | 创建组织 | OrgRequest |
| **PUT** | `/orgs/:id` | 更新组织 | OrgRequest |
| **DELETE** | `/orgs/:id` | 删除组织 | - |

### 请求示例

#### 创建顶级组织
```bash
curl -X POST http://localhost:8080/api/orgs \
  -H "Content-Type: application/json" \
  -d '{
    "name": "新党委",
    "parent_id": null,
    "level": 1,
    "org_type": "党委"
  }'
```

#### 创建子级组织
```bash
curl -X POST http://localhost:8080/api/orgs \
  -H "Content-Type: application/json" \
  -d '{
    "name": "新支部",
    "parent_id": 1,
    "level": 2,
    "org_type": "支部"
  }'
```

#### 更新组织
```bash
curl -X PUT http://localhost:8080/api/orgs/3 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "支部(更新)",
    "parent_id": 1,
    "level": 2,
    "org_type": "支部"
  }'
```

#### 删除组织
```bash
curl -X DELETE http://localhost:8080/api/orgs/3
```

## 💾 数据库SQL查询

### 查询所有顶级组织
```sql
SELECT * FROM sys_org WHERE parent_id IS NULL AND level = 1;
```

### 查询指定父级下的所有子组织
```sql
SELECT * FROM sys_org WHERE parent_id = 1 ORDER BY id;
```

### 查询完整的树形结构 (递归)
```sql
WITH RECURSIVE org_tree AS (
  -- 初始查询：获取顶级组织
  SELECT id, name, parent_id, level, org_type FROM sys_org WHERE parent_id IS NULL
  UNION ALL
  -- 递归查询：获取各级子组织
  SELECT o.id, o.name, o.parent_id, o.level, o.org_type 
  FROM sys_org o
  INNER JOIN org_tree t ON o.parent_id = t.id
)
SELECT * FROM org_tree ORDER BY level, id;
```

### 统计各层级组织数量
```sql
SELECT level, COUNT(*) as count FROM sys_org GROUP BY level;
```

### 查询某个组织的所有上级组织 (递归向上)
```sql
WITH RECURSIVE parent_chain AS (
  SELECT id, name, parent_id, level FROM sys_org WHERE id = 5  -- 指定组织ID
  UNION ALL
  SELECT o.id, o.name, o.parent_id, o.level 
  FROM sys_org o
  INNER JOIN parent_chain p ON o.id = p.parent_id
)
SELECT * FROM parent_chain ORDER BY level DESC;
```

### 查询某个组织的所有下级组织 (递归向下)
```sql
WITH RECURSIVE sub_orgs AS (
  SELECT id, name, parent_id, level FROM sys_org WHERE id = 1  -- 指定组织ID
  UNION ALL
  SELECT o.id, o.name, o.parent_id, o.level 
  FROM sys_org o
  INNER JOIN sub_orgs s ON o.parent_id = s.id
)
SELECT * FROM sub_orgs ORDER BY level;
```

## 📋 业务规则总结

### 创建/更新组织的规则

| 场景 | parent_id | level | 合法性 | 说明 |
|------|-----------|-------|--------|------|
| 顶级党委 | NULL | 1 | ✅ | 树根节点 |
| 二级支部 | 1 | 2 | ✅ | 标准子节点 |
| 三级党小组 | 3 | 3 | ✅ | 标准子节点 |
| 四级分小组 | 5 | 4 | ✅ | 最底层节点 |
| **错误**:孤立 | NULL | 2 | ❌ | parent为NULL时level必须为1 |
| **错误**:悬挂 | 1 | 1 | ❌ | parent非NULL时level不能为1 |

### 验证逻辑 (伪代码)
```go
if ParentID == null && Level != 1 {
    return error("顶级组织的level必须为1")
}
if ParentID != null && Level == 1 {
    return error("非顶级组织的level不能为1")
}
```

## 🖥️ 前端页面元素

### OrgListView.vue 功能清单

#### 搜索和筛选
- 🔍 **组织名称搜索**: 模糊匹配 (includes)
- 📊 **层级筛选**: 下拉选择 1-4 级
- 🔄 **快速操作**: "新建顶级组织"
- ↩️ **重置**: 清空所有过滤条件

#### 表格显示
- **ID**: 组织唯一标识
- **名称**: 可点击编辑
- **层级**: 1-4 级标识
- **类型**: 党委/支部/党小组 等
- **父级**: 显示父级组织名称
- **操作**: 编辑/删除按钮

#### 对话框 (el-dialog)
- **新增模式**: 创建新组织
- **编辑模式**: 修改现有组织
- **验证**: 名称和类型必填，level 1-4
- **智能默认**: 自动设置合理的parent_id和level

#### 状态管理
- ✅ 数据加载中 (loading状态)
- ✅ 对话框打开/关闭
- ✅ 编辑vs新增模式切换
- ✅ 表单数据验证

## 🔄 前后端交互流程

### 创建流程
```
前端页面
  ↓ (用户点击"新增")
打开对话框
  ↓ (用户填写表单)
表单验证
  ↓ (用户点击"保存")
POST /api/orgs (OrgRequest)
  ↓
后端验证业务规则
  ↓
GORM保存到数据库
  ↓
返回OrgResponse
  ↓
前端显示成功提示
  ↓
刷新列表
```

### 编辑流程
```
前端页面
  ↓ (用户点击"编辑")
加载组织数据到表单
  ↓
打开对话框
  ↓ (用户修改表单)
修改验证
  ↓ (用户点击"保存")
PUT /api/orgs/:id (OrgRequest)
  ↓
后端验证和更新
  ↓
返回更新后的对象
  ↓
前端显示成功提示
  ↓
刷新列表
```

### 删除流程
```
前端页面
  ↓ (用户点击"删除")
弹出确认对话框
  ↓ (用户确认)
DELETE /api/orgs/:id
  ↓
后端删除数据库记录
  ↓
返回成功响应
  ↓
前端显示成功提示
  ↓
刷新列表
```

## 🎯 常用代码片段

### 后端：查询用户所属组织及其下属组织
```go
func (s *OrgService) GetUserOrgTree(userOrgID uint) ([]model.Org, error) {
    // 方式1：使用中间件注入的orgID
    return s.repo.List(userOrgID)
}
```

### 前端：获取组织列表
```javascript
import api from '../../api/http'

const loadOrgList = async () => {
  try {
    const { data } = await api.get('/orgs')
    return data.data || []
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '加载失败')
  }
}
```

### 前端：创建组织
```javascript
const createOrg = async (orgData) => {
  try {
    const { data } = await api.post('/orgs', orgData)
    ElMessage.success('组织已创建')
    return data.data
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '创建失败')
  }
}
```

### 前端：获取父级组织列表
```javascript
const parentSelectOptions = computed(() => [
  { id: null, name: '顶级党组织' },
  ...items.value
    .filter(item => item.id !== editingId.value)
    .map(item => ({ id: item.id, name: item.name }))
])
```

## 🚀 启动命令速查

### 后端
```bash
cd backend && go run cmd/server/main.go
```

### 前端
```bash
cd frontend && npm install && npm run dev
```

### 完整启动 (两个终端)

**终端1 - 后端:**
```bash
cd f:\djapp3\backend
go run cmd/server/main.go
# 访问: http://localhost:8080/api/orgs
```

**终端2 - 前端:**
```bash
cd f:\djapp3\frontend
npm install
npm run dev
# 访问: http://localhost:5173/orgs
```

## 📞 端口号速查

| 服务 | 地址 | 用途 |
|------|------|------|
| **后端API** | http://localhost:8080 | REST API 服务 |
| **前端开发** | http://localhost:5173 | Vue 开发服务器 |
| **数据库** | 172.23.72.148:3306 | MySQL 数据库 |

## 🔗 快速导航

- 📖 [完整文档](./org-management.md)
- 🗄️ [数据库DDL](../backend/schema.sql)
- 🎨 [前端组件](../frontend/src/views/org/OrgListView.vue)
- 🔌 [后端处理器](../backend/internal/handler/org.go)
- 📝 [初始化数据](../backend/internal/bootstrap/seed.go)

---

**快速参考版本**: v1.0  
**最后更新**: 2024-01-01
