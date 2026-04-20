<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================
 * REAL STATE ALIGNMENT (CSV-DRIVEN)
 * ============================================
 * One-time truth import from verified Excel/CSV snapshot.
 */

if (!function_exists('nios_alignment_map')) {
    function nios_alignment_map() {
        return [
            'SO0000132' => 'EMBROIDERY',
            'SO0000133' => 'EMBROIDERY',
            'SO0000134' => 'EMBROIDERY',
            'SO0000140' => 'COMPLETE',
            'SO0000154' => 'DISPATCH',
            'SO0000156' => 'DISPATCH',
            'SO0000162' => 'COMPLETE',
            'SO0000171' => 'EMBROIDERY',
            'SO0000172' => 'EMBROIDERY',
            'SO0000175' => 'EMBROIDERY',
            'SO0000190' => 'RECEIVING',
            'SO0000194' => 'EMBROIDERY',
            'SO0000195' => 'EMBROIDERY',
            'SO0000196' => 'COMPLETE',
            'SO0000197' => 'EMBROIDERY',
            'SO0000204' => 'RECEIVING',
            'SO0000205' => 'COMPLETE',
            'SO0000210' => 'EMBROIDERY',
            'SO0000211' => 'COMPLETE',
            'SO0000212' => 'RECEIVING',
            'SO0000213' => 'RECEIVING',
            'SO0000216' => 'DISPATCH',
            'SO0000217' => 'COMPLETE',
            'SO0000218' => 'RECEIVING',
            'SO0000220' => 'RECEIVING',
            'SO0000221' => 'RECEIVING',
            'SO0000223' => 'RECEIVING',
            'SO0000224' => 'RECEIVING',
            'SO0000225' => 'RECEIVING',
            'SO0000226' => 'RECEIVING',
            'SO0000227' => 'RECEIVING',
            'SO0000228' => 'RECEIVING',
        ];
    }
}

if (!function_exists('nios_apply_alignment_state')) {
    function nios_apply_alignment_state(array $order, string $state): array {

        switch ($state) {

            case 'RECEIVING':
                $order['state'] = 'RECEIVING';
                $order['substate'] = 'AWAITING_SUPPLIER';
                $order['current_action'] = 'RECEIVE_GOODS';
                $order['owner'] = 'receiving@norsafe.co.za';
                break;

            case 'EMBROIDERY':
                $order['state'] = 'EMBROIDERY';
                $order['substate'] = 'IN_PRODUCTION';
                $order['current_action'] = 'COMPLETE_EMB';
                $order['owner'] = 'embroidery@norsafe.co.za';
                break;

            case 'DISPATCH':
                $order['state'] = 'DISPATCH';
                $order['substate'] = 'READY_FOR_DISPATCH';
                $order['current_action'] = 'DISPATCH_ORDER';
                $order['owner'] = 'dispatch@norsafe.co.za';
                break;

            case 'COMPLETE':
                $order['state'] = 'COMPLETE';
                $order['substate'] = 'DELIVERED';
                $order['current_action'] = '';
                $order['owner'] = 'system';
                $order['dispatched'] = true;
                $order['completed'] = true;
                break;

            default:
                // fallback only if something unexpected comes through
                $order['state'] = 'EXCEPTION';
                $order['substate'] = 'SYSTEM_MISMATCH';
                $order['current_action'] = 'REVIEW_EXCEPTION';
                $order['owner'] = 'liaison@norsafe.co.za';
                break;
        }

        $order['updated_at'] = current_time('mysql');
        $order['aligned_at'] = current_time('mysql');

        return $order;
    }
}

if (!function_exists('nios_align_all_orders_to_reality')) {
    function nios_align_all_orders_to_reality() {

        $map = nios_alignment_map();
        $orders = nios_get_orders();
        $aligned = [];
        $updated_count = 0;

        foreach ($orders as $order) {
            $o = nios_normalize_order($order);
            $so = strtoupper(trim((string)($o['so_number'] ?? '')));

            if (isset($map[$so])) {
                $o = nios_apply_alignment_state($o, $map[$so]);
                $updated_count++;
            }

            $aligned[] = $o;
        }

        nios_save_orders($aligned);

        return $updated_count;
    }
}