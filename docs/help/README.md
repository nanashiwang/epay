# 用户帮助中心

站内入口为 `/index.php?doc=help`，已有伪静态配置也可使用 `/doc/help.html`。文章由 `catalog.json` 显式登记，正文为同名 Markdown 文件。公开读取，不查询商户账号或展示生产配置。

## 阅读目录

### 开始使用

- [第一次使用：从开通到收到第一笔款](start.md)
- [套餐购买、续期与账号数量](plans.md)
- [选择适合自己的支付方式](choose.md)

### 支付配置

- [配置支付宝官方支付](alipay.md)
- [分清支付宝公钥、应用公钥与私钥](alipay_keys.md)
- [配置微信支付 V3](wechat_v3.md)
- [配置微信支付 V2](wechat_v2.md)
- [配置 QQ 钱包](qqpay.md)
- [接入你自己的易支付兼容网关](epay_gateway.md)
- [配置支付宝原生收款码](native_qr.md)
- [配置 USDT / BEpusdt](bepusdt.md)

### 接入与验收

- [将业务网站或现成插件接到本站](integration.md)
- [支付通知、验签与业务发货](callbacks.md)
- [配置完成后如何验收](testing.md)

### 订单与排障

- [订单、流水与通知记录怎么看](orders.md)
- [收款故障排查](troubleshooting.md)
- [密钥管理与账号维护](security.md)
- [退款、金额异常与投诉处理](refunds.md)

### 管理员

- [管理员：创建包月套餐](admin_plans.md)
- [管理员：启用功能与运行检查](admin_setup.md)

### 参考资料

- [原站教程与本站功能有哪些区别](limitations.md)
- [支付FM文档来源索引](sources.md)

## 内容维护规则

- `catalog.json` 是分类、标题、简介、搜索词与排序的来源；新增文章时同时登记。
- 支持一级标题（页面单独展示）、二/三级标题、单层有序/无序列表、表格、围栏代码、粗体、行内代码和链接。不支持 HTML、图片、嵌套列表或任意脚本。
- 本地文章链接使用 `alipay.md` 这类目录内名称；站内入口用绝对路径，外部引用只允许 HTTPS。标题、查询词和正文均转义，不使用用户传入的文件路径。
- 先核对实际表单、权限和 API，再修改教程；菜单名称、账号限额、金额和状态必须与实现对应。
- 源站目录首次核对覆盖 97 页，全部获取成功。`sources.json` 保存结果与正文指纹，`sources.md` 是用户可读的对照目录。原始全文仅用于本次本地资料研究，不随仓库再分发。
- 原站专属收费、监测软件、机构签约、下载包和私有接口不能改名后冒充本站能力；未开放功能明确标注。机构后台可能变化，使用官方入口并避免固定无法核实的审核承诺。

## 验证

```sh
php tests/help-center.php
php -l includes/lib/HelpCenter.php
php -l template/default/doc/help.php
node --check assets/help/help.js
bash .agents/verify-notes.sh
```

PHP 测试不需要数据库或真实凭据，检查文章目录、来源完整性、本地链接、搜索、安全渲染及页面状态。浏览器还需验证公开访问、桌面、窄屏、深色、搜索无结果、错误文章及入口跳转。

如需复核来源，使用 `python3 scripts/check-help-sources.py`。脚本只比较已登记的公网原文，不写入目录或正文；输出变化和失败后，人工复核相应教程，再更新来源清单。CI 不依赖原站联网状态。
