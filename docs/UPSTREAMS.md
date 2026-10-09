# 外部上游追踪

核对日期：2026-10-09。以下项目是 Epay 的外部网关和插件来源，独立追踪，不是可直接合并进 Epay 的同源分支。本文件记录审计基线，不代表已部署版本。

| 上游 | 用途 | 本地远端 | 本次审计 main 提交 |
|---|---|---|---|
| [v03413/BEpusdt](https://github.com/v03413/BEpusdt) | 独立加密货币收款网关、扫链、确认、汇率和收银台 | `bepusdt` | `aa3bd5097f258cccd5a53baed7211c74733b8332` |
| [v03413/Epay-BEpusdt](https://github.com/v03413/Epay-BEpusdt) | 官方 PHP 接入插件，对照本地 `plugins/bepusdt/bepusdt_plugin.php` | `bepusdt-plugin` | `f85e52278327fe6182d397c8396fc3939ba471c7` |

本次 GitHub 查询的网关最新正式 Release 是 [v1.24.2](https://github.com/v03413/BEpusdt/releases/tag/v1.24.2)，发布于 2026-08-03。`main` 文档及源码可能领先该版本；上线必须针对选定发布版本重新做契约测试，不把 main 功能视为稳定版已具备。

## 初始化与检查

当前工作副本已配置以下两个远端，并禁止向其推送；Git remote 配置不会随 clone 传播。在新工作副本先用 `git remote -v` 检查，仅对缺失的远端执行相应 `remote add`，已有同名远端先核对 URL，不能直接覆盖。

```bash
git remote add bepusdt https://github.com/v03413/BEpusdt.git
git config remote.bepusdt.tagOpt --no-tags
git config remote.bepusdt.pushurl disabled://read-only
git remote add bepusdt-plugin https://github.com/v03413/Epay-BEpusdt.git
git config remote.bepusdt-plugin.tagOpt --no-tags
git config remote.bepusdt-plugin.pushurl disabled://read-only
```

后续按各自审计基线比较；不拿 Epay main 与 Go 网关计算待合并提交。

```bash
git fetch --no-tags bepusdt main
git fetch --no-tags bepusdt-plugin main
git log --oneline aa3bd5097f258cccd5a53baed7211c74733b8332..bepusdt/main
git diff --stat aa3bd5097f258cccd5a53baed7211c74733b8332 bepusdt/main
git log --oneline f85e52278327fe6182d397c8396fc3939ba471c7..bepusdt-plugin/main
git diff HEAD:plugins/bepusdt/bepusdt_plugin.php bepusdt-plugin/main:bepusdt_plugin.php
gh api repos/v03413/BEpusdt/releases/latest --jq '{tag_name,published_at,html_url}'
```

检查行为仅 fetch、读取与比较；不自动 merge/rebase/cherry-pick、替换插件、更新容器或部署。`--no-tags` 避免把独立项目版本标签混入 Epay。本轮没有创建定时检查任务；这是可重复执行的上游追踪入口。

每次审计报告新增提交、正式版本、安全/兼容变化、候选适配和风险。发现更新与批准采用分开；基线只在实际审阅后更新，不能每次 fetch 就覆盖。

## 本次发现

- 本地已有 BEpusdt 插件，不需要新建另一套支付引擎。
- 官方插件比本地增加了交易类型、法币选择、JSON 数值金额，以及严格签名格式和 `hash_equals` 校验。本次仅比较，未替换本地插件。
- 网关当前 `createReq.Amount` 为 `float64`；本地插件直接将订单金额放入 JSON，在数据库返回字符串时存在类型兼容风险，需用实际请求契约测试确认。
- 官方插件当前仍关闭 TLS 证书验证，且成功通知没有金额核验；同步上游不能替代本项目安全校验。
- 网关 `EpNotify` 实际字段包括 `trade_id/order_id/amount/actual_amount/token/block_transaction_id/signature/status`；`token` 是收款地址，当前结构没有 `buyer`、网络或法币字段。回调文档示例与源码不完全一致，应锁定版本并以真实载荷验收。
- `model.AuthToken()` 返回实例级配置，不能据此假定有商户级密钥和权限隔离。
- 网关拥有独立存储和升级周期；Epay 保持 PHP/MySQL 及现有部署脚本。网关源码 LICENSE 为 GPL-3.0；如后续修改、分发镜像或代码，应保留来源并评估相应交付义务，本轮不复制其代码。

重点跟踪：`app/handler/epusdt`、`app/task/notify`、`app/task` 扫链与确认、`app/model` 订单/钱包/汇率、`docs/api`、`docs/trade-type.md`、`docs/docker`、Release 附件和官方 PHP 插件。

融合方案见[商户订阅与 BEpusdt 接入提案](../.agents/notes/proposed/architecture/2026-10-09-bepusdt-merchant-subscription.md)。
