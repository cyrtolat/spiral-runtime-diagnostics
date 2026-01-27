<?php

declare(strict_types=1);

// Common test kit for namespace-level function mocks.
// Include this file from tests that need any of the overridden PHP functions.

require_once __DIR__ . '/is_file.php';
require_once __DIR__ . '/file_get_contents.php';
require_once __DIR__ . '/file_put_contents.php';
require_once __DIR__ . '/rename.php';
require_once __DIR__ . '/unlink.php';
require_once __DIR__ . '/json_decode.php';
require_once __DIR__ . '/json_encode.php';
require_once __DIR__ . '/memory_get_usage.php';
require_once __DIR__ . '/memory_get_peak_usage.php';
