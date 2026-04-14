# Claude Code Implementation Instructions

## 1. 技术栈环境 (Tech Stack)

- **Frontend**: Vue 3 (Script Setup), Vite, Element Plus, Pinia.
- **Backend**: Go 1.23+, Gin, GORM.
- **Database**: MySQL 8.0+.
- **全局数据库连接**：`jdbc:mysql://172.23.72.148:3306/party_db`。
- **数据库账号**：`djapp`
- **数据库密码**：`Wmjf2la!`

后续所有默认数据库配置均以该连接及账号信息为准。

## 2. 数据库设计规范

- 必须使用 `snake_case` 命名字段。
- 核心表：`sys_org` (树形结构), `sys_user` (党员/用户), `t_task` (任务), `t_attachment` (附件), `t_task_log` (操作审计)。
- 表结构优先兼容现有 `schema.sql` 设计，避免无必要偏离。

## 3. 后端开发指令 (Backend)

- **技术选型**：后端统一使用 Go + Gin + GORM，当前阶段不再按 Spring Boot/MyBatis Plus 实现。
- **工程结构**：推荐按 `cmd`、`internal/config`、`internal/model`、`internal/handler`、`internal/service`、`internal/repository`、`internal/middleware`、`internal/router` 分层。
- **权限隔离**：基于 Gin 中间件注入当前用户上下文，Repository 查询必须自动携带 `org_id` 过滤条件。
- **接口风格**：优先提供 REST API，先覆盖组织、党员、任务三个核心模块。
- **定时任务**：使用 Go 定时任务机制实现每月 1 日的任务分发，并预留红名预警任务入口。
- **DTO 规范**：禁止直接暴露数据库模型给前端，请使用独立 request/response 结构体。
- **ORM 约束**：GORM 模型字段与数据库字段保持清晰映射，通用审计字段统一收敛。

## 4. 前端开发指令 (Frontend)

- **UI 库**：全面使用 Element Plus。
- **颜色规范**：
  - 正常状态：`#67C23A` (Success)
  - 待处理：`#E6A23C` (Warning)
  - 红名预警：`#F56C6C` (Danger)
- **组件化**：多媒体上传组件需支持音频播放预览。
- **接口对接**：前端当前按 REST API 与 Go 后端联调。

## 5. 初步引导 (Context Prompt)

"请基于 plan.md 和本文件，优先初始化 Go + Gin + GORM 后端工程、数据库 DDL 脚本，并实现组织、党员、任务三个核心模块的基础模型与 REST API。"
