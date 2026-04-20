<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('nios_complete_action_for_order')) {
    function nios_complete_action_for_order($so_number) {
        $orders = nios_get_orders();
        $index = nios_find_order_index($orders, $so_number);

        if ($index < 0) {
            return [
                'success' => false,
                'message' => 'Order not found',
            ];
        }

        $o = nios_normalize_order($orders[$index]);

        switch ($o['state']) {
            case 'RECEIVING':
                $o['state'] = 'QC';
                $o['substate'] = 'AWAITING_QC';
                $o['current_action'] = 'QUALITY_CHECK';
                $o['owner'] = 'qc@norsafe.co.za';
                break;

            case 'QC':
                if (!empty($o['flags']['EMB'])) {
                    $o['state'] = 'EMBROIDERY';
                    $o['substate'] = 'IN_PRODUCTION';
                    $o['current_action'] = 'COMPLETE_EMB';
                    $o['owner'] = 'embroidery@norsafe.co.za';
                } elseif (!empty($o['flags']['PRINT'])) {
                    $o['state'] = 'PRINTING';
                    $o['substate'] = 'IN_PRINT';
                    $o['current_action'] = 'COMPLETE_PRINT';
                    $o['owner'] = 'printing@norsafe.co.za';
                } elseif (!empty($o['flags']['SUB'])) {
                    $o['state'] = 'SUBLIMATION';
                    $o['substate'] = 'IN_SUBLIMATION';
                    $o['current_action'] = 'COMPLETE_SUBLIMATION';
                    $o['owner'] = 'sublimation@norsafe.co.za';
                } elseif (!empty($o['flags']['SEW'])) {
                    $o['state'] = 'ADJUSTMENTS';
                    $o['substate'] = 'IN_ALTERATION';
                    $o['current_action'] = 'COMPLETE_ALTERATION';
                    $o['owner'] = 'adjustments@norsafe.co.za';
                } else {
                    $o['state'] = 'DISPATCH';
                    $o['substate'] = 'AWAITING_PACK';
                    $o['current_action'] = 'PACK_ORDER';
                    $o['owner'] = 'dispatch@norsafe.co.za';
                }
                break;

            case 'EMBROIDERY':
            case 'PRINTING':
            case 'SUBLIMATION':
            case 'ADJUSTMENTS':
                $o['state'] = 'DISPATCH';
                $o['substate'] = 'AWAITING_PACK';
                $o['current_action'] = 'PACK_ORDER';
                $o['owner'] = 'dispatch@norsafe.co.za';
                break;

            case 'DISPATCH':
                $o['state'] = 'COMPLETE';
                $o['substate'] = 'DELIVERED';
                $o['current_action'] = '';
                $o['owner'] = 'system';
                $o['dispatched'] = true;
                break;

            case 'COMPLETE':
                return [
                    'success' => true,
                    'message' => 'Order already complete',
                    'order' => $o,
                ];

            default:
                return [
                    'success' => false,
                    'message' => 'Invalid current state',
                ];
        }

        $o['updated_at'] = current_time('mysql');
        $orders[$index] = $o;
        nios_save_orders($orders);

        return [
            'success' => true,
            'message' => 'Action completed',
            'order' => $o,
        ];
    }
}