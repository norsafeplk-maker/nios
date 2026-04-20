<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal-only API key.
 * Replace later if needed.
 */
if (!function_exists('nios_api_key')) {
    function nios_api_key() {
        return 'test123';
    }
}

if (!function_exists('nios_api_require_key')) {
    function nios_api_require_key(WP_REST_Request $request) {
        $received = (string) $request->get_header('X-NIOS-KEY');
        return hash_equals(nios_api_key(), $received);
    }
}