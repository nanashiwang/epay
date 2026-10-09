# 彩虹易支付系统

**彩虹易支付系统** 由郑州追梦网络科技有限公司开发，是一款开源的免签约支付产品，能够帮助开发者一站式接入支付宝、微信、财付通、QQ钱包等多种支付方式，实现高效的支付集成。

---

## 功能特色

- **多渠道支付集成**：支持支付宝、微信、财付通、QQ钱包、微信WAP、银联等多种支付方式  
- **便捷的支付解决方案**：简化支付流程，支持快速集成和上线，提供完整的 API 接口  
- **后台管理和数据统计**：提供支付统计、代付统计、利润分析等多种后台管理功能  
- **安全可靠**：采用 RSA 公私钥验证，支持风控检测和黑名单管理  
- **插件扩展**：支持丰富的支付插件，可根据需求灵活扩展  
- **移动端优化**：全新的手机版支付页面，支持各种移动端支付场景  

---

## 更新日志

### 2025/11/10
1. 后台新增转账付款统计  
2. 付款页面新增最近付款人按钮  
3. 支持开启分账失败的订单 24 小时后重试  
4. 支付通道支持设置开放时间段  
5. 新增订单小票打印功能  
6. API 接口增加参数，可限制买家身份证号 / 姓名 / 最小年龄（仅支持支付宝官方接口）

### 2025/10/23
1. 修复微信收付通合单支付确认结算  
2. 修复轮询情况下订单偶尔支付通道错乱的问题  
3. 未支付订单清理时间调整为 48 小时  

### 2025/09/27
1. 微信小程序支付前支持获取手机号码  
2. 投诉单兼容关联多个订单的情况  
3. 部分支付插件增加关闭订单接口  

---

## Docker 部署

### 环境要求

- Linux 服务器（Ubuntu/Debian/CentOS）
- Git

> Docker 如果未安装，初始化脚本会自动安装。

### 新服务器一键部署

```bash
# 1. 克隆仓库
git clone <your-repo-url> /opt/epay && cd /opt/epay

# 2. 交互式初始化（自动安装 Docker、配置数据库、启动服务）
bash epay.sh init
```

初始化向导会依次询问：
- 站点域名
- HTTP/HTTPS 端口
- MySQL root 密码、数据库名、用户名、密码、表前缀

完成后自动启动容器并输出访问地址。

### 管理命令

```bash
bash epay.sh verify    # 只读运行验收
bash epay.sh init      # 交互式初始化（首次部署）
bash epay.sh update    # 拉取最新代码 + 备份数据库 + 重建容器
bash epay.sh preserve-key # 重建前保全旧容器的收款主密钥
bash epay.sh backup    # 手动备份数据库到 backups/ 目录；主密钥单独备份
bash epay.sh restart   # 重启服务
bash epay.sh logs      # 查看实时日志
bash epay.sh status    # 查看运行状态
bash epay.sh ssl       # 配置 SSL 证书
bash epay.sh down      # 停止所有服务
```

### 更新流程

```bash
cd /opt/epay
bash epay.sh update
```

执行步骤：检查干净的 main 分支 → 备份数据库 → `git pull --ff-only` → 保全收款主密钥 → 重建容器 → 验证迁移、密文、当前容器任务心跳与通知积压。必要检查失败时不会提示更新成功；先备份并按提示完成迁移，再运行 `bash epay.sh verify`。

旧版首次升级必须先获取新脚本，并在旧容器仍运行时保全密钥。包月商户运营扩展还需要显式执行新增迁移；完整命令、备份与验收要求见[商户运营说明](docs/MERCHANT_OPERATIONS.md)。不要先删除旧容器。

### SSL 证书配置

```bash
# 方式一：通过 CLI 工具
bash epay.sh ssl
# 按提示输入证书和私钥文件路径

# 方式二：手动复制
docker cp epay.pem epay-app:/etc/nginx/ssl/epay.pem
docker cp epay.key epay-app:/etc/nginx/ssl/epay.key
bash epay.sh restart
```

### 从现有服务器迁移

```bash
# 1. 在旧服务器导出数据库
mysqldump -u root -p epay > backup.sql

# 2. 在新服务器初始化
cd /opt/epay && bash epay.sh init

# 3. 导入数据
docker exec -i epay-db mysql -u root -p<password> epay < backup.sql
```

### 架构说明

```
docker-compose.yml
├── epay-app (Nginx + PHP 8.3 FPM)
│   ├── 项目代码挂载 /var/www/epay
│   └── SSL 证书挂载 /etc/nginx/ssl
└── epay-db (MySQL 8.0)
    └── 数据持久化 Docker Volume
```

**关键设计：**
- `config.php` 和 `.env` 不纳入 Git，代码更新不会覆盖生产配置
- MySQL 数据通过 Docker Volume 持久化，容器重建不丢数据
- `update` 命令执行前自动备份数据库，备份文件保留 30 天
- 内置 Cloudflare Real IP 识别和 HTTPS 检测

### 文件说明

| 文件 | 说明 |
|------|------|
| `Dockerfile` | PHP 8.3 + Nginx Alpine 镜像定义 |
| `docker-compose.yml` | App + MySQL 服务编排 |
| `epay.sh` | CLI 管理工具（init/update/backup 等） |
| `.env.example` | 环境变量模板，复制为 `.env` 使用 |
| `docker/nginx.conf` | Nginx 站点配置（含 Cloudflare + SSL） |
| `docker/php.ini` | PHP 运行参数 |
| `docker/entrypoint.sh` | 容器启动脚本，自动生成 config.php |
| `docker/supervisord.conf` | Nginx + PHP-FPM 进程管理 |

---

## 打赏二维码

如果你觉得对你有帮助，欢迎打赏支持 ❤️

### 微信打赏
<img src="https://cdn.nodeimage.com/i/kgpolIW90QcsVO85dhO0li6ZDj40KttH.webp" width="180" />


---

## 商户包月与自配支付通道

商户购买包月服务后，可自行配置支付宝、微信、QQ、易支付兼容网关、支付宝收款码及 BEpusdt。管理员管理套餐与平台月费收款，无需为每位商户录入收款密钥。具体支持模式、显式迁移步骤和权限边界见[商户支付通道说明](docs/MERCHANT_CHANNELS.md)。

## 用户帮助中心

用户中心点击“帮助中心”，或访问本站 `/index.php?doc=help`，可按分类阅读、搜索操作步骤。支付通道、支付宝收款码、USDT / USDC 收款和套餐页面也提供对应教程入口，未登录用户同样可以阅读。

仓库内可从[帮助目录](docs/help/README.md)或[第一次使用](docs/help/start.md)开始。22 篇本地教程覆盖套餐、支付宝与微信配置、密钥、BEpusdt、网站接入、通知验签、退款和排障；另有[支付FM的 97 篇来源索引](docs/help/sources.md)。教程按本项目实际功能编写，原站专属的收费、软件和接口已标注适用范围，不直接作为本站操作流程。

## BEpusdt 商户包月接入

商户可在用户中心自行配置 BEpusdt，按套餐开通 USDT / USDC 多网络直收。请先阅读[配置、迁移与验收说明](docs/BEPUSDT_SUBSCRIPTION.md)；升级代码不会自动开通套餐或迁移数据库。上游基线见[外部上游追踪](docs/UPSTREAMS.md)。

## 推荐插件

推荐使用 **Bepusdt** 插件进行 USDT / USDC 多网络收款。
Bepusdt 是适用于彩虹易支付系统的稳定币收款插件，收到的货币直接转入商户钱包，不经过任何第三方。

**插件开源地址**：  
🔗 [https://github.com/v03413/bepusdt](https://github.com/v03413/bepusdt)

---
