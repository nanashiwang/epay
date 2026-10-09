# 包月商户运营与 Docker 更新

本扩展复用现有用户组、订阅事件和通知任务。管理员设定套餐，商户自行配置收款账户；不自动扣款、不代商户退款，也不把直收本金变成平台余额。

## 新增迁移

已完成 collection、bepusdt、merchant-channel 迁移的站点，备份数据库后执行：

```sh
php scripts/merchant-operations-setup.php --apply
```

仅创建 `subscription_resolution`、`subscription_reminder` 两张表，可重复执行，不修改价格、有效期或支付状态。未执行时管理员页面提示迁移，邮件提醒不运行；原有收款路径仍使用已有表。Docker 内命令为 `docker compose exec --user www-data app php scripts/merchant-operations-setup.php --apply`。

## 旧 Docker 首次升级

旧主密钥可能只在旧容器 `/var/www/epay-collection.key` 中。先备份数据库并获取本版本代码，然后在旧应用容器仍运行时保全密钥，再重建：

```sh
bash epay.sh backup
git pull --ff-only origin main
bash epay.sh preserve-key
docker compose up -d --build
docker compose exec --user www-data app php scripts/merchant-operations-setup.php --apply
```

不要先 `down`、删除旧容器或重新生成密钥。保全脚本读取旧容器的 `EPAY_COLLECTION_KEY_FILE`，复制 32 字节文件至 `collection_keys` 持久卷，检查已有文件必须一致，并设置 PHP 服务用户与 0600 权限。缺失、冲突、无法确认状态都会停止更新；日志不输出密钥。后续 `bash epay.sh update` 和初始化重建会自动先执行保全步骤。

默认卷名 `epay_collection_keys`；自定义名称使用 Compose 的 `EPAY_COLLECTION_KEY_VOLUME`。脚本通过 Docker Compose v2 解析 `.env`，不执行其中的 shell 内容。自定义应用容器名时为保全命令设置 `EPAY_APP_CONTAINER`。卷内文件在新容器中的位置为 `/var/lib/epay-keys/collection.key`，位于网站根目录外。

数据库备份不包含主密钥。使用主机的备份工具，单独加密备份该卷，限制备份访问，并验证与数据库配套恢复。不要执行 `docker compose down -v` 或直接替换卷内密钥。

全新安装仍需按[商户通道部署说明](MERCHANT_CHANNELS.md)完成四项迁移；Docker 内推荐以 `--user www-data` 执行。collection 初始化可创建首个主密钥；已有 collection 配置但文件缺失时会拒绝生成新文件。

## 任务与提醒

Supervisor 管理 `collection-worker`：基础表和可读的 32 字节主密钥未就绪时等待，就绪后运行原生码监测与通知重试。异常退出自动重启。非 Docker 继续使用现有 systemd 单元或 `php scripts/collection-worker.php`。

独立 `subscription-worker` 每 5 分钟检查一次套餐提醒，每轮重新读取邮件配置。慢 SMTP 不再阻塞收款监测。Docker 自动管理两个进程；非 Docker 增加 `scripts/epay-subscription.service`（按部署位置调整工作目录），或单独运行 `php scripts/subscription-worker.php`。两个任务完成一轮后分别写入无敏感信息的心跳。后台原有“会员到期邮件通知”开关为启用状态、SMTP 可用且商户邮箱有效时，在 7/3/1 天提醒阶段及到期后分别发送。以商户、到期时间和阶段去重，失败 1 小时后重试；每次最多尝试 50 封。续期后的新到期时间重新计数。任务停机后只发送当前阶段，不补发所有错过阶段；到期超过 7 天不补发。邮件与数据库无法原子提交，崩溃时可能重复投递；这不影响收款和权益。

站内提醒即时计算，不依赖邮件开关。首页及“我的套餐”在到期前 7 天内和到期后显示提示。关闭购买或平台收月费账号缺失时，已有权益和最近 20 笔历史仍可查看。

## 异常付款与统计

后台“商户管理 → 套餐异常处理”核对 `review` 状态的已付款套餐。开通不同套餐会替换当前权益，按处理时起算已购月数；同套餐从当前有效期与现在的较晚时间累加。另一选项只登记已经完成的外部处理，必须提供凭证编号，不执行转账或退款。

处置需要管理员会话、CSRF、原因、影响确认及未变化的权益版本。订单、权益、事件和审计在事务内处理；重复提交、并发冲突或审计保存失败均拒绝变更。记录保留操作者、订单、金额、时间和前后权益；原因、凭证对商户可见。

工作台和商户收入统计只计算 `tid=0` 已付款业务订单，排除测试和平台业务。按日邀请返现依据历史订单凭证排除自助通道、商户 BEpusdt、新订阅付款和测试订单；原生码零费直收按账号归属与实收/入账金额相等判断，旧收费订单保留原返现路径。此规则不追溯追回既有返现，也不因商户后来换套餐而改变历史分类。

## 验证与发布边界

`tests/merchant-operations.php` 在独立 `epay_bepusdt_*test` MySQL 数据库验证处置、返现、历史、统计和提醒。`tests/docker-operations.sh` 只创建临时容器、卷和合成主密钥，检查重建后解密、权限、重启以及缺失/冲突拒绝。生产 Docker 镜像也会包含帮助文章。

无 SSL 证书时，从只读 Nginx 模板生成完整 HTTP 配置；证书到位后重启恢复 HTTPS 块。启动前执行 `nginx -t`，避免隐藏配置损坏。

代码推送、CI 和本地合成验收不代表生产已迁移或真实邮件已送达。部署后仍需确认通知进程、主密钥恢复能力、普通商户页面、实际邮件与商户主动的小额收款。当前仓库没有自动 Release/安装包流程，运行版本沿用 `3096` / 数据库版本 `2053`；扩展表由上述显式迁移维护。
