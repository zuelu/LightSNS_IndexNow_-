<?php

use LightSNS\Foundation\DB;

function czzz_indexnow_db_table(): string
{
    return 'czzz_indexnow_submit_logs';
}

function czzz_indexnow_db_full_table(): string
{
    return DB::fullTable(czzz_indexnow_db_table());
}

function czzz_indexnow_db_install(): void
{
    $table = czzz_indexnow_db_full_table();
    DB::raw("CREATE TABLE IF NOT EXISTS {$table} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `url` VARCHAR(2048) NOT NULL,
        `url_hash` CHAR(64) NOT NULL,
        `endpoint` VARCHAR(255) NOT NULL,
        `submit_type` VARCHAR(32) NOT NULL DEFAULT 'auto',
        `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
        `http_code` INT NOT NULL DEFAULT 0,
        `attempts` INT NOT NULL DEFAULT 0,
        `error_message` TEXT NULL,
        `response_body` MEDIUMTEXT NULL,
        `dedupe_until` INT NOT NULL DEFAULT 0,
        `created_at` INT NOT NULL DEFAULT 0,
        `updated_at` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_url_hash_created` (`url_hash`, `created_at`),
        KEY `idx_status_created` (`status`, `created_at`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function czzz_indexnow_db_prune(int $keepDays): void
{
    $keepDays = max(1, min($keepDays, 365));
    $before = time() - $keepDays * 86400;
    DB::raw('DELETE FROM ' . czzz_indexnow_db_full_table() . ' WHERE created_at < ?', [$before], true);
}
