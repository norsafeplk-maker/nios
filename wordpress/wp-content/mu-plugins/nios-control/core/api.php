<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================
 * REGISTER ROUTES
 * ============================================
 */
add_action('rest_api_init', function () {

    register_rest_route('nios/v1', '/order', [
        'methods'  => 'POST',
        'callback' => 'nios_api_ingest_orders',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('nios/v1', '/dashboard/(?P<state>[A-Za-z_-]+)', [
        'methods'  => 'GET',
        'callback' => 'nios_api_dashboard_state',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * ============================================
 * AUTH
 * ============================================
 */
function nios_api_require_key(WP_REST_Request $request) {
    $key = (string) $request->get_header('X-NIOS-KEY');
    return hash_equals('test123', $key);
}

/**
 * ============================================
 * 🔴 INGEST (THIS MUST FIRE)
 * ============================================
 */
function nios_api_ingest_orders(WP_REST_Request $request) {

    if (!nios_api_require_key($request)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 403);
    }

    $body = $request->get_json_params();

    if (!isset($body['orders']) || !is_array($body['orders'])) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Invalid payload'
        ], 400);
    }

    // 🔴 CRITICAL LINE
    $count = nios_ingest_orders($body['orders']);

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Orders saved',
        'count' => $count
    ], 200);
}

/**
 * ============================================
 * DASHBOARD
 * ============================================
 */
function nios_api_dashboard_state(WP_REST_Request $request) {

    if (!nios_api_require_key($request)) {
        return new WP_REST_Response(['status' => 'error'], 403);
    }

    $state = strtoupper($request['state']);

    $orders = nios_get_orders();

    $filtered = array_values(array_filter($orders, function ($o) use ($state) {
        return ($o['state'] ?? '') === $state;
    }));

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $filtered
    ], 200);
}