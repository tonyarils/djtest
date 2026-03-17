<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Oracle 批量执行（PHP）</title>
    <script src="jquery-3.6.0.min.js"></script>
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:#f5f7fb; color:#222; margin:0; padding:24px 12px; }
      .container { max-width:920px; margin:0 auto; }
      .card { background:#fff; border-radius:8px; box-shadow:0 6px 18px rgba(31,41,55,0.06); padding:20px; }
      h1 { margin:0 0 8px 0; font-size:20px; }
      .muted { color:#666; font-size:13px; }
      form .row { display:flex; gap:12px; flex-wrap:wrap; }
      .form-group { flex:1 1 300px; display:flex; flex-direction:column; margin-bottom:12px; }
      label { font-weight:600; margin-bottom:6px; color:#333; }
      input[type="text"], input[type="password"], textarea, input[type="file"] { padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
      textarea { min-height:140px; resize:vertical; }
      .actions { display:flex; gap:12px; align-items:center; margin-top:8px; }
      button.primary { background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
      button.primary:disabled { opacity:0.6; cursor:not-allowed; }
      .spinner { width:20px; height:20px; border-radius:50%; border:3px solid #e5e7eb; border-top-color:#3b82f6; animation:spin 1s linear infinite; display:none; }
      @keyframes spin { to { transform:rotate(360deg); } }
      #filename { font-size:13px; color:#444; margin-top:6px; display:inline-block; }
      .note { font-size:13px; color:#555; margin-top:10px; }
      .error { color:#b91c1c; background:#fff5f5; border:1px solid #fecaca; padding:8px 10px; border-radius:6px; margin-bottom:12px; }
      .footer { text-align:right; margin-top:12px; font-size:13px; color:#666; }
      a.template { color:#2563eb; text-decoration:none; }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="card">
        <h1>Oracle 批量执行（PHP）</h1>
        <div class="muted">上传 Excel（包含 <strong>host</strong>, <strong>servicename</strong>, <strong>port</strong> 列），执行 SQL 并返回结果。</div>
        <p class="note">提交后页面将显示处理进度及当前正在连接的数据库（支持轮询状态文件），处理完成后会自动触发结果文件下载。</p>

        <?php if (!empty($_GET['err'])): ?>
          <div class="error"><?= htmlspecialchars($_GET['err']) ?></div>
        <?php endif; ?>

        <form action="process.php" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label>Excel 文件</label>
            <input id="excelFile" type="file" name="excel" accept=".xls,.xlsx" required>
            <span id="filename"></span>
          </div>

          <div class="form-group">
            <label>默认用户名</label>
            <input type="text" name="username" placeholder="可在表中提供每行凭据覆盖">
          </div>

          <div class="form-group">
            <label>默认密码</label>
            <input type="password" name="password">
          </div>

          <div class="form-group">
            <fieldset style="border:1px solid #ddd; padding:8px; margin-bottom:8px;">
              <legend style="font-weight:600;">执行配置（可选）</legend>
              <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <label>单条最大返回行数: <input type="number" name="cfg_max_result_rows" value="100" min="1" style="width:120px"></label>
                <label>单条超时(秒): <input type="number" name="cfg_max_execution_time" value="30" min="1" style="width:120px"></label>
                <label>总超时(秒): <input type="number" name="cfg_max_total_time" value="300" min="1" style="width:120px"></label>
                <label>最大行数: <input type="number" name="cfg_max_rows" value="1000" min="1" style="width:120px"></label>
                <label style="align-items:center; display:flex; gap:6px;"><input type="checkbox" name="cfg_enable_log" value="1"> 启用日志</label>
              </div>
            </fieldset>
          </div>

          <div class="form-group">
            <label><input type="checkbox" name="mock" value="1"> 模拟模式（测试用，不连接 Oracle）</label>
          </div>

          <div class="form-group">
            <label>SQL 脚本（SELECT 或其它）</label>
            <textarea name="sql" required placeholder="例如：SELECT * FROM DUAL"></textarea>
          </div>

          <div class="actions">
            <button id="submitBtn" class="primary" type="submit">执行并下载结果</button>
            <div class="spinner" id="loading"></div>
            <div id="progressInfo" style="display:none; margin-left:12px; font-size:13px; color:#333">进度: <span id="progressText">0/0</span> 当前: <span id="currentDb">-</span></div>
            <div style="flex:1"></div>
            <div class="note">示例模板： <a class="template" href="template.xlsx" download>下载 template.xlsx</a></div>
          </div>
        </form>

        <div class="footer">运行时请注意数据库权限与安全性。示例模式可用于本地测试。</div>
      </div>
    </div>

    <script>
      $(function(){
        $('#excelFile').on('change', function(){
          var f = this.files && this.files[0];
          $('#filename').text(f ? f.name : '');
        });

        $('form').on('submit', function(e){
          e.preventDefault();
          var f = $('#excelFile')[0].files.length;
          var sql = $.trim($('textarea[name="sql"]').val());
          if(!f){ alert('请选择 Excel 文件'); return false; }
          if(!sql){ alert('请提供要执行的 SQL 脚本'); return false; }

          $('#submitBtn').prop('disabled', true);
          $('#loading').show();
          $('#progressInfo').show();

          // 生成短 token，用于状态文件名（前端已知文件名，可立即轮询）
          var token = 't' + Date.now().toString(36) + Math.random().toString(36).slice(2,8);

          var form = $('form')[0];
          var fd = new FormData(form);
          fd.append('status_token', token);

          // 开始轮询状态文件
          var statusBasename = 'process_status_' + token + '.json';
          var pollUrl = 'status.php?file=' + encodeURIComponent(statusBasename);
          var pollInterval = null;
          function startPolling(){
            pollInterval = setInterval(function(){
              fetch(pollUrl, {cache: 'no-store'})
                .then(function(r){ if (!r.ok) throw new Error('notfound'); return r.json(); })
                .then(function(data){
                  $('#progressText').text(data.processed + '/' + data.total);
                  $('#currentDb').text(data.current_db || '-');
                  // 失败统计可另行显示
                  if (data.done) {
                    clearInterval(pollInterval);
                  }
                }).catch(function(){ /* 忽略网络或 404 错误，继续轮询 */ });
            }, 1000);
          }

          startPolling();

          // 提交并等待文件返回（使用 fetch 以便接收 headers 和文件 blob）
          fetch('process.php', { method: 'POST', body: fd })
            .then(function(response){
              if (!response.ok) throw new Error('请求失败: ' + response.status);
              return response.blob().then(function(blob){
                // 触发下载
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                // 尝试从 Content-Disposition 中获取文件名
                var disposition = response.headers.get('Content-Disposition') || '';
                var filename = 'results.xlsx';
                var m = /filename="?([^";]+)"?/.exec(disposition);
                if (m && m[1]) filename = m[1];
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                // 标记轮询为结束（如果尚未）
                setTimeout(function(){ if (pollInterval) clearInterval(pollInterval); }, 500);
              });
            })
            .catch(function(err){ alert('执行失败：' + err); })
            .finally(function(){
              $('#submitBtn').prop('disabled', false);
              $('#loading').hide();
              // 最终从状态文件获取一次以更新 UI
              fetch(pollUrl, {cache:'no-store'}).then(function(r){ if (r.ok) return r.json(); }).then(function(data){ if (data){ $('#progressText').text(data.processed + '/' + data.total); $('#currentDb').text(data.current_db || '-'); } }).catch(()=>{});
            });
        });
      });
    </script>
  </body>
</html>
