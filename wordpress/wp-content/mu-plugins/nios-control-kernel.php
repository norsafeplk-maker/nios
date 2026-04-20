<?php
if (!defined('ABSPATH')) exit;

/**
 * NIOS Kernel
 * Only this file is auto-loaded by WordPress MU plugins.
 */

define('NIOS_KERNEL_FILE', __FILE__);
define('NIOS_KERNEL_DIR', __DIR__);
define('NIOS_CONTROL_DIR', __DIR__ . '/nios-control');
define('NIOS_CORE_DIR', NIOS_CONTROL_DIR . '/core');
define('NIOS_ASSETS_URL', plugin_dir_url(__FILE__) . 'nios-control/assets/');

require_once NIOS_CORE_DIR . '/states.php';
require_once NIOS_CORE_DIR . '/nios-auth.php';
require_once NIOS_CORE_DIR . '/nios-model.php';
require_once NIOS_CORE_DIR . '/state-alignment.php';
require_once NIOS_CORE_DIR . '/actions.php';
require_once NIOS_CORE_DIR . '/api.php';
require_once NIOS_CORE_DIR . '/dashboard-page.php';