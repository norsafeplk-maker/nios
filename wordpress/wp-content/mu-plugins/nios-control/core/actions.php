<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/engine.php';

/**
 * ============================================
 * REGISTER ACTION ENDPOINT
 * ============================================
 */
add_action('rest_api_init', function () {
    register_rest_route('nios/v1', '/action-complete', [
        'methods'  => 'POST',
        'callback' => 'nios_action_complete_handler',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * ============================================
 * ACTION COMPLETE HANDLER
 * ============================================
 */
function nios_action_complete_handler(WP_REST_Request $request) {

    // 🔐 AUTH
    $key = $request->get_header('X-NIOS-KEY');
    if ($key !== nios_api_key()) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 403);
    }

    $data = json_decode($request->get_body(), true);

    $so_number   = $data['so_number'] ?? '';
    $ticket_data = $data['ticket_data'] ?? [];
    $notes       = $data['notes'] ?? '';

    if (!$so_number) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Missing SO number'
        ], 400);
    }

    $orders = nios_get_orders();
    $index  = nios_find_order_index($so_number);

    if ($index === -1) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Order not found'
        ], 404);
    }

    $order = $orders[$index];

    $substate = $order['substate'] ?? '';

    /**
     * ============================================
     * VALIDATE REQUIRED FIELDS
     * ============================================
     */
    $validation = nios_validate_ticket_payload($substate, $ticket_data);

    if (!$validation['ok']) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $validation['message']
        ], 400);
    }

    /**
     * ============================================
     * SAVE INPUT
     * ============================================
     */
    $order['ticket_data'] = $ticket_data;
    $order['notes']       = $notes;

    /**
     * ============================================
     * MARK ACTION COMPLETE
     * ============================================
     */
    if (!isset($order['current_action'])) {
        $order['current_action'] = [];
    }

    $order['current_action']['status'] = 'DONE';

    /**
     * ============================================
     * TRANSITION ENGINE (THIS IS THE CORE)
     * ============================================
     */
    nios_transition_order($order);

    /**
     * ============================================
     * SAVE BACK
     * ============================================
     */
    $orders[$index] = $order;
    nios_save_orders($orders);

    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Transition complete',
        'data' => [
            'so_number' => $order['so_number'],
            'state'     => $order['state'],
            'substate'  => $order['substate']
        ]
    ], 200);
}