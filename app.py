from flask import Flask, request, render_template, send_file, redirect, url_for, flash
import pandas as pd
import tempfile
import os
import traceback
try:
    import oracledb
except Exception:
    oracledb = None

app = Flask(__name__)
app.secret_key = os.urandom(16)


def build_dsn(host, port, service_name):
    try:
        return oracledb.makedsn(host, int(port), service_name=service_name)
    except Exception:
        return f"{host}:{port}/{service_name}"


def execute_sql_on_conn(conn, sql):
    cur = conn.cursor()
    try:
        cur.execute(sql)
        if cur.description:
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()
            df = pd.DataFrame(rows, columns=cols)
            return True, df.head(100).to_dict(orient='records')
        else:
            return True, f"Statement executed, rowcount={cur.rowcount}"
    finally:
        cur.close()


@app.route('/', methods=['GET'])
def index():
    return render_template('index.html')


@app.route('/run', methods=['POST'])
def run():
    if 'excel' not in request.files:
        flash('请上传 Excel 文件')
        return redirect(url_for('index'))

    excel_file = request.files['excel']
    sql = request.form.get('sql', '').strip()
    if not sql:
        flash('请提供要执行的 SQL 脚本')
        return redirect(url_for('index'))

    form_user = request.form.get('username', '').strip()
    form_password = request.form.get('password', '').strip()

    try:
        df = pd.read_excel(excel_file)
    except Exception as e:
        flash(f'无法读取 Excel: {e}')
        return redirect(url_for('index'))

    for col in ('host', 'servicename', 'port'):
        if col not in df.columns:
            flash(f'Excel 缺少必要列: {col}')
            return redirect(url_for('index'))

    results = []

    for idx, row in df.iterrows():
        host = str(row.get('host'))
        serv = str(row.get('servicename'))
        port = str(row.get('port'))

        user = row.get('username') if 'username' in df.columns and pd.notna(row.get('username')) else form_user
        password = row.get('password') if 'password' in df.columns and pd.notna(row.get('password')) else form_password

        out_status = ''
        out_result = ''

        if not user or not password:
            out_status = 'fail'
            out_result = '缺少用户名或密码（可在表中增加 username/password 列，或在表单填写）'
            results.append({'status': out_status, 'result': out_result})
            continue

        if oracledb is None:
            out_status = 'fail'
            out_result = 'Python 环境未安装 oracledb（请参考 README 安装）'
            results.append({'status': out_status, 'result': out_result})
            continue

        try:
            dsn = build_dsn(host, port, serv)
            conn = oracledb.connect(user=str(user), password=str(password), dsn=dsn, encoding='UTF-8')
            try:
                ok, res = execute_sql_on_conn(conn, sql)
                out_status = 'ok' if ok else 'fail'
                out_result = res
            finally:
                conn.close()
        except Exception:
            out_status = 'fail'
            out_result = traceback.format_exc()

        results.append({'status': out_status, 'result': out_result})

    df['run_status'] = [r['status'] for r in results]
    df['run_result'] = [r['result'] for r in results]

    tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.xlsx')
    try:
        df.to_excel(tmp.name, index=False)
        tmp.close()
        return send_file(tmp.name, as_attachment=True, download_name='results.xlsx')
    finally:
        try:
            os.unlink(tmp.name)
        except Exception:
            pass


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
