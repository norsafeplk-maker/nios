<?php
/**
 * Plugin Name: NIOS API
 * Description: NIOS ingestion API route for Sales Orders.
 * Version: 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('nios/v1', '/order', [
        'methods'             => 'POST',
        'callback'            => 'nios_receive_orders',
        'permission_callback' => '__return_true',
    ]);
});

function nios_extract_orders_from_payload($payload) {
    if (!is_array($payload)) {
        return null;
    }

    if (isset($payload['orders']) && is_array($payload['orders'])) {
        return $payload['orders'];
    }

    if (isset($payload['so_number'])) {
        return [$payload];
    }

    return null;
}

function nios_receive_orders(WP_REST_Request $request) {
    $key = (string) $request->get_header('X-NIOS-KEY');

    if ($key !== 'test123') {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'Unauthorized',
        ], 403);
    }

    $payload = $request->get_json_params();
    $orders_in = nios_extract_orders_from_payload($payload);

    if (!is_array($orders_in)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'Invalid payload: expected {"orders":[...]} or single order object',
            'received_payload' => $payload,
        ], 400);
    }

    $orders = get_option('nios_orders', []);
    if (!is_array($orders)) {
        $orders = [];
    }

    $received = 0;
    $rejected = [];

    foreach ($orders_in as $incoming) {
        if (!is_array($incoming)) {
            $rejected[] = [
                'reason' => 'Order entry is not an object',
                'order'  => $incoming,
            ];
            continue;
        }

        $so_number = isset($incoming['so_number']) ? sanitize_text_field((string) $incoming['so_number']) : '';
        $customer = isset($incoming['customer']) ? sanitize_text_field((string) $incoming['customer']) : '';
        $creation_date = isset($incoming['creation_date']) ? sanitize_text_field((string) $incoming['creation_date']) : '';
        $lines = isset($incoming['lines']) && is_array($incoming['lines']) ? $incoming['lines'] : [];

        if ($so_number === '') {
            $rejected[] = [
                'reason' => 'Missing so_number',
                'order'  => $incoming,
            ];
            continue;
        }

        if ($customer === '') {
            $rejected[] = [
                'reason' => 'Missing customer',
                'order'  => $incoming,
            ];
            continue;
        }

        if ($creation_date === '') {
            $rejected[] = [
                'reason' => 'Missing creation_date',
                'order'  => $incoming,
            ];
            continue;
        }

        $normalized_lines = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $product_name = isset($line['product_name']) ? sanitize_text_field((string) $line['product_name']) : '';
            $quantity = isset($line['quantity']) ? intval($line['quantity']) : 0;

            if ($product_name === '' || $quantity < 1) {
                continue;
            }

            $normalized_lines[] = [
                'product_name' => $product_name,
                'quantity'     => $quantity,
            ];
        }

        if (empty($normalized_lines)) {
            $rejected[] = [
                'reason' => 'No valid lines',
                'order'  => $incoming,
            ];
            continue;
        }

        $matched = false;

        foreach ($orders as $index => $existing) {
            if (!is_array($existing)) {
                continue;
            }

            $existing_so = isset($existing['so_number']) ? (string) $existing['so_number'] : '';

            if ($existing_so === $so_number) {
                $orders[$index]['customer'] = $customer;
                $orders[$index]['creation_date'] = $creation_date;
                $orders[$index]['lines'] = $normalized_lines;
                $orders[$index]['updated_at'] = current_time('mysql');

                if (empty($orders[$index]['state'])) {
                    $orders[$index]['state'] = 'RECEIVING';
                }

                if (empty($orders[$index]['owner'])) {
                    $orders[$index]['owner'] = 'liaison@norsafe.co.za';
                }

                $matched = true;
                $received++;
                break;
            }
        }

        if (!$matched) {
            $orders[] = [
                'so_number'     => $so_number,
                'customer'      => $customer,
                'creation_date' => $creation_date,
                'lines'         => $normalized_lines,
                'state'         => 'RECEIVING',
                'owner'         => 'liaison@norsafe.co.za',
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ];
            $received++;
        }
    }

    update_option('nios_orders', array_values($orders), false);

    return new WP_REST_Response([
        'status'   => 'success',
        'received' => $received,
        'rejected' => $rejected,
    ], 200);
}
