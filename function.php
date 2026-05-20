<?php

if (!defined('LIGHTSNS_LOADED')) {
    die;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'indexnow_lib.php';

czzz_indexnow_get_key();
czzz_indexnow_db_install();
czzz_indexnow_register_runtime_hooks();
czzz_indexnow_maybe_run_request_catchup();
