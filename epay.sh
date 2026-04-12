#!/bin/bash
#
# EPay CLI - 容器化管理工具
# 用法: bash epay.sh <command>
#

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ENV_FILE=".env"
COMPOSE_CMD="docker compose"

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[epay]${NC} $1"; }
warn() { echo -e "${YELLOW}[warn]${NC} $1"; }
err()  { echo -e "${RED}[error]${NC} $1"; exit 1; }

# ============================================
# 检查 Docker 环境
# ============================================
check_docker() {
    if ! command -v docker &>/dev/null; then
        warn "Docker 未安装，正在自动安装..."
        curl -fsSL https://get.docker.com | sh
        systemctl enable docker && systemctl start docker
        log "Docker 安装完成。"
    fi

    # 检查 docker compose 子命令
    if ! docker compose version &>/dev/null; then
        if command -v docker-compose &>/dev/null; then
            COMPOSE_CMD="docker-compose"
        else
            err "docker compose 不可用，请安装 Docker Compose v2。"
        fi
    fi
}

# ============================================
# epay init - 交互式初始化
# ============================================
cmd_init() {
    log "EPay 初始化向导"
    echo ""

    # 检查是否已初始化
    if [ -f "$ENV_FILE" ]; then
        warn ".env 文件已存在。"
        read -p "是否覆盖现有配置？(y/N): " overwrite
        if [[ ! "$overwrite" =~ ^[Yy]$ ]]; then
            log "跳过配置，直接启动..."
            $COMPOSE_CMD up -d --build
            cmd_status
            return
        fi
    fi

    # 交互式收集配置
    echo -e "${CYAN}--- 站点配置 ---${NC}"
    read -p "站点域名 (例: epay.example.com): " site_domain
    site_domain=${site_domain:-localhost}

    read -p "HTTP 端口 [80]: " app_port
    app_port=${app_port:-80}

    read -p "HTTPS 端口 [443]: " app_ssl_port
    app_ssl_port=${app_ssl_port:-443}

    echo ""
    echo -e "${CYAN}--- 数据库配置 ---${NC}"
    read -p "MySQL root 密码: " -s db_root_pass
    echo ""
    if [ -z "$db_root_pass" ]; then
        db_root_pass=$(openssl rand -base64 16 | tr -d '=+/')
        log "已自动生成 root 密码: $db_root_pass"
    fi

    read -p "数据库名 [epay]: " db_name
    db_name=${db_name:-epay}

    read -p "数据库用户名 [epay]: " db_user
    db_user=${db_user:-epay}

    read -p "数据库密码: " -s db_pass
    echo ""
    if [ -z "$db_pass" ]; then
        db_pass=$(openssl rand -base64 16 | tr -d '=+/')
        log "已自动生成用户密码: $db_pass"
    fi

    read -p "数据表前缀 [pay]: " db_prefix
    db_prefix=${db_prefix:-pay}

    # 生成 .env
    cat > "$ENV_FILE" <<EOF
# EPay Environment Configuration
# Generated at $(date '+%Y-%m-%d %H:%M:%S')

# Site
SITE_DOMAIN=${site_domain}
APP_PORT=${app_port}
APP_SSL_PORT=${app_ssl_port}

# Database
DB_ROOT_PASS=${db_root_pass}
DB_NAME=${db_name}
DB_USER=${db_user}
DB_PASS=${db_pass}
DB_PREFIX=${db_prefix}
DB_PORT=3306
EOF

    log ".env 配置文件已生成。"

    # 构建并启动
    echo ""
    log "正在构建和启动容器..."
    $COMPOSE_CMD up -d --build

    # 等待服务就绪
    log "等待服务就绪..."
    sleep 5

    echo ""
    log "============================================"
    log "  EPay 初始化完成！"
    log "============================================"
    log "  访问地址: http://${site_domain}:${app_port}"
    log "  安装页面: http://${site_domain}:${app_port}/install/"
    log ""
    log "  数据库信息:"
    log "    Host: db (容器内) / localhost:${app_port} (外部)"
    log "    User: ${db_user}"
    log "    Pass: ${db_pass}"
    log "    DB:   ${db_name}"
    log "============================================"
    echo ""
    log "如需配置 SSL，请将证书放入:"
    log "  docker compose cp epay.pem epay-app:/etc/nginx/ssl/"
    log "  docker compose cp epay.key epay-app:/etc/nginx/ssl/"
    log "  然后运行: bash epay.sh restart"
}

# ============================================
# epay update - 拉取代码并更新
# ============================================
cmd_update() {
    log "开始更新..."

    # 检查 git
    if ! git rev-parse --is-inside-work-tree &>/dev/null; then
        err "当前目录不是 Git 仓库，无法自动更新。"
    fi

    # 记录当前版本
    old_version=$(grep "define('VERSION'" includes/common.php | grep -oP "'[0-9]+'")
    old_db_version=$(grep "define('DB_VERSION'" includes/common.php | grep -oP "'[0-9]+'")

    # 备份数据库
    log "备份数据库..."
    cmd_backup

    # 拉取最新代码
    log "拉取最新代码..."
    git stash 2>/dev/null || true
    git pull origin main
    git stash pop 2>/dev/null || true

    # 获取新版本号
    new_version=$(grep "define('VERSION'" includes/common.php | grep -oP "'[0-9]+'")
    new_db_version=$(grep "define('DB_VERSION'" includes/common.php | grep -oP "'[0-9]+'")

    log "版本: ${old_version} -> ${new_version}"

    # 重建容器
    log "重建容器..."
    $COMPOSE_CMD up -d --build

    # 检查是否需要数据库升级
    if [ "$old_db_version" != "$new_db_version" ]; then
        warn "数据库版本有变更 (${old_db_version} -> ${new_db_version})"
        warn "请访问 /install/update.php 完成数据库升级"
    fi

    echo ""
    log "============================================"
    log "  更新完成！"
    log "  版本: ${old_version} -> ${new_version}"
    log "============================================"
}

# ============================================
# epay backup - 备份数据库
# ============================================
cmd_backup() {
    if [ ! -f "$ENV_FILE" ]; then
        err ".env 文件不存在，请先运行: bash epay.sh init"
    fi

    source "$ENV_FILE"

    backup_dir="backups"
    mkdir -p "$backup_dir"
    timestamp=$(date '+%Y%m%d_%H%M%S')
    backup_file="${backup_dir}/epay_db_${timestamp}.sql"

    log "正在备份数据库到 ${backup_file} ..."
    docker exec epay-db mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$backup_file" 2>/dev/null

    if [ -f "$backup_file" ] && [ -s "$backup_file" ]; then
        gzip "$backup_file"
        log "备份完成: ${backup_file}.gz ($(du -h "${backup_file}.gz" | cut -f1))"
    else
        rm -f "$backup_file"
        warn "备份失败或数据库为空。"
    fi

    # 清理 30 天前的备份
    find "$backup_dir" -name "*.sql.gz" -mtime +30 -delete 2>/dev/null || true
}

# ============================================
# epay restart - 重启服务
# ============================================
cmd_restart() {
    log "重启服务..."
    $COMPOSE_CMD restart
    log "重启完成。"
}

# ============================================
# epay logs - 查看日志
# ============================================
cmd_logs() {
    $COMPOSE_CMD logs -f --tail=100
}

# ============================================
# epay status - 查看状态
# ============================================
cmd_status() {
    echo ""
    echo -e "${CYAN}--- 容器状态 ---${NC}"
    $COMPOSE_CMD ps
    echo ""

    # 检查服务是否可访问
    if curl -sf http://localhost:${APP_PORT:-80} -o /dev/null 2>/dev/null; then
        log "HTTP 服务: ${GREEN}正常${NC}"
    else
        warn "HTTP 服务: 不可达"
    fi
}

# ============================================
# epay down - 停止服务
# ============================================
cmd_down() {
    warn "将停止所有 EPay 服务..."
    read -p "确认？(y/N): " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        $COMPOSE_CMD down
        log "服务已停止。"
    fi
}

# ============================================
# epay ssl - 配置 SSL 证书
# ============================================
cmd_ssl() {
    echo -e "${CYAN}--- SSL 证书配置 ---${NC}"
    read -p "证书文件路径 (.pem): " cert_file
    read -p "私钥文件路径 (.key): " key_file

    if [ ! -f "$cert_file" ] || [ ! -f "$key_file" ]; then
        err "文件不存在，请检查路径。"
    fi

    docker cp "$cert_file" epay-app:/etc/nginx/ssl/epay.pem
    docker cp "$key_file" epay-app:/etc/nginx/ssl/epay.key
    docker exec epay-app chmod 600 /etc/nginx/ssl/epay.key

    log "SSL 证书已配置，正在重启..."
    cmd_restart
    log "HTTPS 已启用。"
}

# ============================================
# 主入口
# ============================================
case "${1:-}" in
    init)
        check_docker
        cmd_init
        ;;
    update)
        check_docker
        cmd_update
        ;;
    backup)
        cmd_backup
        ;;
    restart)
        cmd_restart
        ;;
    logs)
        cmd_logs
        ;;
    status)
        cmd_status
        ;;
    down)
        cmd_down
        ;;
    ssl)
        cmd_ssl
        ;;
    *)
        echo ""
        echo -e "${CYAN}EPay CLI - 容器化管理工具${NC}"
        echo ""
        echo "用法: bash epay.sh <command>"
        echo ""
        echo "命令:"
        echo "  init      交互式初始化（首次部署）"
        echo "  update    拉取代码并更新容器"
        echo "  backup    备份数据库"
        echo "  restart   重启服务"
        echo "  logs      查看实时日志"
        echo "  status    查看运行状态"
        echo "  ssl       配置 SSL 证书"
        echo "  down      停止所有服务"
        echo ""
        echo "快速开始:"
        echo "  git clone <repo> /opt/epay && cd /opt/epay"
        echo "  bash epay.sh init"
        echo ""
        ;;
esac
