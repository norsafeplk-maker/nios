<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('nios/v1', '/order', [
        'methods'  => 'POST',
        'callback' => 'nios_api_ingest_orders',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('nios/v1', '/dashboard', [
        'methods'  => 'GET',
        'callback' => 'nios_api_dashboard_all',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('nios/v1', '/dashboard/(?P<state>[A-Za-z_-]+)', [
        'methods'  => 'GET',
        'callback' => 'nios_api_dashboard_state',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('nios/v1', '/debug-orders', [
        'methods'  => 'GET',
        'callback' => 'nios_api_debug_orders',
        'permission_callback' => '__return_true',
    ]);
});

function nios_api_require_key(WP_REST_Request $request) {
    $key = (string) $request->get_header('X-NIOS-KEY');
    return hash_equals(nios_api_key(), $key);
}

function nios_api_ingest_orders(WP_REST_Request $request) {
    if (!nios_api_require_key($request)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }

    $body = $request->get_json_params();
    $orders = isset($body['orders']) && is_array($body['orders']) ? $body['orders'] : [];

    nios_ingest_orders($orders);

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Orders ingested',
        'count' => count($orders),
    ], 200);
}

function nios_api_dashboard_all(WP_REST_Request $request) {
    if (!nios_api_require_key($request)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }

    $out = [];
    foreach (nios_primary_states() as $state) {
        $out[$state] = nios_state_dashboard_rows($state);
    }

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $out,
    ], 200);
}

function nios_api_dashboard_state(WP_REST_Request $request) {
    if (!nios_api_require_key($request)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }

    $state = strtoupper(trim((string)$request['state']));
    if (!in_array($state, nios_primary_states(), true)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid state'], 400);
    }

    return new WP_REST_Response([
        'status' => 'success',
        'state' => $state,
        'data' => nios_state_dashboard_rows($state),
    ], 200);
}

function nios_api_debug_orders(WP_REST_Request $request) {
    if (!nios_api_require_key($request)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }

    $orders = array_map('nios_normalize_order', nios_get_orders());

    return new WP_REST_Response([
        'status' => 'success',
        'data' => $orders,
    ], 200);
}