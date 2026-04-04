<?php
/**
 * Plugin Name: XWC Data Type Test Bootstrap
 * Description: Test-only plugin wrapper for the XWC data type package.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Support/TestItem.php';
require_once dirname(__DIR__, 2) . '/Support/fixtures.php';

xwc_test_install_custom_entity();
