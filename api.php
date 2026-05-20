<?php

ob_start();

require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'indexnow_lib.php';

use LightSNS\Foundation\Response;

$action = strtolower((string) ($_REQUEST['action'] ?? 'summary'));
$writeActions = ['regenerate_key', 'maintain_key_file', 'submit', 'retry', 'repair_recent'];

try {
    $readOnlyActions = ['summary', 'logs'];
    $adminReadActions = ['batch_candidates'];
    $isAdmin = czzz_indexnow_is_admin();
    $canViewReadonly = czzz_indexnow_can_view_readonly();

    if (!$isAdmin) {
        if (in_array($action, $readOnlyActions, true)) {
            if (!$canViewReadonly) {
                Response::json(['code' => 0, 'msg' => '请先登录后查看 IndexNow 只读信息']);
            }
        } else {
            Response::json(['code' => 0, 'msg' => '只有管理员可以操作 IndexNow 模块']);
        }
    }

    if (!in_array($action, $readOnlyActions, true)
        && !in_array($action, $adminReadActions, true)
        && !in_array($action, $writeActions, true)
    ) {
        Response::json(['code' => 0, 'msg' => '未知操作']);
    }

    if (in_array($action, $writeActions, true)) {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            Response::json(['code' => 0, 'msg' => '写操作只允许使用 POST 请求']);
        }
        if (!czzz_indexnow_same_origin_request()) {
            Response::json(['code' => 0, 'msg' => '请求来源校验失败，请在站内控制台重试']);
        }
    }

    if ($action === 'summary') {
        Response::json(['code' => 1, 'msg' => 'ok', 'data' => $isAdmin ? czzz_indexnow_summary() : czzz_indexnow_readonly_summary()]);
    }

    if ($action === 'regenerate_key') {
        $result = czzz_indexnow_regenerate_key();
        Response::json(['code' => 1, 'msg' => 'Key 已重新生成', 'data' => $result, 'summary' => czzz_indexnow_summary()]);
    }

    if ($action === 'maintain_key_file') {
        $result = czzz_indexnow_maintain_key_file(czzz_indexnow_get_key());
        Response::json(['code' => !empty($result['ok']) ? 1 : 0, 'msg' => (string) ($result['message'] ?? '已处理'), 'data' => $result]);
    }

    if ($action === 'submit') {
        $url = (string) ($_POST['url'] ?? '');
        $force = !empty($_POST['force']);
        $submitType = czzz_indexnow_normalize_manual_submit_type((string) ($_POST['submit_type'] ?? ''), $force);
        $result = czzz_indexnow_submit_url($url, $submitType, $force);
        Response::json(['code' => (int) $result['code'], 'msg' => (string) $result['msg'], 'data' => $result, 'summary' => czzz_indexnow_summary()]);
    }

    if ($action === 'retry') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = czzz_indexnow_log_by_id($id);
        if (empty($row['url'])) {
            Response::json(['code' => 0, 'msg' => '记录不存在或 URL 为空']);
        }
        $result = czzz_indexnow_submit_url((string) $row['url'], 'manual_retry', true);
        Response::json(['code' => (int) $result['code'], 'msg' => (string) $result['msg'], 'data' => $result, 'summary' => czzz_indexnow_summary()]);
    }

    if ($action === 'logs') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min($isAdmin ? 1000 : 200, (int) ($_GET['limit'] ?? 50)));
        $status = (string) ($_GET['status'] ?? 'all');
        $pageData = czzz_indexnow_logs_page($page, $limit, $status);
        Response::json(['code' => 1, 'msg' => 'ok', 'data' => czzz_indexnow_sanitize_logs_page($pageData, $isAdmin)]);
    }

    if ($action === 'batch_candidates') {
        $mode = (string) ($_REQUEST['mode'] ?? 'never_submitted');
        $result = czzz_indexnow_batch_candidates($mode);
        Response::json(['code' => 1, 'msg' => 'ok', 'data' => $result]);
    }

    if ($action === 'repair_recent') {
        $sourceLimit = max(1, min(100, (int) ($_POST['source_limit'] ?? 20)));
        $submitLimit = max(1, min(20, (int) ($_POST['submit_limit'] ?? 3)));
        $result = czzz_indexnow_catch_up_recent_posts($sourceLimit, $submitLimit);
        Response::json([
            'code' => 1,
            'msg' => '最近内容补扫完成',
            'data' => $result,
            'summary' => czzz_indexnow_summary(),
        ]);
    }

    Response::json(['code' => 0, 'msg' => '未知操作']);
} catch (Throwable $e) {
    Response::json(['code' => 0, 'msg' => czzz_indexnow_is_admin() ? $e->getMessage() : '请求处理失败']);
}
