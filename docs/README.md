# 党建任务管理系统 - 文档中心

欢迎来到党建任务管理系统的完整文档。本文档中心提供了系统的全面介绍、技术栈说明、API文档和快速开始指南。

## 📚 文档导航

### 🎯 快速入门

1. **[快速参考 (Quick Reference)](./quick-reference.md)** - ⭐ 从这里开始
   - 30秒了解系统概况
   - 常用API速查表
   - 数据库字段说明
   - 启动命令速览

2. **[系统总览 (System Overview)](./SYSTEM_OVERVIEW.md)** - 系统架构图解
   - 完整的系统架构
   - 分层设计详解
   - 数据库关系图
   - API调用流程
   - 权限隔离机制

3. **[组织管理详细文档 (Org Management)](./org-management.md)** - 深度学习
   - 完整的功能说明
   - 代码示例详解
   - 业务规则说明
   - 故障排查指南
   - 扩展建议

## 🏗️ 系统架构速览

```
浏览器 (http://localhost:5173)
    ↓ HTTP (JSON)
前端 (Vue 3 + Element Plus)
    ├─ 组织管理页面
    ├─ 党员管理页面
    └─ 任务管理页面
    ↓ RESTful API
后端 (Go + Gin + GORM)
    ├─ Handler 层
    ├─ Service 层
    └─ Repository 层
    ↓ SQL
MySQL 数据库
    ├─ sys_org (组织表)
    ├─ sys_user (党员表)
    ├─ t_task (任务表)
    ├─ t_attachment (附件表)
    └─ t_task_log (审计表)
```

## 🗄️ 核心数据表

### sys_org - 组织表

```
id=1 "示范党委" (parent_id=NULL, level=1)
├── id=3 "第一党支部" (parent_id=1, level=2)
│   └── id=5 "第一党小组" (parent_id=3, level=3)
└── id=4 "第二党支部" (parent_id=1, level=2)
```

| 字段 | 说明 | 示例 |
|------|------|------|
| id | 组织ID | 1 |
| name | 组织名称 | "示范党委" |
| parent_id | 父级ID | 1 或 NULL(顶级) |
| level | 层级 | 1-4 |
| org_type | 类型 | "党委", "支部" |

## 🔌 核心API接口

### 组织管理

| 方法 | 路由 | 功能 |
|------|------|------|
| GET | `/api/orgs` | 获取组织列表 |
| GET | `/api/orgs/:id` | 获取组织详情 |
| POST | `/api/orgs` | 创建组织 |
| PUT | `/api/orgs/:id` | 更新组织 |
| DELETE | `/api/orgs/:id` | 删除组织 |

### 党员管理

| 方法 | 路由 | 功能 |
|------|------|------|
| GET | `/api/users` | 获取党员列表 |
| POST | `/api/users` | 创建党员 |
| PUT | `/api/users/:id` | 更新党员 |
| DELETE | `/api/users/:id` | 删除党员 |

### 任务管理

| 方法 | 路由 | 功能 |
|------|------|------|
| GET | `/api/tasks` | 获取任务列表 |
| POST | `/api/tasks` | 创建任务 |
| PUT | `/api/tasks/:id` | 更新任务 |
| DELETE | `/api/tasks/:id` | 删除任务 |

## 🚀 快速启动

### 1. 启动后端

```bash
cd backend
go run cmd/server/main.go

# 输出: [GIN-debug] Listening and serving HTTP on :8080
```

### 2. 启动前端

```bash
cd frontend
npm install
npm run dev

# 输出: ➜  Local:   http://localhost:5173/
```

### 3. 访问应用

打开浏览器访问: **http://localhost:5173**

## 💻 技术栈

### 前端

- **Vue 3** - 进行式前端框架
- **Vite** - 下一代前端构建工具
- **Element Plus** - 企业级UI组件库
- **Pinia** - 轻量级状态管理
- **Vue Router** - 路由管理
- **Axios** - HTTP客户端

### 后端

- **Go 1.23+** - 编程语言
- **Gin** - 高性能Web框架
- **GORM** - ORM数据库框架
- **MySQL 8.0+** - 关系数据库

## 📊 数据库连接

```
Host:     172.23.72.148
Port:     3306
Database: party_db
User:     djapp
Password: Wmjf2la!
```

## 🎯 主要功能

### ✅ 已实现

- [x] 树形组织结构管理 (1-4级)
- [x] 组织CRUD完整操作
- [x] 党员信息管理
- [x] 任务发布和分配
- [x] 任务进度追踪
- [x] 附件上传
- [x] 操作审计日志
- [x] 搜索和筛选
- [x] 权限隔离
- [x] RESTful API

### 🚧 规划中

- [ ] 组织级联选择器优化
- [ ] 批量导入/导出 (Excel)
- [ ] 定时任务分发
- [ ] 红名预警系统
- [ ] 移动应用支持
- [ ] 权限模型细化

## 📁 项目结构

```
djapp3/
├── backend/                          # 后端项目
│   ├── cmd/server/main.go           # 程序入口
│   ├── internal/
│   │   ├── model/                   # 数据模型
│   │   ├── dto/                     # 数据传输对象
│   │   ├── handler/                 # HTTP处理器
│   │   ├── service/                 # 业务逻辑
│   │   ├── repository/              # 数据访问
│   │   ├── middleware/              # 中间件
│   │   ├── router/                  # 路由配置
│   │   ├── config/                  # 配置管理
│   │   └── bootstrap/               # 初始化脚本
│   ├── schema.sql                   # 数据库DDL
│   ├── go.mod                       # Go依赖
│   └── go.sum
│
├── frontend/                         # 前端项目
│   ├── src/
│   │   ├── views/                   # 页面组件
│   │   │   ├── home/
│   │   │   ├── org/
│   │   │   ├── user/
│   │   │   └── task/
│   │   ├── api/                     # API客户端
│   │   ├── router/                  # 路由配置
│   │   ├── stores/                  # 状态管理
│   │   └── main.js                  # 入口文件
│   ├── vite.config.js               # 构建配置
│   └── package.json
│
├── docs/                            # 文档目录
│   ├── README.md                    # 本文件
│   ├── quick-reference.md           # 快速参考
│   ├── SYSTEM_OVERVIEW.md           # 系统总览
│   └── org-management.md            # 组织管理详解
│
└── CLAUDE.md                        # 开发指南
```

## 🔐 权限和安全

### 权限隔离

- 通过Gin中间件注入用户上下文
- Repository层自动过滤orgID
- 用户只能查看其所属组织及下属组织

### 数据隔离

```go
// 每个查询都会自动加入权限过滤
repository.List(userOrgID)
  └─ WHERE id = ? OR parent_id = ?
```

## 🐛 故障排查

### 前端无法连接后端

**问题:** "Network Error" 或 "CORS error"

**解决:**
```bash
# 检查后端是否运行
curl http://localhost:8080/api/orgs

# 检查环境变量
cat frontend/.env.local
# 应包含: VITE_API_BASE_URL=http://localhost:8080/api
```

### 数据库连接失败

**问题:** "connection refused"

**解决:**
```bash
# 检查MySQL服务
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" -e "SELECT 1"

# 导入schema.sql
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" party_db < backend/schema.sql
```

## 📖 详细文档

### 对组织管理感兴趣？
👉 阅读 **[组织管理详细文档](./org-management.md)**
- 完整的功能说明
- 代码示例详解
- 故障排查指南

### 想了解系统架构？
👉 阅读 **[系统总览](./SYSTEM_OVERVIEW.md)**
- 架构图解
- 分层设计
- 数据库关系图

### 需要快速查询？
👉 查看 **[快速参考](./quick-reference.md)**
- API速查表
- 数据库字段
- 常用命令

## 🎓 学习路径

### 初学者

1. 从 [快速参考](./quick-reference.md) 开始了解基本概念
2. 运行 [快速启动](#快速启动) 部分，启动系统
3. 在前端UI中体验各项功能
4. 查看示例数据了解数据结构

### 开发者

1. 阅读 [系统总览](./SYSTEM_OVERVIEW.md) 理解架构
2. 查看相应模块的源码:
   - Frontend: `frontend/src/views/org/OrgListView.vue`
   - Backend: `backend/internal/{handler,service,repository}/org.go`
3. 参考 [组织管理详细文档](./org-management.md) 进行扩展开发
4. 按照 CLAUDE.md 中的规范进行编码

### 架构师

1. 研究 [系统总览](./SYSTEM_OVERVIEW.md) 中的架构设计
2. 评审数据库设计 (`backend/schema.sql`)
3. 考虑 [后续扩展](./org-management.md#后续扩展) 的可行性
4. 优化权限隔离和数据安全机制

## 🤝 贡献指南

### 代码规范

参考 CLAUDE.md 中的开发指南：
- 后端: Go + Gin + GORM 规范
- 前端: Vue 3 + Element Plus 规范
- 数据库: snake_case 命名

### 提交代码

1. 确保所有测试通过
2. 遵循代码规范
3. 在README中更新相关文档

## 📞 支持

遇到问题？
1. 检查 [故障排查](#故障排查) 部分
2. 查看相关详细文档
3. 联系开发团队

## 📝 版本历史

### v1.0.0 (2024-01-01)

初始版本功能：
- ✅ 完整的组织管理模块
- ✅ 党员管理功能
- ✅ 任务管理功能
- ✅ 权限隔离系统
- ✅ 详细的文档和指南

## 📄 许可证

本项目采用 MIT 许可证。详见 LICENSE 文件。

## 👥 关于

**党建任务管理系统** 是一套现代化的党组织管理解决方案，采用最新的前后端技术栈，提供完整的组织、党员、任务管理功能。

---

**文档版本**: v1.0.0  
**最后更新**: 2024-01-01  
**作者**: Claude (Copilot)

## 快速链接

| 链接 | 说明 |
|------|------|
| 📊 [快速参考](./quick-reference.md) | API速查、常用命令 |
| 🏗️ [系统总览](./SYSTEM_OVERVIEW.md) | 架构图解、流程详解 |
| 📖 [组织管理](./org-management.md) | 完整功能文档、代码示例 |
| 🎯 [开发指南](../CLAUDE.md) | 编码规范、技术栈说明 |
| 📁 [源码](../) | 项目根目录 |

**开始使用:** 👉 [快速启动](#快速启动) | 📚 [文档导航](#文档导航)
