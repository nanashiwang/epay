# 管理员：启用功能与运行检查

本页用于理解开通顺序；执行前仍应阅读仓库中的运行说明并备份。文档更新、代码推送和容器重建都不等于数据库迁移已经完成。

## 初始化顺序

先备份数据库和站点外收款加密主密钥，再在本站 PHP 运行环境执行。前两项已安装时可跳过。

```sh
php scripts/collection-setup.php --apply
php scripts/bepusdt-setup.php --apply
php scripts/merchant-channel-setup.php --apply
php scripts/merchant-operations-setup.php --apply
php scripts/payment-review-setup.php --apply
```

迁移创建扩展表和停用的系统模板，不自动定价、开通旧用户权益或填写商户密钥。系统模板不应被启用到全站随机通道池。

## 运行检查

- PHP 进程和 CLI 能读取同一份站点外主密钥；容器重建后文件仍持久存在。
- collection-worker 处理原生码监测与通知重试；独立 subscription-worker 处理已开启的套餐邮件提醒。两者分别记录完成心跳。
- 运营迁移完成后，后台“套餐异常处理”可查看已付款冲突。
- Docker 使用站点外主密钥持久卷；旧版首次升级须在销毁旧容器前保全原密钥，具体命令见仓库 `docs/MERCHANT_OPERATIONS.md`。
- 支付机构能访问本站回调地址，本站能访问必要的机构接口。
- 平台月费商户与平台通道可用，套餐购买开关、售价、周期和可见范围正确。
- 商户页面能新增配置、保存后不回显密钥、未测试账号不能启用。

BEpusdt 的部署和扫链健康由对应网关维护，不是 collection-worker 的职责。

## 验收与回滚

先用独立环境验证配置和页面，再由商户主动完成真实小额收款及业务通知核验。若需回滚，先停止新订单，保留在途回调代码、扩展表、主密钥和通知 worker，直到原订单处理完成。

不要直接删除密文、快照或主密钥，也不要把生产凭据复制进测试数据库。历史订单的验证能力依赖这些数据。

## 仓库运行文档

[通用商户通道](/docs/MERCHANT_CHANNELS.md) · [BEpusdt 订阅](/docs/BEPUSDT_SUBSCRIPTION.md) · [支付宝原生码](/docs/MERCHANT_COLLECTION.md)

更新后运行 `bash epay.sh verify`，核对迁移、历史密文可读、后台任务与通知积压。提示失败时按说明修复，迁移前先备份；不要通过新建密钥解决旧密文解密失败。后台“订阅运营看板”查看套餐成交与到期名单，“支付异常核对”登记依据和重试业务通知。
