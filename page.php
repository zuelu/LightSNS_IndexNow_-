<?php

ob_start();

require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'indexnow_lib.php';

use LightSNS\Foundation\Site;

if (function_exists('czzz_sitemap_clear_output_buffers')) {
    czzz_sitemap_clear_output_buffers();
} else {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

$summary = czzz_indexnow_summary();
$verification = is_array($summary['verification'] ?? null) ? $summary['verification'] : [];
$styleUrl = czzz_indexnow_module_url('module.css');
$scriptUrl = czzz_indexnow_module_url('module.js');
$apiUrl = czzz_indexnow_module_url('api.php');
$key = (string) ($summary['key'] ?? '');
$endpoint = (string) ($summary['endpoint'] ?? '');
$keyLocation = (string) ($summary['key_location'] ?? '');
$isAdmin = czzz_indexnow_is_admin();
$canViewReadonly = czzz_indexnow_can_view_readonly();
$embed = !empty($_GET['embed']);
$defaultLogLimit = 50;
$defaultLogStatus = 'all';
$canShowSensitive = $isAdmin;
$displayKey = $canShowSensitive ? $key : czzz_indexnow_mask_key($key);
$displayKeyLocation = $canShowSensitive ? $keyLocation : '';
$displayFilePath = $canShowSensitive ? (string) ($verification['path'] ?? '') : '仅管理员可见';
$allowedSections = ['all', 'info', 'manual', 'batch', 'logs'];
$section = strtolower((string) ($_GET['section'] ?? 'all'));
if (!in_array($section, $allowedSections, true)) {
    $section = 'all';
}
$showAll = $section === 'all';
$showHero = !$embed || $showAll;
$showInfoSection = $showAll || $section === 'info';
$showManualSection = $showAll || $section === 'manual';
$showBatchSection = $showAll || $section === 'batch';
$showLogsSection = $showAll || $section === 'logs';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IndexNow 自动提交</title>
    <link rel="stylesheet" href="<?php echo Site::escAttr($styleUrl); ?>">
</head>
<body class="<?php echo $embed ? 'czzz-indexnow-embed-body' : ''; ?>">
<div class="czzz-indexnow-page<?php echo $embed ? ' is-embed' : ''; ?>"
     data-api-url="<?php echo Site::escAttr($apiUrl); ?>"
     data-default-status="<?php echo Site::escAttr($defaultLogStatus); ?>"
     data-default-limit="<?php echo $defaultLogLimit; ?>"
     data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>"
     data-can-view-readonly="<?php echo $canViewReadonly ? '1' : '0'; ?>">
    <?php if ($showHero): ?>
    <header class="czzz-indexnow-hero">
        <div>
            <p class="czzz-indexnow-kicker">Czzz.ru IndexNow</p>
            <h1>IndexNow 自动提交</h1>
            <p>单栏控制台，集中查看 Key、验证状态、实时日志、批量补提与逐条重提结果。</p>
        </div>
        <span class="czzz-indexnow-badge <?php echo !empty($verification['ok']) ? 'is-ok' : 'is-warn'; ?>"><?php echo !empty($verification['ok']) ? '验证文件已就绪' : '验证文件异常'; ?></span>
    </header>
    <?php endif; ?>

    <?php if (!$isAdmin && $canViewReadonly && ($showAll || $showManualSection || $showBatchSection)): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>只读模式</h2>
                <p>当前账号不是管理员，只能查看 Key、验证状态与提交日志；提交、重试、批量补提和维护操作均不可用。</p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$canViewReadonly): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>访问提示</h2>
                <p>请先登录后查看 IndexNow 页面；管理员登录后可进行提交与维护，普通用户登录后仅能查看脱敏后的日志与状态概览。</p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showInfoSection): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>基础信息</h2>
                <p><?php echo $canShowSensitive ? '这里显示当前使用中的真实配置与验证状态。' : '这里只显示只读概览；Key、文件路径和敏感诊断信息仅管理员可见。'; ?></p>
            </div>
        </div>
        <div class="czzz-indexnow-info-grid">
            <article class="czzz-indexnow-info-item">
                <h3>当前 Key</h3>
                <code><?php echo Site::escHtml($displayKey); ?></code>
                <p>KeyLocation：<?php if ($displayKeyLocation !== ''): ?><a href="<?php echo Site::escAttr($displayKeyLocation); ?>" target="_blank" rel="noopener noreferrer"><?php echo Site::escHtml($displayKeyLocation); ?></a><?php else: ?>仅管理员可见<?php endif; ?></p>
            </article>
            <article class="czzz-indexnow-info-item">
                <h3>提交端点</h3>
                <p><?php echo Site::escHtml($endpoint); ?></p>
                <p>文件路径：<?php echo Site::escHtml($displayFilePath); ?></p>
            </article>
            <article class="czzz-indexnow-info-item">
                <h3>验证状态</h3>
                <p>HTTP：<?php echo (int) ($verification['http_code'] ?? 0); ?></p>
                <p>内容匹配：<?php echo !empty($verification['body_ok']) ? '是' : '否'; ?></p>
            </article>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($isAdmin && $showManualSection): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>手动操作</h2>
                <p>支持单条 URL 提交、验证文件维护、Key 重建与最近内容补扫。</p>
            </div>
        </div>
        <form class="czzz-indexnow-submit-form">
            <input type="url" name="url" placeholder="https://czzz.ru/article/123" required>
            <button type="submit">提交 URL</button>
        </form>
        <div class="czzz-indexnow-actions">
            <button type="button" class="czzz-indexnow-maintain-key">维护验证文件</button>
            <button type="button" class="czzz-indexnow-regenerate-key">重新生成 Key</button>
            <button type="button" class="czzz-indexnow-repair-recent">补扫最近已发布内容</button>
        </div>
        <p class="czzz-indexnow-message" aria-live="polite"></p>
    </section>
    <?php endif; ?>

    <?php if ($isAdmin && $showBatchSection): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>批量提交</h2>
                <p>从全站真实链接池中找出未提交过或最近失败的内容页，逐条顺序提交并实时回显状态。</p>
            </div>
        </div>
        <div class="czzz-indexnow-batch-toolbar">
            <label>
                <span>批量范围</span>
                <select class="czzz-indexnow-batch-mode">
                    <option value="never_submitted">所有未提交过的链接</option>
                    <option value="unsubmitted_or_failed">所有未提交过或最近失败的链接</option>
                </select>
            </label>
            <div class="czzz-indexnow-actions">
                <button type="button" class="czzz-indexnow-preview-batch">预览候选</button>
                <button type="button" class="czzz-indexnow-start-batch">开始逐条提交</button>
            </div>
        </div>
        <div class="czzz-indexnow-batch-summary">尚未加载候选链接。</div>
        <div class="czzz-indexnow-batch-preview"></div>
        <div class="czzz-indexnow-batch-progress"></div>
        <div class="czzz-indexnow-table-wrap">
            <table class="czzz-indexnow-table czzz-indexnow-batch-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>URL</th>
                    <th>状态</th>
                    <th>HTTP</th>
                    <th>尝试</th>
                    <th>说明</th>
                </tr>
                </thead>
                <tbody class="czzz-indexnow-batch-results">
                <tr><td colspan="6">批量任务尚未开始</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canViewReadonly && $showLogsSection): ?>
    <section class="czzz-indexnow-card">
        <div class="czzz-indexnow-section-head">
            <div>
                <h2>提交日志</h2>
                <p>真实数据直接来自模块日志表，支持分页、状态筛选、每页条数调整和行内重新提交。</p>
            </div>
        </div>
        <div class="czzz-indexnow-log-toolbar">
            <label>
                <span>状态</span>
                <select class="czzz-indexnow-log-status">
                    <option value="all">全部</option>
                    <option value="success">成功</option>
                    <option value="accepted">已接收</option>
                    <option value="deduped">去重跳过</option>
                    <option value="filtered">已过滤</option>
                    <option value="disabled">已禁用</option>
                    <option value="failed">失败</option>
                </select>
            </label>
            <label>
                <span>每页显示</span>
                <select class="czzz-indexnow-log-limit">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                    <option value="1000">1000</option>
                </select>
            </label>
            <div class="czzz-indexnow-actions">
                <button type="button" class="czzz-indexnow-refresh-logs">刷新日志</button>
            </div>
        </div>
        <div class="czzz-indexnow-log-summary">正在加载日志...</div>
        <div class="czzz-indexnow-table-wrap">
            <table class="czzz-indexnow-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>URL</th>
                    <th>类型</th>
                    <th>端点</th>
                    <th>状态</th>
                    <th>HTTP</th>
                    <th>尝试</th>
                    <th>错误</th>
                    <?php if ($isAdmin): ?><th>操作</th><?php endif; ?>
                </tr>
                </thead>
                <tbody class="czzz-indexnow-log-body">
                <tr><td colspan="<?php echo $isAdmin ? 9 : 8; ?>">正在加载日志...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="czzz-indexnow-pagination">
            <button type="button" class="czzz-indexnow-page-prev">上一页</button>
            <span class="czzz-indexnow-page-info">第 1 / 1 页</span>
            <button type="button" class="czzz-indexnow-page-next">下一页</button>
        </div>
    </section>
    <?php endif; ?>
</div>
<script src="<?php echo Site::escAttr($scriptUrl); ?>"></script>
</body>
</html>
