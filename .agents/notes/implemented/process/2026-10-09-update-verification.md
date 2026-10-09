# Agent Note: 更新后的自动运行验收

Status: implemented

## Problem

容器重建命令返回后立即显示更新完成，可能掩盖缺失迁移、旧密文无法解密、后台任务未完成一轮或通知积压；自动 stash 也可能留下未解决冲突。

## Decision

新增只读 CLI 验收，读取真实数据库配置与扩展表列，验证站点外密钥和各类最近密文样本、collection/subscription 完成心跳及业务通知积压。更新拒绝脏工作区与非主线，使用 fast-forward 拉取，备份/重建/验收失败都停止成功提示；不自动迁移。提供独立 verify 命令和有限的任务启动等待。Docker 每次启动生成新任务标识，旧容器心跳不通过验收。PdoHelper 增加可选的连接异常抛出模式，健康检查启用它，避免旧构造器直接 exit(0) 误报成功和输出连接细节。

## Related notes

[运营部署](../feature/2026-10-09-merchant-operations.md)和[任务隔离](../architecture/2026-10-09-worker-isolation.md)部分重叠，分别扩展原更新脚本与心跳；其他笔记仅用作表结构事实，不改变支付行为。

## Alternatives considered

- 仅检查 HTTP 200：安装提示页也会 200，不能证明数据库和 worker 正常。
- 更新时自动迁移并自动回滚：更省步骤，但主表与在线支付副作用不能可靠逆转，沿用显式备份和迁移。

## Testing

12 项隔离 MySQL 检查通过，验证健康、缺表/列、错误密钥、过期任务和积压告警；实际生产镜像下以 www-data 运行 CLI；7 条 shell 测试确认备份、构建和验收失败不显示成功。

## Consequences

密文只抽取各类型最近记录，不能证明全历史均完整。外部支付机构、SMTP 与真实收款仍需独立验收。通知积压为提示，不将短暂下游不可用等同应用启动失败。
