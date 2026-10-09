# Agent Note: 收款与套餐提醒进程隔离

Status: implemented

## Problem

现有 collection-worker 同步发送最多 50 封邮件，慢 SMTP 会推迟收款查询。通知开关虽可刷新，但 SMTP 参数常驻不更新；运维只能看到进程存在，缺少完成一轮工作的证据。

## Decision

复用提醒账本和邮件发送器，移至独立 subscription-worker，由同一 Docker 监督器或独立 systemd 单元管理。两个进程各自单例、各自记录无敏感数据的完成心跳，邮件配置每轮刷新；collection 不再调用邮件。心跳复用 pre_cache 的独立键，不新增生产依赖或自动迁移。

## Related notes

[包月运营](../feature/2026-10-09-merchant-operations.md)部分重叠：拆分其共用 worker 决定，提醒去重和重试不变。[商户收款](../feature/2026-10-09-merchant-collection.md)保留轮询与业务通知机制；其他支付、帮助笔记无关。

## Alternatives considered

- 限制单轮邮件数量：修改少，但即使一封慢邮件仍阻塞查账，不能隔离故障。
- 引入 Redis 队列：支持更大规模，但现有 MySQL 提醒账本已足够，增加部署成本。

## Testing

隔离 MySQL 中用慢邮件替身运行实际提醒入口，同时运行实际收款入口，核对心跳独立更新、提醒幂等、开关关闭不投递。Docker 构建和两个监督进程检查通过。

## Consequences

增加一个常驻进程，非 Docker 部署必须新增服务。业务回调重试仍与原生码监测共用原进程；本项仅隔离套餐邮件。邮件继续采用至少一次投递。
