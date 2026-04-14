# 系统总览 - 党建任务管理系统

## 📊 系统架构

```
┌────────────────────────────────────────────────────────────────────┐
│                        用户浏览器                                   │
└────────────────────────────────────────────────────────────────────┘
                                ↓
                        ↓ HTTP(S) ↑
                                
┌────────────────────────────────────────────────────────────────────┐
│                       前端应用 (Vue 3)                              │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  路由器 (Vue Router)                  页面组件                      │
│  ├─ /                                ├─ HomeView.vue              │
│  ├─ /orgs        →  组织管理   →   OrgListView.vue           │
│  ├─ /users       →  党员管理   →   UserListView.vue          │
│  └─ /tasks       →  任务管理   →   TaskListView.vue          │
│                                                                    │
│  状态管理 (Pinia) ← → 本地存储                                     │
│                                                                    │
│  HTTP客户端 (Axios) → API 调用                                     │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
                                ↓
                        RESTful API (JSON)
                                ↓
┌────────────────────────────────────────────────────────────────────┐
│                    后端服务 (Go + Gin)                              │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  API 路由                                                          │
│  ├─ GET    /api/orgs              获取所有组织                    │
│  ├─ POST   /api/orgs              创建组织                        │
│  ├─ PUT    /api/orgs/:id          更新组织                        │
│  ├─ DELETE /api/orgs/:id          删除组织                        │
│  ├─ GET    /api/users             获取用户列表                    │
│  ├─ POST   /api/users             创建用户                        │
│  ├─ GET    /api/tasks             获取任务列表                    │
│  ├─ POST   /api/tasks             创建任务                        │
│  └─ ...                                                            │
│                                                                    │
│  分层架构                                                          │
│  ┌─ Handler 层 (HTTP 处理)                                        │
│  │  ├─ OrgHandler                                                │
│  │  ├─ UserHandler                                               │
│  │  └─ TaskHandler                                               │
│  │                                                                │
│  ├─ Service 层 (业务逻辑)                                        │
│  │  ├─ OrgService                                                │
│  │  ├─ UserService                                               │
│  │  └─ TaskService                                               │
│  │                                                                │
│  ├─ Repository 层 (数据访问)                                     │
│  │  ├─ OrgRepository                                             │
│  │  ├─ UserRepository                                            │
│  │  └─ TaskRepository                                            │
│  │                                                                │
│  └─ Middleware 层 (请求拦截)                                    │
│     ├─ Auth (认证)                                                │
│     ├─ AuthContext (用户上下文)                                   │
│     └─ CORS (跨域)                                                │
│                                                                    │
│  DTO 转换层 (Request/Response 对象)                               │
│  ├─ OrgRequest / OrgResponse                                      │
│  ├─ UserRequest / UserResponse                                    │
│  └─ TaskRequest / TaskResponse                                    │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
                                ↓
                        SQL (GORM ORM)
                                ↓
┌────────────────────────────────────────────────────────────────────┐
│                   MySQL 数据库 (party_db)                          │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  核心表                                                            │
│  ├─ sys_org          ← 组织树形结构                              │
│  │                                                                │
│  ├─ sys_user         ← 党员/用户信息                             │
│  │  └─ org_id → 引用 sys_org.id                                 │
│  │                                                                │
│  ├─ t_task           ← 任务信息                                  │
│  │  ├─ org_id → 引用 sys_org.id                                 │
│  │  └─ assignee_id → 引用 sys_user.id                           │
│  │                                                                │
│  ├─ t_attachment     ← 附件存储                                  │
│  │  └─ task_id → 引用 t_task.id                                 │
│  │                                                                │
│  └─ t_task_log       ← 操作审计日志                              │
│     └─ task_id → 引用 t_task.id                                 │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

## 🏢 核心业务模块

### 1. 组织管理 (Org)

**功能:**
- 树形组织结构 (1-4级)
- 组织CRUD操作
- 搜索和筛选
- 权限隔离

**主要文件:**
```
Frontend:  frontend/src/views/org/OrgListView.vue
Backend:   backend/internal/{model,dto,handler,service,repository}/org.go
Database:  sys_org 表
API:       /api/orgs
```

**数据模型:**
```
sys_org {
  id: 1,
  name: "示范党委",
  parent_id: null,
  level: 1,
  org_type: "党委"
}
```

### 2. 党员管理 (User)

**功能:**
- 党员信息管理
- 所属组织关联
- 职位和角色管理
- 搜索和筛选

**主要文件:**
```
Frontend:  frontend/src/views/user/UserListView.vue
Backend:   backend/internal/{model,dto,handler,service,repository}/user.go
Database:  sys_user 表
API:       /api/users
```

**数据模型:**
```
sys_user {
  id: 1,
  name: "张三",
  employee_no: "DJ001",
  org_id: 3,           # 引用 sys_org
  party_role: "组织委员",
  job_title: "专员",
  gender: "男",
  education: "本科"
}
```

### 3. 任务管理 (Task)

**功能:**
- 任务发布和分配
- 任务进度跟踪
- 红名预警机制
- 附件上传和预览
- 操作审计日志

**主要文件:**
```
Frontend:  frontend/src/views/task/TaskListView.vue
Backend:   backend/internal/{model,dto,handler,service,repository}/task.go
Database:  t_task, t_attachment, t_task_log 表
API:       /api/tasks
```

**数据模型:**
```
t_task {
  id: 1,
  title: "示例党建任务",
  description: "任务描述",
  task_type: "A",         # 任务类型
  status: "待领用",        # 状态
  warning_level: 0,       # 预警等级 (0=绿, 1=黄, 2=红)
  org_id: 3,              # 引用 sys_org
  assignee_id: 1,         # 引用 sys_user
  deadline_at: "2024-01-04 12:00:00",
  created_at: "2024-01-01 10:00:00"
}
```

## 📱 前端技术栈详解

### 项目结构

```
frontend/
├── src/
│   ├── main.js                # 应用入口
│   ├── App.vue                # 根组件
│   ├── index.html             # HTML 模板
│   │
│   ├── views/                 # 页面组件
│   │   ├── home/
│   │   │   └── HomeView.vue   # 首页
│   │   ├── org/
│   │   │   └── OrgListView.vue    # 组织管理
│   │   ├── user/
│   │   │   └── UserListView.vue   # 党员管理
│   │   └── task/
│   │       └── TaskListView.vue   # 任务管理
│   │
│   ├── components/            # 公共组件
│   │   └── (可扩展)
│   │
│   ├── router/                # 路由配置
│   │   └── index.js
│   │
│   ├── stores/                # Pinia 状态管理
│   │   └── app.js
│   │
│   └── api/                   # API 客户端
│       ├── http.js            # Axios 实例
│       ├── forms.js           # 表单初始化
│       └── format.js          # 数据格式化
│
├── vite.config.js             # Vite 配置
├── package.json               # 依赖管理
└── .env.example               # 环境变量
```

### 核心技术

| 技术 | 用途 | 版本 |
|------|------|------|
| **Vue 3** | 前端框架 | 3.x |
| **Vite** | 构建工具 | 4.x |
| **Element Plus** | UI 组件库 | 2.x |
| **Pinia** | 状态管理 | 2.x |
| **Vue Router** | 路由管理 | 4.x |
| **Axios** | HTTP 客户端 | 1.x |

### 页面示例: OrgListView.vue

**功能流程:**

```
┌─ 页面加载
│  └─ onMounted() 触发 loadData()
│
├─ API 请求
│  └─ GET /api/orgs → 获取组织列表
│
├─ 数据绑定
│  ├─ items.value = 组织列表
│  └─ 计算属性更新:
│     ├─ filteredItems (搜索+筛选)
│     └─ parentNameMap (ID→名称映射)
│
├─ 用户交互
│  ├─ 搜索: keyword filter
│  ├─ 筛选: level filter
│  ├─ 新增: openCreate() → dialog
│  ├─ 编辑: openEdit(row) → dialog
│  └─ 删除: removeItem(row) → 确认 → API
│
└─ 表单提交
   └─ submitForm()
      ├─ 验证 (名称、类型不为空)
      ├─ 判断 (编辑 vs 新增)
      ├─ API 请求
      │  ├─ PUT /api/orgs/:id (编辑)
      │  └─ POST /api/orgs (新增)
      └─ 刷新列表
```

## ⚙️ 后端技术栈详解

### 项目结构

```
backend/
├── cmd/
│   └── server/
│       └── main.go            # 应用入口
│
├── internal/
│   ├── bootstrap/             # 初始化
│   │   ├── migrate.go         # 数据库迁移
│   │   └── seed.go            # 示例数据
│   │
│   ├── config/                # 配置管理
│   │   └── config.go
│   │
│   ├── middleware/            # 中间件
│   │   ├── auth.go
│   │   ├── auth_context.go    # 用户上下文
│   │   └── cors.go
│   │
│   ├── model/                 # 数据模型
│   │   ├── base.go            # 基础模型
│   │   ├── org.go
│   │   ├── user.go
│   │   └── task.go
│   │
│   ├── dto/                   # 数据传输对象
│   │   ├── org.go
│   │   ├── user.go
│   │   └── task.go
│   │
│   ├── handler/               # HTTP 处理器
│   │   ├── org.go
│   │   ├── user.go
│   │   └── task.go
│   │
│   ├── service/               # 业务逻辑
│   │   ├── org.go
│   │   ├── user.go
│   │   └── task.go
│   │
│   ├── repository/            # 数据访问
│   │   ├── org.go
│   │   ├── user.go
│   │   └── task.go
│   │
│   └── router/                # 路由配置
│       └── router.go
│
├── schema.sql                 # 数据库 DDL
├── go.mod                     # 依赖声明
├── go.sum                     # 依赖锁定
└── .env.example               # 环境变量
```

### 核心技术

| 技术 | 用途 | 版本 |
|------|------|------|
| **Go** | 编程语言 | 1.23+ |
| **Gin** | Web 框架 | 1.x |
| **GORM** | ORM 框架 | 1.x |
| **MySQL** | 数据库 | 8.0+ |

### 分层架构

```
HTTP 请求
    ↓
┌─ Handler 层
│  ├─ 参数绑定 (ShouldBindJSON)
│  ├─ 参数验证 (binding tags)
│  ├─ 调用 Service
│  └─ 返回 Response
│   ↓
├─ Service 层
│  ├─ 业务逻辑验证
│  ├─ 数据流程处理
│  ├─ 调用 Repository
│  └─ 返回 DTO
│   ↓
├─ Repository 层
│  ├─ 权限检查 (orgID 隔离)
│  ├─ 数据库查询 (GORM)
│  ├─ 错误处理
│  └─ 返回 Model
│   ↓
└─ GORM + MySQL
   ├─ 执行 SQL
   └─ 返回结果
```

### 分层对应代码

**1. Handler 层** (处理HTTP请求)
```go
func (h *OrgHandler) Create(c *gin.Context) {
    var req dto.OrgRequest
    // 1. 绑定和验证
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(400, gin.H{"message": err.Error()})
        return
    }
    // 2. 调用 Service
    item, err := h.service.Create(req)
    // 3. 返回响应
    c.JSON(201, gin.H{"data": item})
}
```

**2. Service 层** (业务逻辑)
```go
func (s *OrgService) Create(req dto.OrgRequest) (*model.Org, error) {
    // 1. 业务规则检查
    if req.ParentID == nil && req.Level != 1 {
        return nil, errors.New("顶级组织的layer必须为1")
    }
    // 2. 构建模型
    item := &model.Org{Name: req.Name, ...}
    // 3. 调用 Repository
    if err := s.repo.Create(item); err != nil {
        return nil, err
    }
    return item, nil
}
```

**3. Repository 层** (数据访问)
```go
func (r *OrgRepository) Create(item *model.Org) error {
    // 1. 权限检查 (通过中间件注入的 orgID)
    // 2. 执行 GORM 操作
    return r.db.Create(item).Error
}
```

## 🗄️ 数据库关系图

```
┌─────────────────┐
│    sys_org      │
├─────────────────┤
│ id (PK)         │
│ name            │
│ parent_id (FK)──┼─────┐
│ level           │     │
│ org_type        │     │ (树形关系)
│ created_at      │     │
│ updated_at      │     │
└─────────────────┘     │
        ↑────────────────┘


        ┌──────────────┐
        │   sys_user   │
        ├──────────────┤
        │ id (PK)      │
        │ name         │
        │ employee_no  │
    ┌───┤ org_id (FK)  ├───┐
    │   │ party_role   │   │ (关联)
    │   │ job_title    │   │
    │   │ ...          │   │
    │   └──────────────┘   │
    │                      ↓
    │            ┌─────────────────┐
    │            │ sys_org         │
    │            │ (组织表)         │
    │            └─────────────────┘
    │
    └───────────────┐
                    │
        ┌───────────┴──────────┐
        │                      │
    ┌───▼──────────┐   ┌──────▼────────┐
    │   t_task     │   │ t_attachment  │
    ├──────────────┤   ├───────────────┤
    │ id (PK)      │   │ id (PK)       │
    │ title        │   │ task_id (FK)─┐│
    │ description  │   │ file_name    ││
    │ task_type    │   │ file_url     ││
    │ status       │   │ file_type    ││
    │ org_id (FK)─┐│   │ ...          ││
    │ assignee_id │├─┐ └───────────────┘│
    │ (FK)        ││ │                   │
    │ deadline_at ││ │ ┌────────────────┘
    │ ...         ││ │ │ (关联)
    └─────────────┘│ │ │
                   │ └─┼──────┐
            (关联)  │    │      │
                   │    │      ▼
            ┌──────┴────┤  ┌──────────────┐
            │ 用户      │  │  t_task_log  │
            │ (操作人)  │  ├──────────────┤
            │           │  │ id (PK)      │
            └───────────┘  │ task_id (FK) │
                           │ action       │
                           │ operator_id  │
                           │ detail       │
                           │ ...          │
                           └──────────────┘
```

## 🔐 权限隔离机制

### 用户上下文注入

```go
// 中间件: 从请求中提取用户信息
middleware.AuthContext(c)
  ├─ 从 Token 解析用户信息
  └─ 存储到 Gin Context 中

// 业务代码: 从 Context 获取用户信息
orgID := middleware.CurrentOrgID(c)    // 获取用户所属组织ID
userID := middleware.CurrentUserID(c)  // 获取用户ID

// Repository 层: 自动过滤
repository.List(orgID)
  └─ 只返回该组织及其子组织的数据
```

### 示例: OrgRepository.List()

```go
func (r *OrgRepository) List(orgID uint) ([]model.Org, error) {
    var items []model.Org
    query := r.db.Model(&model.Org{})
    
    // 权限隔离: 用户只能查看其所属组织
    if orgID != 0 {
        query = query.Where("id = ? OR parent_id = ?", orgID, orgID)
    }
    
    return items, query.Find(&items).Error
}
```

## 📊 初始化数据流

```
应用启动
  ├─ 加载数据库配置
  ├─ 连接 MySQL
  ├─ 执行数据库迁移 (bootstrap/migrate.go)
  │  └─ 执行 schema.sql (创建表结构)
  ├─ 检查是否已有数据
  ├─ 执行数据初始化 (bootstrap/seed.go)
  │  ├─ 创建 2 个顶级组织
  │  │  ├─ 示范党委 (ID=1)
  │  │  └─ 市级党委 (ID=2)
  │  ├─ 创建 3 个二级支部
  │  │  ├─ 第一党支部 (ParentID=1)
  │  │  ├─ 第二党支部 (ParentID=1)
  │  │  └─ 市级第一党支部 (ParentID=2)
  │  ├─ 创建 1 个三级党小组
  │  │  └─ 第一党小组 (ParentID=3)
  │  ├─ 创建 3 个党员
  │  ├─ 创建 3 个任务
  │  └─ 关联任务到党员
  └─ 启动 HTTP 服务器 (监听 :8080)
```

## 🔄 API 调用流程示例: 创建组织

### 前端流程
```
用户点击"新增组织"按钮
  ↓
打开编辑对话框
  ↓
用户填写表单
  - name: "新支部"
  - parent_id: 1
  - level: 2
  - org_type: "支部"
  ↓
用户点击"保存"按钮
  ↓
validateForm() - 前端验证
  ├─ name 不为空 ✓
  ├─ org_type 不为空 ✓
  └─ level 在 1-4 范围内 ✓
  ↓
api.post('/orgs', payload)
  └─ POST http://localhost:8080/api/orgs
     Content-Type: application/json
     
     {
       "name": "新支部",
       "parent_id": 1,
       "level": 2,
       "org_type": "支部"
     }
```

### 后端流程
```
Gin 收到 POST /api/orgs 请求
  ↓
OrgHandler.Create()
  ├─ c.ShouldBindJSON(&dto.OrgRequest)
  │  └─ 绑定并验证参数 (binding tags)
  ├─ h.service.Create(req)
  │  ↓
  │  OrgService.Create()
  │  ├─ 业务规则检查
  │  │  ├─ ParentID != null && Level == 1 ? → 错误
  │  │  └─ ParentID == null && Level != 1 ? → 错误
  │  ├─ 构建 model.Org 对象
  │  ├─ h.repo.Create(org)
  │  │  ↓
  │  │  OrgRepository.Create()
  │  │  ├─ 权限检查 (通过中间件注入的 orgID)
  │  │  ├─ db.Create(org)
  │  │  │  ↓
  │  │  │  GORM 执行 INSERT 语句
  │  │  │  ↓
  │  │  │  INSERT INTO sys_org (...) VALUES (...)
  │  │  │  ↓
  │  │  │  MySQL 返回新 ID (自增)
  │  │  │
  │  │  └─ 返回 error (nil 成功)
  │  │
  │  └─ 返回 *model.Org
  │
  └─ c.JSON(201, gin.H{"data": org})
     ↓
     返回 HTTP 201 Created
```

### 前端接收
```
Response: 
{
  "data": {
    "id": 7,
    "name": "新支部",
    "parent_id": 1,
    "level": 2,
    "org_type": "支部"
  }
}
  ↓
ElMessage.success("组织已创建")
  ↓
刷新列表 (loadData())
  └─ GET /api/orgs → 更新 items.value
      ↓
    重新渲染表格
      ↓
    新增的组织出现在列表中
```

---

**系统总览文档版本**: v1.0  
**最后更新**: 2024-01-01
