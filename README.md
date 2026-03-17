# Oracle 批量执行网页

简单的 Flask 应用：上传 Excel（必须包含 `host`, `servicename`, `prot` 列），在每个主机上使用给定或表内凭据连接 Oracle，执行 SQL，并把结果写回新的 Excel 并下载。

安装依赖：

````markdown
# Oracle 批量执行网页

简单的 Flask 应用：上传 Excel（必须包含 `host`, `servicename`, `prot` 列），在每个主机上使用给定或表内凭据连接 Oracle，执行 SQL，并把结果写回新的 Excel 并下载。

安装依赖：

```powershell
python -m pip install -r requirements.txt
```

注意：`oracledb` 在有些环境下需要 Oracle Instant Client 或使用薄客户端模式（详见 oracledb 文档）。确保能在本机使用 `oracledb` 连接到目标数据库。

运行：

```powershell
python app.py
# 然后打开 http://127.0.0.1:5000
```

Excel 说明：
- 必须列：`host`（IP 或域名）、`servicename`（Oracle service name）、`prot`（port）
- 可选列：`username`、`password`（若提供则覆盖表单中填写的凭据）

输出：下载 `results.xlsx`，增加列 `run_status`（ok/fail）和 `run_result`（结果或错误信息）。


PHP 版本
-------------
本仓库同时提供了一个 PHP 实现：
- 页面: `index.php`
- 处理脚本: `process.php`

依赖：使用 `phpoffice/phpspreadsheet` 读取/写入 Excel，使用 PHP 的 `oci8` 扩展连接 Oracle（或使用 PDO_OCI）。

安装依赖（在工程目录运行）：

```powershell
composer install
```

运行：将项目放在你的 PHP 可访问的目录（例如 `htdocs` 或者内置 PHP server），并确保 `oci8` 已启用且能连接到目标 Oracle 实例。

示例使用内置服务器（仅用于测试）:

```powershell
php -S 127.0.0.1:8000 -t f:\\djtest
# 打开 http://127.0.0.1:8000/index.php
```

注意：确保你的 PHP 已启用 `oci8`，Windows 下通常需要安装 Oracle Instant Client 并启用相应的 DLL。若没有 `oci8`，请参考 PHP 与 Oracle 的文档安装扩展。

Docker 打包
-------------
此项目提供可构建的 `Dockerfile`（位于仓库根目录）。由于 Oracle Instant Client 受限于 Oracle 分发许可，构建镜像时需要你提供 Instant Client 的 zip 文件并放到 `docker/` 目录下：

- `docker/instantclient-basiclite.zip`
- `docker/instantclient-sdk.zip`

构建并运行示例：

```powershell
# 在项目根目录运行（确保已把 instantclient zip 放到 ./docker/）
docker build -t oracle-exec-php .
docker run -p 8080:80 --name oracle-exec -d oracle-exec-php
# 打开 http://127.0.0.1:8080/index.php
```

如果你不希望在镜像内安装 Instant Client，可以直接在宿主上安装并通过 `-v` 挂载必要的共享库并调整 php.ini，但那比较复杂，推荐直接在构建上下文中提供 Instant Client zip。

````


错误码与轮询状态
-------------------

本项目在处理每行数据库时会返回统一的 `error_code` 字段，用于标识失败原因。常见错误码：

- `OCI_MISSING`：PHP 环境未安装 OCI8 扩展（`oci_connect` 不可用）。
- `OCI_CONN_FAIL`：OCI 连接失败（返回数据库错误信息）。
- `OCI_MISSING_FUNCS`：OCI8 缺少必要函数，无法执行 SQL。
- `SQL_PARSE_ERROR`：SQL 解析失败（`oci_parse` 错误）。
- `SQL_EXEC_ERROR`：SQL 执行失败（`oci_execute` 错误）。
- `MISSING_CREDENTIALS`：缺少用户名或密码（行或表单）。
- `CONN_FAIL`：连接失败的后备错误码（保底）。
- `TIMEOUT_TOTAL`：超过总执行时间限制时跳过该行并记录该错误码。
- `TIMEOUT`：历史兼容码，表示超时。成功时 `error_code` 为 `null`。

在 HTTP 响应头中还会返回错误码汇总：

- `X-Process-Error-Codes`: JSON，例如 `{"MISSING_CREDENTIALS":2,"OCI_CONN_FAIL":5}`。
- `X-Process-Stats`: 进度信息的 JSON（processed/total/success/failed）。
- `X-Process-Progress`: 简短的分数形式（例如 `3/10`）。
- `X-Process-Current-DB`: 当前或最后一个正在处理的数据库（例如 `db.example.com:1521/ORCL`）。
- `X-Process-Status-File`: 返回状态文件的 basename，前端可使用 `status.php?file=<basename>` 进行轮询。

轮询状态文件（推荐前端做法）
-------------------------------

处理开始时 `process.php` 会在系统临时目录创建一个 JSON 状态文件，默认名为 `process_status_<uniq>.json`。如果前端在提交时提供 `status_token`（任意短字符串），服务端将使用 `process_status_<token>.json`，这样前端能在提交后立即知道要轮询的文件名。

状态文件 JSON 字段：

- `processed`：已处理行数
- `total`：总行数
- `success`：成功计数
- `failed`：失败计数
- `current_db`：当前行正在处理的数据库（host:port/serv）
- `error_counts`：按错误码统计的对象
- `done`：布尔，处理是否完成
- `timestamp`：最后更新时间戳

安全读取：仓库中提供 `status.php`，接受 `file` 参数（basename），并对文件名做严格校验，防止目录遍历。前端轮询示例（简化版）：

```javascript
// 假设服务器返回 X-Process-Status-File: process_status_abcd.json
const statusFile = 'process_status_abcd.json';
setInterval(() => {
	fetch('/status.php?file=' + encodeURIComponent(statusFile), {cache: 'no-store'})
		.then(r => { if (!r.ok) throw new Error('notfound'); return r.json(); })
		.then(data => {
			console.log('进度', data.processed + '/' + data.total, '当前', data.current_db);
			if (data.done) { /* 停止轮询 */ }
		}).catch(() => {/* 忽略 */});
}, 1000);
```

前端提交建议：提交表单时生成 `status_token` 并附带到 POST（字段名为 `status_token`），随后前端即可轮询 `status.php` 获取进度并在处理完成后下载返回的文件。

注：状态文件位于系统临时目录，`status.php` 只返回 basename 合法的文件内容，避免泄露服务器路径。
