# 配置微信支付 V3

入口：[支付通道](/user/channels.php) → 添加支付通道 → 微信官方支付 V3。当前自助入口支持微信支付公钥模式的 Native 扫码和 H5。

## 参数填写对照

| 字段 | 说明 |
| --- | --- |
| 关联的 AppID | 已与收款商户号关联的微信 AppID，格式为 wx 加 16 位字母数字 |
| 商户号 | 收款商户编号，不是 AppID |
| APIv3 密钥 | 32 位 APIv3 密钥，不是 APIv2 密钥或私钥 PEM |
| 商户 API 证书序列号 | 商户 API 证书对应的序列号 |
| 微信支付公钥 ID | 与下方微信支付公钥配套的 PUB_KEY_ID_ 开头标识 |
| 商户 API 私钥 PEM | 商户 API 证书配套的私钥，保留完整 PEM 内容 |
| 微信支付公钥 PEM | 微信支付提供的验签公钥，保留完整 PEM 内容 |

## 配置步骤

1. 登录[微信支付商户平台](https://pay.weixin.qq.com/)，核对商户号、产品开通状态和 AppID 关联状态。
2. 在 API 安全相关页面准备 APIv3 密钥、证书序列号、商户私钥和配套的微信支付公钥及 ID。以官方后台的当前名称为准。
3. 将对应值填入本站；私钥和公钥直接粘贴，本站加密保存，不需要管理员上传证书文件。
4. 只勾选已经开通的 Native 或 H5 产品。H5 所需域名等配置由商户按机构要求完成。
5. 保存、测试到账、启用并设为微信默认，然后验证实际业务网站。

## 容易混淆的情况

微信支付公钥 ID 与商户证书序列号不是同一个值。APIv3 密钥也不是商户 API 私钥。本站不会在公钥 ID 不匹配时自动改用共享证书；报错时应核对整套参数。

公众号 JSAPI 和小程序接口尚未进入通用自助配置。请勿照搬原站的 payType、微信授权域名或小程序参数。

## 参考来源

[支付FM：微信官方](https://docs.zhifux.com/read/zhifufm/wxpay) · [AppID 关联](https://docs.zhifux.com/read/zhifufm/wxappid) · [查看微信支付公钥](https://docs.zhifux.com/read/zhifufm/wxpublickey)。
