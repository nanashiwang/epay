# Epay: capability map

Verified from the local checkout on 2026-09-29. This is a scoped navigation aid, not a complete product audit or a replacement for [existing guidance](../README.md). Recheck implementation when using it; extend entries only as tasks confirm the relevant behavior. Do not treat roadmap ideas as implemented features.

| Capability | Entry / UI | API / orchestration | Implementation / data | Review boundary |
|---|---|---|---|---|
| Payment entry and channel selection | [submit.php](../submit.php) | [includes/lib/Order.php](../includes/lib/Order.php) | [includes/lib/Channel.php](../includes/lib/Channel.php) | Inspect both merchant entry and channel selection before adding a second payment route. |
| Payment verification and callbacks | [includes/lib/Payment.php](../includes/lib/Payment.php) | [includes/lib/Plugin.php](../includes/lib/Plugin.php) | [plugins](../plugins) | Provider adapters and central order processing share responsibilities; retain signature verification and duplicate callback handling. |
| Operations and merchant views | [admin/order.php](../admin/order.php) | [user/order.php](../user/order.php) | [admin/transfer_stat.php](../admin/transfer_stat.php) | Compare merchant and admin scope, order state and statistics definitions before adding new views. |
| Configuration and deployment | [epay.sh](../epay.sh) | [docker/entrypoint.sh](../docker/entrypoint.sh) | [docker-compose.yml](../docker-compose.yml) | Runtime config.php and .env are not repository facts. Do not expose secrets or treat a rebuild as a database migration. |

Preserve PHP/MySQL runtime and current deployment scripts. Note checking uses Bun only on developer machines/CI; no production container changes are required.

Before adding a feature, inspect adjacent flows and the current source of truth; shared filters, data definitions and access rules must not diverge across entry points.
