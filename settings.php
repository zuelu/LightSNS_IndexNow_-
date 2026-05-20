<?php

if (!defined('LIGHTSNS_LOADED')) {
    die;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'indexnow_lib.php';

use LightSNS\Foundation\PageRouter;
use LightSNS\Foundation\Site;

czzz_indexnow_get_key();
czzz_indexnow_db_install();

$routePath = '/indexnow-auto-submit';
PageRouter::add('module_' . czzz_indexnow_module_id(), [
    'path' => $routePath,
    'file' => czzz_indexnow_module_dir() . '/page.php',
    'seo' => [
        'title' => 'IndexNow 自动提交',
        'description' => 'IndexNow Key、验证文件、URL 提交与历史记录管理。',
    ],
]);

$summary = czzz_indexnow_summary();
$verification = is_array($summary['verification'] ?? null) ? $summary['verification'] : [];
$diagnostics = is_array($summary['diagnostics'] ?? null) ? $summary['diagnostics'] : [];
$keyHtml = htmlspecialchars((string) ($summary['key'] ?? ''), ENT_QUOTES, 'UTF-8');
$keyUrl = htmlspecialchars((string) ($summary['key_location'] ?? ''), ENT_QUOTES, 'UTF-8');
$apiUrl = czzz_indexnow_module_url('api.php');
$apiUrlHtml = htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8');
$pageUrl = czzz_indexnow_module_url('page.php');
$pageUrlHtml = htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8');
$routeUrl = htmlspecialchars(Site::homeUrl($routePath), ENT_QUOTES, 'UTF-8');
$filePath = htmlspecialchars((string) ($verification['path'] ?? ''), ENT_QUOTES, 'UTF-8');
$statusText = !empty($verification['ok']) ? '本地验证文件内容正确' : '本地验证文件未就绪';
$publicText = !empty($verification['public_ok']) ? '公开访问 HTTP 200 且内容匹配' : '公开访问需在浏览器或服务器网络中复核';
$diagHomeUrl = htmlspecialchars((string) ($diagnostics['home_url'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagHost = htmlspecialchars((string) ($diagnostics['parsed_host'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagScheme = htmlspecialchars((string) ($diagnostics['parsed_scheme'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagEndpoint = htmlspecialchars((string) ($diagnostics['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagKeyLocation = htmlspecialchars((string) ($diagnostics['key_location'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagLegacyKeyLocation = htmlspecialchars((string) ($diagnostics['legacy_key_location'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagSampleUrl = htmlspecialchars((string) ($diagnostics['sample_url'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagSampleHost = htmlspecialchars((string) ($diagnostics['sample_url_host'] ?? ''), ENT_QUOTES, 'UTF-8');
$diagPayload = htmlspecialchars(json_encode($diagnostics['sample_payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8');
$latestFailed = is_array($diagnostics['latest_failed_log'] ?? null) ? $diagnostics['latest_failed_log'] : [];
$latestAny = is_array($diagnostics['latest_log'] ?? null) ? $diagnostics['latest_log'] : [];
$latestFailedUrl = htmlspecialchars((string) ($latestFailed['url'] ?? ''), ENT_QUOTES, 'UTF-8');
$latestFailedStatus = htmlspecialchars((string) ($latestFailed['status'] ?? ''), ENT_QUOTES, 'UTF-8');
$latestFailedHttp = (int) ($latestFailed['http_code'] ?? 0);
$latestFailedBody = htmlspecialchars((string) ($latestFailed['response_body'] ?? ''), ENT_QUOTES, 'UTF-8');
$latestAnyUrl = htmlspecialchars((string) ($latestAny['url'] ?? ''), ENT_QUOTES, 'UTF-8');
$latestAnyStatus = htmlspecialchars((string) ($latestAny['status'] ?? ''), ENT_QUOTES, 'UTF-8');
$latestAnyHttp = (int) ($latestAny['http_code'] ?? 0);
$diagPanel = '<div style="display:grid;gap:12px;line-height:1.75;">'
    . '<div><strong>当前 homeUrl：</strong><code>' . $diagHomeUrl . '</code></div>'
    . '<div><strong>解析出的协议与 Host：</strong><code>' . $diagScheme . '://' . $diagHost . '</code></div>'
    . '<div><strong>当前端点：</strong><code>' . $diagEndpoint . '</code></div>'
    . '<div><strong>当前 keyLocation：</strong><code>' . $diagKeyLocation . '</code></div>'
    . '<div><strong>兼容副本 keyLocation：</strong><code>' . $diagLegacyKeyLocation . '</code></div>'
    . '<div><strong>示例内容页 URL：</strong><code>' . $diagSampleUrl . '</code></div>'
    . '<div><strong>示例 URL Host：</strong><code>' . $diagSampleHost . '</code></div>'
    . '<div><strong>即将提交的示例 Payload：</strong><pre style="margin:8px 0 0;padding:12px;border-radius:12px;background:#0f172a;color:#e2e8f0;overflow:auto;white-space:pre-wrap;">' . $diagPayload . '</pre></div>'
    . '<div><strong>最近一条失败日志：</strong>' . ($latestFailedUrl !== '' ? ('<div style="margin-top:8px;">URL：<code>' . $latestFailedUrl . '</code><br>状态：<code>' . $latestFailedStatus . '</code> / HTTP：<code>' . $latestFailedHttp . '</code><br>响应体：<pre style="margin:8px 0 0;padding:12px;border-radius:12px;background:#fff7ed;color:#7c2d12;overflow:auto;white-space:pre-wrap;">' . ($latestFailedBody !== '' ? $latestFailedBody : '无响应体') . '</pre></div>') : '暂无失败日志') . '</div>'
    . '<div><strong>最近一条提交日志：</strong>' . ($latestAnyUrl !== '' ? ('<div style="margin-top:8px;">URL：<code>' . $latestAnyUrl . '</code><br>状态：<code>' . $latestAnyStatus . '</code> / HTTP：<code>' . $latestAnyHttp . '</code></div>') : '暂无提交日志') . '</div>'
    . '</div>';
$panel = '<div style="display:grid;gap:12px;line-height:1.7;">'
    . '<div><strong>当前 Key：</strong><code>' . $keyHtml . '</code></div>'
    . '<div><strong>验证 URL：</strong><a href="' . $keyUrl . '" target="_blank" rel="noopener noreferrer">' . $keyUrl . '</a></div>'
    . '<div><strong>验证文件：</strong>' . $filePath . '</div>'
    . '<div><strong>兼容副本：</strong><code>' . htmlspecialchars((string) ($verification['legacy_path'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code></div>'
    . '<div><strong>验证状态：</strong>' . htmlspecialchars($statusText . '；' . $publicText, ENT_QUOTES, 'UTF-8') . '</div>'
    . '<div><strong>接口入口：</strong><a href="' . $apiUrlHtml . '?action=summary" target="_blank" rel="noopener noreferrer">' . $apiUrlHtml . '</a></div>'
    . '<div><strong>控制台：</strong><a href="' . $pageUrlHtml . '" target="_blank" rel="noopener noreferrer">' . $pageUrlHtml . '</a> / <a href="' . $routeUrl . '" target="_blank" rel="noopener noreferrer">' . $routeUrl . '</a></div>'
    . '</div>';
$consoleFrame = static function (string $section, int $height, string $description) use ($pageUrl, $pageUrlHtml): string {
    $iframeUrl = htmlspecialchars($pageUrl . '?embed=1&section=' . rawurlencode($section), ENT_QUOTES, 'UTF-8');
    $sectionHtml = htmlspecialchars($section, ENT_QUOTES, 'UTF-8');
    return '<div style="display:grid;gap:12px;">'
        . '<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">'
        . '<div style="font-size:14px;line-height:1.7;color:#374151;">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<a href="' . $pageUrlHtml . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;background:#2563eb;color:#fff;text-decoration:none;">在新窗口打开完整控制台</a>'
        . '</div>'
        . '<iframe class="czzz-indexnow-settings-frame" data-indexnow-section="' . $sectionHtml . '" src="' . $iframeUrl . '" style="width:100%;min-height:' . $height . 'px;border:1px solid #dbe3f0;border-radius:16px;background:#fff;"></iframe>'
        . '</div>';
};
$consoleInfoPanel = $consoleFrame('info', 360, '只显示控制台里的基础信息区，便于快速查看当前 Key、验证文件、端点和公开访问状态。');
$consoleManualPanel = $consoleFrame('manual', 390, '只显示手动操作区，支持单条 URL 提交、维护验证文件、重新生成 Key 和补扫最近已发布内容。');
$consoleBatchPanel = $consoleFrame('batch', 560, '只显示批量提交区，可预览候选链接并逐条提交未提交或最近失败的内容页。');
$consoleLogPanel = $consoleFrame('logs', 760, '只显示提交日志区，支持分页、状态筛选、每页条数调整和行内重新提交。');
$layoutFix = '<style>'
    . '.adminx-module-settings-form .adminx-field.adminx-field-tab{display:block;margin-bottom:0;padding-bottom:0;border-bottom:none;}'
    . '.adminx-module-settings-form .adminx-field.adminx-field-tab>.adminx-field-left{display:none !important;}'
    . '.adminx-module-settings-form .adminx-field.adminx-field-tab>.adminx-field-right{width:100%;max-width:100%;flex:1 1 100%;}'
    . '.adminx-module-settings-form .adminx-field.adminx-field-tab .adminx-tab{width:100%;}'
    . '.adminx-module-settings-form .adminx-field.adminx-field-tab .adminx-tab-nav{margin-bottom:16px;}'
    . '</style>'
    . '<script>(function(){if(window.__czzzIndexnowSettingsTabRefreshBound__)return;window.__czzzIndexnowSettingsTabRefreshBound__=true;function refreshLogs(){var frame=document.querySelector("iframe.czzz-indexnow-settings-frame[data-indexnow-section=\"logs\"]");if(!frame)return;if(frame.contentWindow){frame.contentWindow.postMessage({type:"czzz-indexnow-refresh-logs"},window.location.origin);}}document.addEventListener("click",function(event){var node=event.target&&event.target.closest?event.target.closest("[role=tab],button,a,li,.adminx-tab-nav-item,.adminx-tab-item"):event.target;if(!node)return;var text=(node.textContent||"").replace(/\\s+/g,"").trim();if(text==="提交日志"){window.setTimeout(refreshLogs,180);}},true);})();</script>';

return [
    'id' => czzz_indexnow_module_id(),
    'title' => 'IndexNow 自动提交设置',
    'fields' => [
        ['type' => 'notice', 'style' => 'info', 'content' => $layoutFix],
        [
            'id' => czzz_indexnow_option_key('sub_tabs'),
            'type' => 'tab',
            'title' => '',
            'tabs' => [
                [
                    'title' => '基础配置',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'info', 'content' => '点击上方 TAB 可切换基础配置、诊断面板、控制台概览、手动操作、批量提交和提交日志。'],
                        ['type' => 'notice', 'style' => !empty($verification['ok']) ? 'success' : 'warning', 'content' => $panel],
                        ['id' => czzz_indexnow_option_key('enabled'), 'type' => 'switcher', 'title' => '启用自动提交', 'default' => true],
                        ['id' => czzz_indexnow_option_key('auto_submit_posts'), 'type' => 'switcher', 'title' => '监听帖子发布/更新事件', 'default' => true, 'desc' => '模块会在自身运行时注册 Hook 与 EventBus 监听；是否能覆盖所有发布链路取决于主程序是否加载模块运行时。'],
                        ['id' => czzz_indexnow_option_key('endpoint'), 'type' => 'select', 'title' => 'IndexNow 提交端点', 'default' => czzz_indexnow_default_endpoint(), 'options' => czzz_indexnow_endpoint_options()],
                        ['id' => czzz_indexnow_option_key('dedupe_seconds'), 'type' => 'text', 'title' => '自动提交去重窗口（秒）', 'default' => '3600', 'desc' => '同一 URL 在窗口内已有成功提交时，自动提交会记录为 deduped；手动重试不受限制。'],
                        ['id' => czzz_indexnow_option_key('retry_max'), 'type' => 'text', 'title' => '429 最大退避重试次数', 'default' => '2', 'desc' => '遇到 HTTP 429 时进行有限次退避重试，范围 0-5。'],
                        ['id' => czzz_indexnow_option_key('retry_base_seconds'), 'type' => 'text', 'title' => '429 退避基础秒数', 'default' => '2', 'desc' => '实际等待约为基础秒数乘以当前尝试序号。'],
                        ['id' => czzz_indexnow_option_key('history_keep_days'), 'type' => 'text', 'title' => '历史记录保留天数', 'default' => '30', 'desc' => '提交时会清理超过该天数的模块自有记录。'],
                    ],
                ],
                [
                    'title' => '诊断面板',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'info', 'content' => '这里直接显示插件当前解析出的真实提交参数，便于定位 422 / 403 等问题。'],
                        ['type' => 'notice', 'style' => 'warning', 'content' => $diagPanel],
                    ],
                ],
                [
                    'title' => '控制台概览',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'success', 'content' => $consoleInfoPanel],
                    ],
                ],
                [
                    'title' => '手动操作',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'success', 'content' => $consoleManualPanel],
                    ],
                ],
                [
                    'title' => '批量提交',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'success', 'content' => $consoleBatchPanel],
                    ],
                ],
                [
                    'title' => '提交日志',
                    'fields' => [
                        ['type' => 'notice', 'style' => 'success', 'content' => $consoleLogPanel],
                    ],
                ],
            ],
        ],
    ],
];
