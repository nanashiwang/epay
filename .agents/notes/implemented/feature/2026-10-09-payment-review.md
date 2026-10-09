# Agent Note: 支付异常核对与处理留痕

Status: implemented

## Problem

创建结果未知、超时未确认和通知失败分散在通道记录里；缺少统一核对入口及处置理由，不能通过直接修改付款状态恢复。

## Decision

商户仅访问自己的异常，管理员访问全站；复用订单、快照、收款与通知记录，追加核对审计。提供登记依据、已付款通知重试、BEpusdt 有 provider_id 时按原快照查单。先落审计意图，再外部操作，唯一请求键避免刷新重放；状态变化前重新核对归属。原始异常不会因写备注而消失。

## Related notes

[订单保留](../bug-fix/2026-10-09-order-retention.md)和[包月运营](2026-10-09-merchant-operations.md)部分重叠，分别提供凭证与运营入口；[BEpusdt](../architecture/2026-10-09-bepusdt-merchant-subscription.md)回调验签和金额身份校验保留。

## Alternatives considered

- 人工直接设置已支付：看似恢复快，但没有签名和链上唯一性证据，会破坏到账幂等。
- 查单自动补单：已检查本地跟踪的 BEpusdt app/router/epusdt.go 与 handler 的 Info，接口按已知 trade_id 返回收银台信息，无签名及独立交易哈希字段，也不能按本地单号查创建未知单；故仅显示核对证据，真实入账继续等待原有完整签名回调。

## Testing

30 项 MySQL 检查通过，验证租户隔离、管理员权限、CSRF、幂等重放、速率限制、审计失败前不调用外部操作、BE 查单字段核对及记录不含凭据；实际页面验证处理、错误、刷新、手机与暗色。

## Consequences

查单只能覆盖已知网关单号，unknown 需到网关后台核对。备注不执行退款、补记付款或解除异常。收款流水未匹配仍从原收款码记录处理。外部请求与审计完成非原子，处理中断需人工核对，不能直接重放。
