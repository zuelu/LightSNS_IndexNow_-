<?php

use LightSNS\Foundation\DB;
 use LightSNS\Foundation\EventBus;
use LightSNS\Foundation\Site;
use LightSNS\Shared\Auth;
use LightSNS\Shared\Hook;
use LightSNS\Shared\Options;
use LightSNS\Shared\Power;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'database.php';

function czzz_indexnow_module_id(): string
{
    return 'czzz-pc-page-indexnow-auto-submit';
}

function czzz_indexnow_option_prefix(): string
{
    return 'czzz_indexnow_pc_page_';
}

function czzz_indexnow_option_key(string $name): string
{
    return czzz_indexnow_option_prefix() . $name;
}

function czzz_indexnow_module_dir(): string
{
    return __DIR__;
}

function czzz_indexnow_module_url(string $path = ''): string
{
    $base = Site::moduleUrl() . '/pc/page/' . czzz_indexnow_module_id();
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function czzz_indexnow_home_url(string $path = ''): string
{
    return Site::homeUrl($path);
}

function czzz_indexnow_is_admin(): bool
{
    $userId = (int) Auth::userId();
    return $userId > 0 && Power::isAdmin($userId);
}

function czzz_indexnow_can_view_readonly(): bool
{
    return (int) Auth::userId() > 0;
}

function czzz_indexnow_site_origin(): string
{
    $home = czzz_indexnow_home_url('/');
    $scheme = strtolower((string) parse_url($home, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($home, PHP_URL_HOST));
    if ($scheme === '' || $host === '') {
        return '';
    }
    $port = (int) parse_url($home, PHP_URL_PORT);
    $origin = $scheme . '://' . $host;
    if ($port > 0 && !in_array([$scheme, $port], [['http', 80], ['https', 443]], true)) {
        $origin .= ':' . $port;
    }
    return $origin;
}

function czzz_indexnow_same_origin_request(): bool
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $siteOrigin = czzz_indexnow_site_origin();
    if ($siteOrigin === '') {
        return false;
    }
    foreach ([$origin, $referer] as $value) {
        if ($value === '') {
            continue;
        }
        return str_starts_with(strtolower($value), strtolower($siteOrigin));
    }
    return false;
}

function czzz_indexnow_mask_key(string $key): string
{
    $length = strlen($key);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }
    return substr($key, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($key, -4);
}

function czzz_indexnow_default_endpoint(): string
{
    return 'https://api.indexnow.org/indexnow';
}

function czzz_indexnow_endpoint_options(): array
{
    return [
        'https://api.indexnow.org/indexnow' => 'IndexNow 官方端点',
        'https://www.bing.com/indexnow' => 'Bing IndexNow',
        'https://api.searchengine.cn/indexnow' => 'SearchEngine.cn IndexNow',
        'https://api.yandex.com/indexnow' => 'Yandex IndexNow',
    ];
}

function czzz_indexnow_bool_option(string $name, bool $default): bool
{
    $value = Options::module(czzz_indexnow_option_key($name), $default);
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return $default;
    }
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function czzz_indexnow_int_option(string $name, int $default, int $min, int $max): int
{
    $value = (int) Options::module(czzz_indexnow_option_key($name), $default);
    if ($value < $min) {
        return $default;
    }
    return min($value, $max);
}

function czzz_indexnow_endpoint(): string
{
    $endpoint = trim((string) Options::module(czzz_indexnow_option_key('endpoint'), czzz_indexnow_default_endpoint()));
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return czzz_indexnow_default_endpoint();
    }
    $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $endpoint : czzz_indexnow_default_endpoint();
}

function czzz_indexnow_config(): array
{
    return [
        'enabled' => czzz_indexnow_bool_option('enabled', true),
        'endpoint' => czzz_indexnow_endpoint(),
        'dedupe_seconds' => czzz_indexnow_int_option('dedupe_seconds', 3600, 60, 604800),
        'retry_max' => czzz_indexnow_int_option('retry_max', 2, 0, 5),
        'retry_base_seconds' => czzz_indexnow_int_option('retry_base_seconds', 2, 1, 60),
        'history_keep_days' => czzz_indexnow_int_option('history_keep_days', 30, 1, 365),
        'auto_submit_posts' => czzz_indexnow_bool_option('auto_submit_posts', true),
    ];
}

function czzz_indexnow_random_key(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable) {
        return hash('sha256', uniqid('czzz_indexnow_', true) . mt_rand());
    }
}

function czzz_indexnow_get_key(): string
{
    $key = trim((string) Options::module(czzz_indexnow_option_key('key'), ''));
    if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key)) {
        $key = czzz_indexnow_random_key();
        Options::moduleSave([czzz_indexnow_option_key('key') => $key]);
    }
    czzz_indexnow_maintain_key_file($key);
    return $key;
}

function czzz_indexnow_well_known_dir(): string
{
    return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . '.well-known';
}

function czzz_indexnow_root_dir(): string
{
    return dirname(__DIR__, 4);
}

function czzz_indexnow_root_key_file_path(?string $key = null): string
{
    $key = $key ?: czzz_indexnow_get_key();
    return czzz_indexnow_root_dir() . DIRECTORY_SEPARATOR . $key . '.txt';
}

function czzz_indexnow_legacy_key_file_path(?string $key = null): string
{
    $key = $key ?: czzz_indexnow_get_key();
    return czzz_indexnow_well_known_dir() . DIRECTORY_SEPARATOR . $key . '.txt';
}

function czzz_indexnow_key_file_path(?string $key = null): string
{
    return czzz_indexnow_root_key_file_path($key);
}

function czzz_indexnow_key_file_url(?string $key = null): string
{
    $key = $key ?: czzz_indexnow_get_key();
    return czzz_indexnow_home_url('/' . rawurlencode($key) . '.txt');
}

function czzz_indexnow_legacy_key_file_url(?string $key = null): string
{
    $key = $key ?: czzz_indexnow_get_key();
    return czzz_indexnow_home_url('/.well-known/' . rawurlencode($key) . '.txt');
}

function czzz_indexnow_maintain_key_file(?string $key = null): array
{
    $key = $key ?: trim((string) Options::module(czzz_indexnow_option_key('key'), ''));
    if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key)) {
        return ['ok' => false, 'path' => '', 'url' => '', 'message' => 'Key 格式无效'];
    }
    $rootFile = czzz_indexnow_root_key_file_path($key);
    $legacyDir = czzz_indexnow_well_known_dir();
    $legacyFile = czzz_indexnow_legacy_key_file_path($key);

    $rootAlreadyValid = is_file($rootFile) && trim((string) @file_get_contents($rootFile)) === $key;
    $rootOk = $rootAlreadyValid ? true : (@file_put_contents($rootFile, $key, LOCK_EX) !== false);
    if ($rootOk) {
        @chmod($rootFile, 0644);
    }

    $legacyDirOk = true;
    if (!is_dir($legacyDir) && !mkdir($legacyDir, 0755, true) && !is_dir($legacyDir)) {
        $legacyDirOk = false;
    }
    $legacyOk = false;
    if ($legacyDirOk) {
        $legacyAlreadyValid = is_file($legacyFile) && trim((string) @file_get_contents($legacyFile)) === $key;
        $legacyOk = $legacyAlreadyValid ? true : (@file_put_contents($legacyFile, $key, LOCK_EX) !== false);
        if ($legacyOk) {
            @chmod($legacyFile, 0644);
        }
    }

    $rootValid = $rootOk && is_file($rootFile) && trim((string) @file_get_contents($rootFile)) === $key;
    $legacyValid = $legacyOk && is_file($legacyFile) && trim((string) @file_get_contents($legacyFile)) === $key;
    return [
        'ok' => $rootValid,
        'path' => $rootFile,
        'url' => czzz_indexnow_key_file_url($key),
        'legacy_path' => $legacyFile,
        'legacy_url' => czzz_indexnow_legacy_key_file_url($key),
        'legacy_ok' => $legacyValid,
        'message' => $rootValid ? '验证文件已维护' : '根目录验证文件写入失败',
    ];
}

function czzz_indexnow_regenerate_key(): array
{
    $oldKey = trim((string) Options::module(czzz_indexnow_option_key('key'), ''));
    $newKey = czzz_indexnow_random_key();
    Options::moduleSave([czzz_indexnow_option_key('key') => $newKey]);
    $state = czzz_indexnow_maintain_key_file($newKey);
    if ($oldKey !== '' && $oldKey !== $newKey && preg_match('/^[A-Za-z0-9_-]{8,128}$/', $oldKey)) {
        $oldFiles = [
            czzz_indexnow_root_key_file_path($oldKey),
            czzz_indexnow_legacy_key_file_path($oldKey),
        ];
        foreach ($oldFiles as $oldFile) {
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    }
    return ['key' => $newKey, 'file' => $state];
}

function czzz_indexnow_verification_status(): array
{
    $key = czzz_indexnow_get_key();
    $state = czzz_indexnow_maintain_key_file($key);
    $httpCode = 0;
    $bodyOk = false;
    if (!empty($state['url']) && function_exists('curl_init')) {
        $ch = curl_init($state['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $bodyOk = is_string($body) && trim($body) === $key;
    } elseif (!empty($state['path']) && is_file($state['path'])) {
        $bodyOk = trim((string) file_get_contents($state['path'])) === $key;
    }
    return $state + [
        'key' => $key,
        'http_code' => $httpCode,
        'body_ok' => $bodyOk,
        'public_ok' => $httpCode === 200 && $bodyOk,
    ];
}

function czzz_indexnow_normalize_url(string $url): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    } elseif (str_starts_with($url, '/')) {
        $url = czzz_indexnow_home_url($url);
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    $host = strtolower((string) $parts['host']);
    $path = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '/')) ?: '/';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return $scheme . '://' . $host . $path . $query;
}

function czzz_indexnow_is_content_url(string $url): bool
{
    $url = czzz_indexnow_normalize_url($url);
    if ($url === '') {
        return false;
    }
    $homeHost = strtolower((string) parse_url(czzz_indexnow_home_url('/'), PHP_URL_HOST));
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($homeHost !== '' && $host !== $homeHost) {
        return false;
    }
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    if (preg_match('/\.(?:jpg|jpeg|png|gif|webp|svg|ico|css|js|mjs|map|json|xml|txt|woff|woff2|ttf|eot|otf|mp3|mp4|webm|avi|mov|zip|rar|7z|gz|pdf)(?:$|\?)/i', $path)) {
        return false;
    }
    if (str_contains($path, '/module/') || str_contains($path, '/public/') || str_contains($path, '/assets/') || str_contains($path, '/upload/')) {
        return false;
    }
    return true;
}

function czzz_indexnow_post_url(int $postId): string
{
    if ($postId <= 0) {
        return '';
    }
    if (class_exists('\\LightSNS\\Post\\PostService') && method_exists('\\LightSNS\\Post\\PostService', 'url')) {
        $url = (string) \LightSNS\Post\PostService::url($postId, true);
        if ($url !== '') {
            return czzz_indexnow_normalize_url($url);
        }
    }
    return czzz_indexnow_normalize_url(czzz_indexnow_home_url('/post/' . $postId));
}

function czzz_indexnow_recent_success_exists(string $url, int $windowSeconds): bool
{
    $hash = hash('sha256', $url);
    $since = time() - max(60, $windowSeconds);
    $row = DB::query([
        'table_name' => czzz_indexnow_db_table(),
        'query_type' => 'get_row',
        'select_arr' => 'id',
        'where_arr' => [
            'url_hash' => $hash,
            'created_at' => ['operator' => '>=', 'value' => $since],
            'status' => ['operator' => 'IN', 'value' => ['success', 'accepted']],
        ],
        'order_arr' => ['id' => 'DESC'],
        'limit_arr' => ['page' => 1, 'number' => 1],
    ]);
    return !empty($row);
}

function czzz_indexnow_record(array $data): int
{
    czzz_indexnow_db_install();
    $now = time();
    return (int) DB::insert(czzz_indexnow_db_table(), [
        'url' => (string) ($data['url'] ?? ''),
        'url_hash' => hash('sha256', (string) ($data['url'] ?? '')),
        'endpoint' => (string) ($data['endpoint'] ?? czzz_indexnow_endpoint()),
        'submit_type' => (string) ($data['submit_type'] ?? 'auto'),
        'status' => (string) ($data['status'] ?? 'pending'),
        'http_code' => (int) ($data['http_code'] ?? 0),
        'attempts' => (int) ($data['attempts'] ?? 0),
        'error_message' => mb_substr((string) ($data['error_message'] ?? ''), 0, 2000),
        'response_body' => mb_substr((string) ($data['response_body'] ?? ''), 0, 8000),
        'dedupe_until' => (int) ($data['dedupe_until'] ?? 0),
        'created_at' => (int) ($data['created_at'] ?? $now),
        'updated_at' => (int) ($data['updated_at'] ?? $now),
    ]);
}

function czzz_indexnow_http_post(string $endpoint, array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!function_exists('curl_init')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($endpoint, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
        return [
            'http_code' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => is_string($body) ? '' : 'HTTP 请求失败',
        ];
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'http_code' => $status,
        'body' => is_string($body) ? $body : '',
        'error' => $error,
    ];
}

function czzz_indexnow_submit_url(string $url, string $submitType = 'auto', bool $force = false): array
{
    czzz_indexnow_db_install();
    czzz_indexnow_db_prune(czzz_indexnow_int_option('history_keep_days', 30, 1, 365));
    $config = czzz_indexnow_config();
    $url = czzz_indexnow_normalize_url($url);
    $endpoint = $config['endpoint'];
    if ($url === '' || !czzz_indexnow_is_content_url($url)) {
        $id = czzz_indexnow_record([
            'url' => $url,
            'endpoint' => $endpoint,
            'submit_type' => $submitType,
            'status' => 'filtered',
            'error_message' => 'URL 非内容页面或格式无效，已过滤',
        ]);
        return ['code' => 0, 'msg' => 'URL 已过滤', 'id' => $id, 'status' => 'filtered', 'url' => $url];
    }
    if (!$force && czzz_indexnow_recent_success_exists($url, (int) $config['dedupe_seconds'])) {
        $id = czzz_indexnow_record([
            'url' => $url,
            'endpoint' => $endpoint,
            'submit_type' => $submitType,
            'status' => 'deduped',
            'dedupe_until' => time() + (int) $config['dedupe_seconds'],
            'error_message' => '去重窗口内已有成功提交，自动提交已抑制',
        ]);
        return ['code' => 1, 'msg' => '已按去重规则跳过', 'id' => $id, 'status' => 'deduped', 'url' => $url];
    }
    if (empty($config['enabled']) && $submitType === 'auto') {
        $id = czzz_indexnow_record([
            'url' => $url,
            'endpoint' => $endpoint,
            'submit_type' => $submitType,
            'status' => 'disabled',
            'error_message' => '自动提交开关关闭',
        ]);
        return ['code' => 0, 'msg' => '自动提交已关闭', 'id' => $id, 'status' => 'disabled', 'url' => $url];
    }

    $key = czzz_indexnow_get_key();
    $payload = [
        'host' => (string) parse_url(czzz_indexnow_home_url('/'), PHP_URL_HOST),
        'key' => $key,
        'keyLocation' => czzz_indexnow_key_file_url($key),
        'urlList' => [$url],
    ];
    $maxAttempts = 1 + (int) $config['retry_max'];
    $attempt = 0;
    $last = ['http_code' => 0, 'body' => '', 'error' => ''];
    while ($attempt < $maxAttempts) {
        $attempt++;
        $last = czzz_indexnow_http_post($endpoint, $payload);
        if ((int) $last['http_code'] !== 429) {
            break;
        }
        if ($attempt < $maxAttempts) {
            usleep((int) ($config['retry_base_seconds'] * 1000000 * $attempt));
        }
    }

    $httpCode = (int) ($last['http_code'] ?? 0);
    $status = in_array($httpCode, [200, 202], true) ? ($httpCode === 202 ? 'accepted' : 'success') : 'failed';
    $error = trim((string) ($last['error'] ?? ''));
    if ($status === 'failed' && $error === '') {
        $error = 'IndexNow 返回 HTTP ' . $httpCode;
    }
    $id = czzz_indexnow_record([
        'url' => $url,
        'endpoint' => $endpoint,
        'submit_type' => $submitType,
        'status' => $status,
        'http_code' => $httpCode,
        'attempts' => $attempt,
        'error_message' => $error,
        'response_body' => (string) ($last['body'] ?? ''),
        'dedupe_until' => in_array($status, ['success', 'accepted'], true) ? time() + (int) $config['dedupe_seconds'] : 0,
    ]);
    return [
        'code' => in_array($status, ['success', 'accepted'], true) ? 1 : 0,
        'msg' => $status === 'failed' ? $error : '提交完成',
        'id' => $id,
        'status' => $status,
        'url' => $url,
        'http_code' => $httpCode,
        'attempts' => $attempt,
        'endpoint' => $endpoint,
    ];
}

function czzz_indexnow_log_statuses(): array
{
    return ['all', 'success', 'accepted', 'deduped', 'filtered', 'disabled', 'failed', 'pending'];
}

function czzz_indexnow_log_status_label(string $status): string
{
    $map = [
        'all' => '全部',
        'success' => '成功',
        'accepted' => '已接收',
        'deduped' => '去重跳过',
        'filtered' => '已过滤',
        'disabled' => '已禁用',
        'failed' => '失败',
        'pending' => '待处理',
    ];
    return $map[$status] ?? $status;
}

function czzz_indexnow_normalize_log_status(string $status): string
{
    $status = trim(strtolower($status));
    return in_array($status, czzz_indexnow_log_statuses(), true) ? $status : 'all';
}

function czzz_indexnow_log_count(string $status = 'all'): int
{
    czzz_indexnow_db_install();
    $where = [];
    $status = czzz_indexnow_normalize_log_status($status);
    if ($status !== 'all') {
        $where['status'] = $status;
    }
    $total = DB::query([
        'table_name' => czzz_indexnow_db_table(),
        'query_type' => 'get_var',
        'select_arr' => 'COUNT(*)',
        'where_arr' => $where,
    ]);
    return (int) $total;
}

function czzz_indexnow_logs_page(int $page = 1, int $limit = 50, string $status = 'all'): array
{
    czzz_indexnow_db_install();
    $page = max(1, $page);
    $limit = max(1, min($limit, 1000));
    $status = czzz_indexnow_normalize_log_status($status);
    $where = [];
    if ($status !== 'all') {
        $where['status'] = $status;
    }
    $total = czzz_indexnow_log_count($status);
    $pages = max(1, (int) ceil($total / $limit));
    $page = min($page, $pages);
    $items = DB::query([
        'table_name' => czzz_indexnow_db_table(),
        'query_type' => 'get_results',
        'select_arr' => '*',
        'where_arr' => $where,
        'order_arr' => ['id' => 'DESC'],
        'limit_arr' => ['page' => $page, 'number' => $limit],
    ]);
    return [
        'items' => is_array($items) ? $items : [],
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $pages,
        'status' => $status,
    ];
}

function czzz_indexnow_logs(int $limit = 50): array
{
    return czzz_indexnow_logs_page(1, $limit, 'all')['items'];
}

function czzz_indexnow_log_by_id(int $id): array
{
    if ($id <= 0) {
        return [];
    }
    czzz_indexnow_db_install();
    return DB::query([
        'table_name' => czzz_indexnow_db_table(),
        'query_type' => 'get_row',
        'select_arr' => '*',
        'where_arr' => ['id' => $id],
        'limit_arr' => ['page' => 1, 'number' => 1],
    ]);
}

function czzz_indexnow_sanitize_log_row(array $row, bool $adminView = false): array
{
    $clean = [
        'id' => (int) ($row['id'] ?? 0),
        'url' => (string) ($row['url'] ?? ''),
        'endpoint' => (string) ($row['endpoint'] ?? ''),
        'submit_type' => (string) ($row['submit_type'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'http_code' => (int) ($row['http_code'] ?? 0),
        'attempts' => (int) ($row['attempts'] ?? 0),
        'error_message' => (string) ($row['error_message'] ?? ''),
        'created_at' => (int) ($row['created_at'] ?? 0),
        'updated_at' => (int) ($row['updated_at'] ?? 0),
    ];
    if ($adminView) {
        $clean['response_body'] = (string) ($row['response_body'] ?? '');
        $clean['dedupe_until'] = (int) ($row['dedupe_until'] ?? 0);
    }
    return $clean;
}

function czzz_indexnow_sanitize_logs_page(array $pageData, bool $adminView = false): array
{
    $items = [];
    foreach ((array) ($pageData['items'] ?? []) as $row) {
        if (is_array($row)) {
            $items[] = czzz_indexnow_sanitize_log_row($row, $adminView);
        }
    }
    $pageData['items'] = $items;
    return $pageData;
}

function czzz_indexnow_latest_log(?string $status = null): array
{
    czzz_indexnow_db_install();
    $where = [];
    if ($status !== null && $status !== '') {
        $where['status'] = $status;
    }
    return DB::query([
        'table_name' => czzz_indexnow_db_table(),
        'query_type' => 'get_row',
        'select_arr' => '*',
        'where_arr' => $where,
        'order_arr' => ['id' => 'DESC'],
        'limit_arr' => ['page' => 1, 'number' => 1],
    ]);
}

function czzz_indexnow_diagnostics(): array
{
    $homeUrl = czzz_indexnow_home_url('/');
    $key = czzz_indexnow_get_key();
    $host = (string) parse_url($homeUrl, PHP_URL_HOST);
    $scheme = (string) parse_url($homeUrl, PHP_URL_SCHEME);
    $sampleUrl = czzz_indexnow_home_url('/indexnow-auto-submit');
    $payload = [
        'host' => $host,
        'key' => $key,
        'keyLocation' => czzz_indexnow_key_file_url($key),
        'urlList' => [$sampleUrl],
    ];
    $latestFailed = czzz_indexnow_latest_log('failed');
    $latestAny = czzz_indexnow_latest_log();
    return [
        'home_url' => $homeUrl,
        'parsed_host' => $host,
        'parsed_scheme' => $scheme,
        'endpoint' => czzz_indexnow_endpoint(),
        'key_location' => $payload['keyLocation'],
        'legacy_key_location' => czzz_indexnow_legacy_key_file_url($key),
        'sample_url' => $sampleUrl,
        'sample_url_host' => (string) parse_url($sampleUrl, PHP_URL_HOST),
        'sample_payload' => $payload,
        'latest_failed_log' => $latestFailed,
        'latest_log' => $latestAny,
    ];
}

function czzz_indexnow_fetch_ids_from_query(string $sql): array
{
    $result = DB::raw($sql);
    if (!($result instanceof mysqli_result)) {
        return [];
    }
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int) ($row['id'] ?? $row['ID'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $result->free();
    return $ids;
}

function czzz_indexnow_static_page_urls(): array
{
    $urls = [
        czzz_indexnow_home_url('/'),
        czzz_indexnow_home_url('/video-feed'),
    ];
    return array_values(array_filter(array_map('czzz_indexnow_normalize_url', $urls)));
}

function czzz_indexnow_collect_bbs_urls(): array
{
    $urls = [];
    if (class_exists('\\LightSNS\\Contexts\\Bbs\\Read\\BbsReadModel')) {
        $rows = \LightSNS\Contexts\Bbs\Read\BbsReadModel::all();
        foreach ($rows as $row) {
            $bbsId = (int) ($row['id'] ?? $row['ID'] ?? 0);
            if ($bbsId > 0) {
                $urls[] = czzz_indexnow_home_url(\LightSNS\BBS\BBSRepository::url($bbsId));
            }
        }
    }
    return array_values(array_filter(array_map('czzz_indexnow_normalize_url', $urls)));
}

function czzz_indexnow_collect_tag_urls(int $limit = 5000): array
{
    $limit = max(1, min($limit, 50000));
    $table = DB::fullTable('tags');
    $result = DB::raw("SELECT id FROM {$table} ORDER BY id DESC LIMIT {$limit}");
    if (!($result instanceof mysqli_result)) {
        return [];
    }
    $urls = [];
    while ($row = $result->fetch_assoc()) {
        $tagId = (int) ($row['id'] ?? 0);
        if ($tagId > 0) {
            $urls[] = czzz_indexnow_home_url('/tag/' . $tagId);
        }
    }
    $result->free();
    return array_values(array_filter(array_map('czzz_indexnow_normalize_url', $urls)));
}

function czzz_indexnow_collect_post_urls(int $limit = 50000): array
{
    $limit = max(1, min($limit, 50000));
    $table = DB::fullTable('posts');
    $ids = czzz_indexnow_fetch_ids_from_query("SELECT ID FROM {$table} WHERE post_status = 'publish' ORDER BY ID DESC LIMIT {$limit}");
    $urls = [];
    foreach ($ids as $postId) {
        $url = czzz_indexnow_post_url((int) $postId);
        if ($url !== '') {
            $urls[] = $url;
        }
    }
    return array_values(array_filter($urls));
}

function czzz_indexnow_unique_urls(array $urls): array
{
    $seen = [];
    $list = [];
    foreach ($urls as $url) {
        $normalized = czzz_indexnow_normalize_url((string) $url);
        if ($normalized === '' || !czzz_indexnow_is_content_url($normalized) || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $list[] = $normalized;
    }
    return $list;
}

function czzz_indexnow_collect_site_links(): array
{
    return czzz_indexnow_unique_urls(array_merge(
        czzz_indexnow_collect_post_urls(),
        czzz_indexnow_collect_bbs_urls(),
        czzz_indexnow_collect_tag_urls(),
        czzz_indexnow_static_page_urls()
    ));
}

function czzz_indexnow_manual_submit_types(): array
{
    return ['manual', 'manual_retry', 'batch', 'batch_retry'];
}

function czzz_indexnow_normalize_manual_submit_type(string $submitType, bool $force = false): string
{
    $submitType = trim(strtolower($submitType));
    if (!in_array($submitType, czzz_indexnow_manual_submit_types(), true)) {
        return $force ? 'manual_retry' : 'manual';
    }
    if (!$force && in_array($submitType, ['manual_retry', 'batch_retry'], true)) {
        return $submitType === 'batch_retry' ? 'batch' : 'manual';
    }
    return $submitType;
}

function czzz_indexnow_log_state_for_urls(array $urls): array
{
    czzz_indexnow_db_install();
    $urls = czzz_indexnow_unique_urls($urls);
    if (!$urls) {
        return [];
    }
    $hashToUrl = [];
    foreach ($urls as $url) {
        $hashToUrl[hash('sha256', $url)] = $url;
    }
    $states = [];
    $chunks = array_chunk(array_keys($hashToUrl), 300);
    foreach ($chunks as $chunk) {
        if (!$chunk) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $result = DB::raw(
            "SELECT id, url_hash, status, http_code, created_at FROM " . czzz_indexnow_db_full_table() .
            " WHERE url_hash IN ({$placeholders}) ORDER BY id DESC",
            $chunk
        );
        if (!($result instanceof mysqli_result)) {
            continue;
        }
        while ($row = $result->fetch_assoc()) {
            $hash = (string) ($row['url_hash'] ?? '');
            if ($hash === '' || empty($hashToUrl[$hash])) {
                continue;
            }
            if (!isset($states[$hash])) {
                $states[$hash] = [
                    'url' => $hashToUrl[$hash],
                    'exists' => true,
                    'has_success' => false,
                    'latest_status' => (string) ($row['status'] ?? ''),
                    'latest_http_code' => (int) ($row['http_code'] ?? 0),
                    'latest_created_at' => (int) ($row['created_at'] ?? 0),
                ];
            }
            if (in_array((string) ($row['status'] ?? ''), ['success', 'accepted'], true)) {
                $states[$hash]['has_success'] = true;
            }
        }
        $result->free();
    }
    return $states;
}

function czzz_indexnow_batch_modes(): array
{
    return ['never_submitted', 'unsubmitted_or_failed'];
}

function czzz_indexnow_normalize_batch_mode(string $mode): string
{
    $mode = trim(strtolower($mode));
    return in_array($mode, czzz_indexnow_batch_modes(), true) ? $mode : 'never_submitted';
}

function czzz_indexnow_batch_mode_label(string $mode): string
{
    $map = [
        'never_submitted' => '所有未提交过的链接',
        'unsubmitted_or_failed' => '所有未提交过或最近失败的链接',
    ];
    return $map[$mode] ?? $mode;
}

function czzz_indexnow_batch_candidates(string $mode = 'never_submitted'): array
{
    $mode = czzz_indexnow_normalize_batch_mode($mode);
    $links = czzz_indexnow_collect_site_links();
    $states = czzz_indexnow_log_state_for_urls($links);
    $urls = [];
    foreach ($links as $url) {
        $state = $states[hash('sha256', $url)] ?? [
            'exists' => false,
            'has_success' => false,
            'latest_status' => '',
        ];
        if ($mode === 'never_submitted') {
            if (empty($state['exists'])) {
                $urls[] = $url;
            }
            continue;
        }
        if (empty($state['has_success']) || in_array((string) ($state['latest_status'] ?? ''), ['failed', 'filtered', 'disabled'], true)) {
            $urls[] = $url;
        }
    }
    return [
        'mode' => $mode,
        'mode_label' => czzz_indexnow_batch_mode_label($mode),
        'total' => count($urls),
        'urls' => $urls,
    ];
}

function czzz_indexnow_recent_published_urls(int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    return czzz_indexnow_collect_post_urls($limit);
}

function czzz_indexnow_catch_up_recent_posts(int $sourceLimit = 20, int $submitLimit = 3): array
{
    $config = czzz_indexnow_config();
    if (empty($config['enabled']) || empty($config['auto_submit_posts'])) {
        return ['checked' => 0, 'submitted' => 0, 'results' => []];
    }
    $urls = czzz_indexnow_recent_published_urls($sourceLimit);
    $states = czzz_indexnow_log_state_for_urls($urls);
    $results = [];
    foreach ($urls as $url) {
        $state = $states[hash('sha256', $url)] ?? ['exists' => false, 'has_success' => false, 'latest_status' => ''];
        if (!empty($state['has_success'])) {
            continue;
        }
        if (!empty($state['exists']) && !in_array((string) ($state['latest_status'] ?? ''), ['failed', 'filtered', 'disabled'], true)) {
            continue;
        }
        $results[] = czzz_indexnow_submit_url($url, 'auto_catchup', false);
        if (count($results) >= $submitLimit) {
            break;
        }
    }
    return [
        'checked' => count($urls),
        'submitted' => count($results),
        'results' => $results,
    ];
}

function czzz_indexnow_maybe_run_request_catchup(): void
{
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;
    $config = czzz_indexnow_config();
    if (empty($config['enabled']) || empty($config['auto_submit_posts'])) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }
    $key = czzz_indexnow_option_key('last_request_catchup_at');
    $now = time();
    $last = (int) Options::module($key, 0);
    if ($now - $last < 120) {
        return;
    }
    Options::moduleSave([$key => $now]);
    czzz_indexnow_catch_up_recent_posts(100, 20);
}

function czzz_indexnow_summary(): array
{
    $verification = czzz_indexnow_verification_status();
    return [
        'module_id' => czzz_indexnow_module_id(),
        'key' => czzz_indexnow_get_key(),
        'endpoint' => czzz_indexnow_endpoint(),
        'key_location' => czzz_indexnow_key_file_url(czzz_indexnow_get_key()),
        'verification' => $verification,
        'config' => czzz_indexnow_config(),
        'api_url' => czzz_indexnow_module_url('api.php'),
        'page_url' => czzz_indexnow_module_url('page.php'),
        'route_url' => czzz_indexnow_home_url('/indexnow-auto-submit'),
        'logs' => czzz_indexnow_logs(50),
        'batch_modes' => czzz_indexnow_batch_modes(),
        'diagnostics' => czzz_indexnow_diagnostics(),
    ];
}

function czzz_indexnow_readonly_summary(): array
{
    $verification = czzz_indexnow_verification_status();
    return [
        'module_id' => czzz_indexnow_module_id(),
        'key_masked' => czzz_indexnow_mask_key(czzz_indexnow_get_key()),
        'key_configured' => czzz_indexnow_get_key() !== '',
        'endpoint' => czzz_indexnow_endpoint(),
        'verification' => [
            'ok' => !empty($verification['ok']),
            'http_code' => (int) ($verification['http_code'] ?? 0),
            'body_ok' => !empty($verification['body_ok']),
            'public_ok' => !empty($verification['public_ok']),
        ],
        'page_url' => czzz_indexnow_module_url('page.php'),
        'route_url' => czzz_indexnow_home_url('/indexnow-auto-submit'),
    ];
}

function czzz_indexnow_extract_post_id($payload): int
{
    if (is_numeric($payload)) {
        return (int) $payload;
    }
    if (!is_array($payload)) {
        return 0;
    }
    return (int) ($payload['post_id'] ?? $payload['ID'] ?? $payload['id'] ?? 0);
}

function czzz_indexnow_payload_is_publishable(array $payload): bool
{
    $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
    if ($after) {
        $afterStatus = (string) ($after['status'] ?? $after['post_status'] ?? '');
        if ($afterStatus !== '') {
            return $afterStatus === 'publish';
        }
    }
    if (isset($payload['after_status'])) {
        return (string) $payload['after_status'] === 'publish';
    }
    if (isset($payload['status'])) {
        return (string) $payload['status'] === 'publish';
    }
    if (isset($payload['post_status'])) {
        return (string) $payload['post_status'] === 'publish';
    }
    return true;
}

function czzz_indexnow_handle_post_event($payload = null): void
{
    $config = czzz_indexnow_config();
    if (empty($config['enabled']) || empty($config['auto_submit_posts'])) {
        return;
    }
    if (is_array($payload) && !czzz_indexnow_payload_is_publishable($payload)) {
        return;
    }
    $postId = czzz_indexnow_extract_post_id($payload);
    if ($postId <= 0) {
        return;
    }
    $url = czzz_indexnow_post_url($postId);
    if ($url === '') {
        return;
    }
    czzz_indexnow_submit_url($url, 'auto', false);
}

function czzz_indexnow_register_runtime_hooks(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;
    if (class_exists('\\LightSNS\\Shared\\Hook')) {
        Hook::on('post.published', 'czzz_indexnow_handle_post_event', 20);
        Hook::on('post.updated', 'czzz_indexnow_handle_post_event', 20);
        Hook::on('post.status_changed', 'czzz_indexnow_handle_post_event', 20);
    }
    if (class_exists('\\LightSNS\\Foundation\\EventBus')) {
        EventBus::on('post.published', 'czzz_indexnow_handle_post_event', 20);
        EventBus::on('post.updated', 'czzz_indexnow_handle_post_event', 20);
        EventBus::on('post.status_changed', 'czzz_indexnow_handle_post_event', 20);
        EventBus::onAsync('post.published', 'czzz_indexnow_handle_post_event');
        EventBus::onAsync('post.updated', 'czzz_indexnow_handle_post_event');
        EventBus::onAsync('post.status_changed', 'czzz_indexnow_handle_post_event');
    }
}

czzz_indexnow_register_runtime_hooks();
