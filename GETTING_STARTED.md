# 组织管理系统 - 5分钟快速开始指南

欢迎使用党建任务管理系统！本指南将帮助您在5分钟内启动系统并体验功能。

## 🎯 目标

启动后端 + 前端，然后在浏览器中体验组织管理功能。

## 📋 前置条件检查

运行以下命令验证环境：

```bash
# 检查 Go 版本 (需要 1.23+)
go version

# 检查 Node 版本 (需要 14+)
node --version

# 检查 npm 版本
npm --version

# 检查 MySQL 连接 (使用提供的数据库凭证)
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" -e "SELECT 1"
```

## 🚀 快速启动 (两个终端)

### 方式 A: 分离终端启动 (推荐)

#### 终端 1 - 启动后端

```bash
cd f:\djapp3\backend
go run cmd/server/main.go
```

**预期输出:**
```
[GIN-debug] Loaded HTML Templates (2): 
[GIN-debug] Accepting connections on [::]:8080
```

✅ 后端已启动，监听 http://localhost:8080

#### 终端 2 - 启动前端

```bash
cd f:\djapp3\frontend
npm install
npm run dev
```

**预期输出:**
```
  ➜ Local:   http://localhost:5173/
  ➜ press h to show help
```

✅ 前端已启动，访问 http://localhost:5173

### 方式 B: 一键启动脚本 (可选)

```bash
# 如果您有启动脚本，运行它
# 或按照方式A分别启动
```

## 🌐 访问应用

1. **打开浏览器**，输入以下地址：
   ```
   http://localhost:5173
   ```

2. **导航到组织管理**：
   - 在左侧菜单找到 "组织管理" 或点击顶部导航
   - 或直接访问: http://localhost:5173/orgs

## 📊 初始数据预览

系统自动加载的示例组织结构：

```
┌─ 示范党委 (顶级组织，Level=1)
│  ├─ 第一党支部 (Level=2)
│  │  └─ 第一党小组 (Level=3)
│  └─ 第二党支部 (Level=2)
└─ 市级党委 (顶级组织，Level=1)
   └─ 市级第一党支部 (Level=2)
```

## 🎮 功能演示 (5步体验)

### 步骤 1: 查看组织列表 ✅

在 http://localhost:5173/orgs 页面，您应该看到：

```
┌─────────────────────────────────────────────────────┐
│ 组织列表                    [新增组织] [刷新]        │
├─────────────────────────────────────────────────────┤
│ [搜索框] [层级筛选] [快速操作] [重置]                 │
├─────────────────────────────────────────────────────┤
│ ID │ 组织名称 │ 层级 │ 类型 │ 父级 │ 操作           │
├────┼─────────┼──────┼──────┼──────┼────────────────┤
│ 1  │ 示范党委 │  1   │ 党委 │  -   │ 编辑 │ 删除    │
│ 3  │ 第一党支部 │ 2  │ 支部 │ 示范党委 │ 编辑 │ 删除 │
│ 5  │ 第一党小组 │ 3  │ 党小组 │ 第一党支部 │ 编辑 │ 删除 │
│ 4  │ 第二党支部 │ 2  │ 支部 │ 示范党委 │ 编辑 │ 删除 │
│ 2  │ 市级党委 │  1   │ 党委 │  -   │ 编辑 │ 删除    │
│ 6  │ 市级第一党支部 │ 2 │ 支部 │ 市级党委 │ 编辑 │ 删除 │
└────┴─────────┴──────┴──────┴──────┴────────────────┘
```

### 步骤 2: 搜索组织 🔍

1. 在搜索框输入 "党委"
2. 表格自动过滤，只显示包含 "党委" 的行
3. 点击 "重置" 按钮清除搜索

**观察到:**
- 实时搜索 (无需按Enter)
- 模糊匹配 (输入部分内容即可匹配)

### 步骤 3: 筛选层级 📊

1. 点击 "层级筛选" 下拉框
2. 选择 "2级"
3. 表格只显示 Level=2 的支部

**可筛选的层级:**
- 1级 (顶级党委)
- 2级 (支部)
- 3级 (党小组)
- 4级 (其他)

### 步骤 4: 新增组织 ➕

#### 方式 A: 创建子级组织

1. 点击 "新增组织" 按钮
2. 填写表单：
   - **组织名称**: "新建支部"
   - **父级组织**: "示范党委" (自动选中)
   - **层级**: "2" (自动设置)
   - **类型**: "支部"
3. 点击 "保存"
4. 页面显示 "✓ 组织已创建"
5. 列表自动刷新，新组织出现

#### 方式 B: 创建顶级组织

1. 从下拉菜单选择 "新建顶级组织"
2. 填写表单：
   - **组织名称**: "新党委"
   - **层级**: "1" (固定)
   - **类型**: "党委"
   - **父级**: "(无)" (固定)
3. 点击 "保存"

**观察:**
- 父级选择自动更新
- 层级根据父级自动调整
- 表单验证实时进行

### 步骤 5: 编辑和删除 ✏️ 🗑️

#### 编辑

1. 点击任意行的 "编辑" 按钮
2. 对话框显示当前组织信息
3. 修改 **组织名称** 为 "修改的名称"
4. 点击 "保存"
5. 列表自动刷新

**支持修改的字段:**
- 组织名称
- 所属层级
- 组织类型

#### 删除

1. 点击任意行的 "删除" 按钮
2. 弹出确认对话框: "确认删除组织'XXX'吗？"
3. 点击 "确定" 确认
4. 页面显示 "✓ 组织已删除"
5. 列表自动刷新

## 🔍 验证系统是否正常

### 检查 1: 前端是否正常加载

**访问:** http://localhost:5173/orgs

**预期:**
- ✅ 页面加载成功
- ✅ 显示 "组织列表" 标题
- ✅ 表格显示至少 6 条初始数据

### 检查 2: 后端API是否正常

```bash
# 获取组织列表
curl http://localhost:8080/api/orgs

# 预期返回:
{
  "data": [
    {
      "id": 1,
      "name": "示范党委",
      "parent_id": null,
      "level": 1,
      "org_type": "党委"
    },
    ...
  ]
}
```

### 检查 3: 数据库是否正常

```bash
# 连接数据库
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" party_db

# 查询组织表
mysql> SELECT COUNT(*) FROM sys_org;
# 预期: 至少 6 条记录

mysql> SELECT * FROM sys_org LIMIT 1\G
# 预期: 显示组织记录，包含 id, name, parent_id, level, org_type
```

## 🐛 常见问题排查

### 问题 1: 前端显示 "Network Error"

**原因:** 无法连接后端API

**解决:**
```bash
# 1. 检查后端是否运行
curl http://localhost:8080/api/orgs

# 2. 检查环境变量
cat frontend/.env.local
# 应该包含: VITE_API_BASE_URL=http://localhost:8080/api

# 3. 重启前端
# 在前端终端按 Ctrl+C 停止
# 再运行 npm run dev
```

### 问题 2: 后端报错 "connection refused"

**原因:** 数据库连接失败

**解决:**
```bash
# 1. 验证MySQL是否运行
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" -e "SELECT 1"

# 2. 检查数据库和表是否存在
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" party_db -e "SHOW TABLES"

# 3. 导入数据库脚本 (如果表不存在)
mysql -h 172.23.72.148 -u djapp -p"Wmjf2la!" party_db < backend/schema.sql

# 4. 重启后端
```

### 问题 3: 页面无法搜索/筛选

**原因:** 列表数据未加载

**解决:**
```bash
# 1. 打开浏览器开发者工具 (F12)
# 2. 查看 Console 选项卡的错误信息
# 3. 查看 Network 选项卡的 /api/orgs 请求
# 4. 检查响应数据是否正常

# 也可点击页面的 "刷新" 按钮重新加载数据
```

### 问题 4: 操作后页面未刷新

**解决:**
```bash
# 1. 点击 "刷新" 按钮手动刷新
# 2. 或刷新整个页面 (Ctrl+R 或 Cmd+R)
# 3. 查看浏览器控制台是否有错误信息
```

## 📊 接下来可以做的事

### 体验其他功能

- 👥 **党员管理** - http://localhost:5173/users
- 📋 **任务管理** - http://localhost:5173/tasks

### 深入学习

- 📖 [完整系统文档](docs/README.md)
- 📌 [快速参考](docs/quick-reference.md)
- 🏗️ [系统架构](docs/SYSTEM_OVERVIEW.md)
- 📚 [详细API文档](docs/org-management.md)

### 阅读源码

**前端:**
```
frontend/src/views/org/OrgListView.vue    # 完整的页面实现
frontend/src/api/http.js                  # HTTP客户端
```

**后端:**
```
backend/internal/handler/org.go           # HTTP处理
backend/internal/service/org.go           # 业务逻辑
backend/internal/repository/org.go        # 数据访问
```

### 二次开发

按照 [CLAUDE.md](./CLAUDE.md) 中的规范进行开发。

## 🎓 学习资源

### 数据库

**所有组织的SQL查询:**
```sql
SELECT * FROM sys_org ORDER BY level, parent_id;
```

**创建新组织的SQL:**
```sql
INSERT INTO sys_org (name, parent_id, level, org_type) 
VALUES ('新组织', 1, 2, '支部');
```

### API

**完整API列表:**
- `GET /api/orgs` - 获取列表
- `GET /api/orgs/:id` - 获取详情
- `POST /api/orgs` - 创建
- `PUT /api/orgs/:id` - 更新
- `DELETE /api/orgs/:id` - 删除

详见 [快速参考](docs/quick-reference.md)

## 📞 需要帮助？

1. **查看文档** - [文档中心](docs/README.md)
2. **搜索问题** - 查看 [快速参考](docs/quick-reference.md) 的FAQ
3. **查看源码** - 代码中有详细注释
4. **查看日志** - 检查浏览器控制台和后端输出

## ✅ 检查清单

快速启动时的检查清单：

- [ ] 后端成功启动 (http://localhost:8080)
- [ ] 前端成功启动 (http://localhost:5173)
- [ ] 能够访问组织列表页面
- [ ] 页面显示初始化数据 (至少6行)
- [ ] 能够搜索组织
- [ ] 能够筛选层级
- [ ] 能够创建新组织
- [ ] 能够编辑组织
- [ ] 能够删除组织
- [ ] 数据持久化 (刷新后数据仍在)

## 🎉 成功指标

当您能够完成以下操作时，表示系统运行正常：

✅ 查看初始组织列表  
✅ 搜索和筛选组织  
✅ 创建新的顶级党委  
✅ 在顶级党委下创建支部  
✅ 在支部下创建党小组  
✅ 修改组织名称  
✅ 删除组织  
✅ 刷新后数据仍然存在  

## 下一步

1. **体验完整系统** - 探索其他功能模块
2. **理解架构** - 阅读 [系统总览](docs/SYSTEM_OVERVIEW.md)
3. **学习代码** - 查看源码实现
4. **进行开发** - 按照规范进行二次开发

## 参考资源

| 资源 | 链接 |
|------|------|
| 文档首页 | [docs/README.md](docs/README.md) |
| 快速参考 | [docs/quick-reference.md](docs/quick-reference.md) |
| 系统架构 | [docs/SYSTEM_OVERVIEW.md](docs/SYSTEM_OVERVIEW.md) |
| 详细文档 | [docs/org-management.md](docs/org-management.md) |
| 开发规范 | [CLAUDE.md](CLAUDE.md) |
| 完整总结 | [ORG_MANAGEMENT_SUMMARY.md](ORG_MANAGEMENT_SUMMARY.md) |

---

**开始时间**: 现在  
**预计完成**: 5分钟  
**难度级别**: ⭐ 极简  

**祝您使用愉快！** 🎉

如有任何问题，请参考上述故障排查部分或查看详细文档。
