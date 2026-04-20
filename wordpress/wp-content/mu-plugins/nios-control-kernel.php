<?php
/**
 * Plugin Name: NIOS Control Kernel
 * Description: Deterministic operational control kernel for NIOS.
 */

if (!defined('ABSPATH')) exit;

define('NIOS_KERNEL_DIR', __DIR__ . '/nios-control');
define('NIOS_KERNEL_URL', content_url('mu-plugins/nios-control'));

require_once NIOS_KERNEL_DIR . '/core/engine.php';
require_once NIOS_KERNEL_DIR . '/core/api.php';
require_once NIOS_KERNEL_DIR . '/core/actions.php';
require_once NIOS_KERNEL_DIR . '/core/dashboard-page.php';