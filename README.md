# LightSNS IndexNow 自动提交

这是一个适用于 LightSNS 的 PC 页面插件，用于维护 IndexNow 验证 Key，并在内容发布、更新或手动补扫时向 IndexNow 端点提交站内内容 URL。

## 功能特性

- 自动生成并维护 IndexNow Key 验证文件。
- 监听帖子发布、更新、状态变更事件，自动提交已发布内容 URL。
- 支持手动提交单条 URL。
- 支持最近内容补扫，适合修复 Worker 延迟或历史内容漏提交。
- 支持批量预览并逐条提交未提交或最近失败的内容链接。
- 提供提交日志、状态筛选、分页、行内重试。
- 内置 URL 过滤，避免提交模块资源、静态资源、上传资源等非内容页面。
- 对成功提交做去重窗口控制，避免频繁重复提交同一 URL。
- 遇到 HTTP 429 时支持有限退避重试。
- 提供诊断面板，方便排查 422、403、KeyLocation 不匹配等问题。

## 目录名称

插件目录必须保持为：

```text
czzz-pc-page-indexnow-auto-submit
```

推荐安装路径：

```text
module/pc/page/czzz-pc-page-indexnow-auto-submit
```

## 使用方式

1. 将插件目录上传到 LightSNS 的 `module/pc/page/` 目录。
2. 在后台模块管理中启用 `IndexNow 自动提交`。
3. 进入插件设置页，确认 `启用自动提交` 和 `监听帖子发布/更新事件` 已开启。
4. 点击 `维护验证文件`，确认验证文件公开访问正常。
5. 发布一篇测试帖子，稍等后台异步任务消费后，在 `提交日志` 中查看提交结果。

## 注意事项

- Bing 站长后台通常不会实时显示插件侧的批量或自动提交按钮，它主要显示已提交 URL 的结果列表。
- 自动提交依赖 LightSNS 的事件与 Worker 消费链路，正常情况下会有短暂延迟。
- 如果出现 HTTP 422，请优先检查 KeyLocation 是否公开可访问、内容是否与当前 Key 一致、提交 URL 是否属于同一站点。

## 作者信息

作者：云遮天  
Telegram：[@czzzru](https://t.me/czzzru)  
网站：[https://czzz.ru](https://czzz.ru)

## 开源协议

本项目使用 GPL-3.0 协议开源，详见 `LICENSE`。

