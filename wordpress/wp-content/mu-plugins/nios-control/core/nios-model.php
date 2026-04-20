<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================
 * FORCE STORAGE MODEL
 * ============================================
 */

function nios_get_orders() {
    $orders = get_option('nios_orders', []);
    return is_array($orders) ? $orders : [];
}

function nios_save_orders(array $orders) {
    update_option('nios_orders', array_values($orders), false);
}

/**
 * 🔴 HARD SAVE FUNCTION
 */
function nios_ingest_orders(array $incomingOrders) {

    $existing = nios_get_orders();

    foreach ($incomingOrders as $incoming) {

        if (!is_array($incoming)) continue;

        $so = strtoupper(trim((string)($incoming['so_number'] ?? '')));

        if ($so === '') continue;

        // 🔴 CREATE ORDER STRUCTURE
        $order = [
            'so_number' => $so,
            'customer' => $incoming['customer'] ?? '',
            'lines' => $incoming['lines'] ?? [],
            'state' => 'RECEIVING',
            'substate' => 'AWAITING_SUPPLIER',
            'current_action' => 'RECEIVE_GOODS',
            'owner' => 'receiving@norsafe.co.za',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        // 🔴 REPLACE OR ADD
        $found = false;

        foreach ($existing as $i => $ex) {
            if (($ex['so_number'] ?? '') === $so) {
                $existing[$i] = $order;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $existing[] = $order;
        }
    }

    // 🔴 FORCE SAVE
    update_option('nios_orders', $existing, false);

    return count($incomingOrders);
}
