#!/usr/bin/env bash
# 本地开发一键启动（MySQL + PHP 内置服务器）
# 使用前请按实际路径修改 MYSQL_HOME / PHP_BIN
set -e
MYSQL_HOME=${MYSQL_HOME:-/tmp/mysql}
PHP_BIN=${PHP_BIN:-php}
DATADIR=${MYSQL_DATADIR:-/tmp/mysql-data}
RUNDIR=${MYSQL_RUNDIR:-/tmp/mysql-run}
PORT=${APP_PORT:-8000}

export LD_LIBRARY_PATH=${LD_LIBRARY_PATH:-/tmp/libs/usr/lib/aarch64-linux-gnu}

# 启动 MySQL（如未运行）
if [ ! -S "$RUNDIR/mysql.sock" ]; then
  echo "启动 MySQL ..."
  nohup "$MYSQL_HOME/bin/mysqld" --no-defaults --basedir="$MYSQL_HOME" \
    --datadir="$DATADIR" --socket="$RUNDIR/mysql.sock" --port=3306 \
    --bind-address=127.0.0.1 --mysqlx=OFF --pid-file="$RUNDIR/mysqld.pid" \
    > "$RUNDIR/server.log" 2>&1 &
  sleep 5
fi

echo "启动 PHP 开发服务器: http://127.0.0.1:$PORT"
echo "  前台: http://127.0.0.1:$PORT/index.php"
echo "  后台: http://127.0.0.1:$PORT/admin/  (admin / admin123)"
exec "$PHP_BIN" -S 127.0.0.1:$PORT -t "$(dirname "$0")/.."
