<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('nios_primary_states')) {
    function nios_primary_states() {
        return [
            'RECEIVING',
            'QC',
            'EMBROIDERY',
            'PRINTING',
            'SUBLIMATION',
            'ADJUSTMENTS',
            'DISPATCH',
            'COMPLETE',
            'EXCEPTION',
        ];
    }
}

if (!function_exists('nios_substates')) {
    function nios_substates() {
        return [
            'RECEIVING' => [
                'AWAITING_SUPPLIER',
                'PARTIAL_RECEIVED',
                'RECEIVED_COMPLETE',
                'RECEIVING_BLOCKED',
            ],
            'QC' => [
                'AWAITING_QC',
                'QC_IN_PROGRESS',
                'QC_PASSED',
                'QC_BLOCKED',
            ],
            'EMBROIDERY' => [
                'AWAITING_DIGITIZING',
                'IN_PRODUCTION',
                'EMB_COMPLETE',
                'EMB_BLOCKED',
            ],
            'PRINTING' => [
                'AWAITING_ARTWORK',
                'IN_PRINT',
                'PRINT_COMPLETE',
                'PRINT_BLOCKED',
            ],
            'SUBLIMATION' => [
                'AWAITING_DESIGN',
                'IN_SUBLIMATION',
                'SUBLIMATION_COMPLETE',
                'SUB_BLOCKED',
            ],
            'ADJUSTMENTS' => [
                'AWAITING_ALTERATION',
                'IN_ALTERATION',
                'ALTERATION_COMPLETE',
                'ADJUSTMENT_BLOCKED',
            ],
            'DISPATCH' => [
                'AWAITING_PACK',
                'PACKING',
                'READY_FOR_DISPATCH',
                'DISPATCHED',
            ],
            'COMPLETE' => [
                'DELIVERED',
                'INVOICED',
                'PAID',
                'CLOSED',
            ],
            'EXCEPTION' => [
                'DATA_ERROR',
                'SYSTEM_MISMATCH',
                'MANUAL_OVERRIDE',
            ],
        ];
    }
}

if (!function_exists('nios_valid_state')) {
    function nios_valid_state($state) {
        return in_array(strtoupper((string)$state), nios_primary_states(), true);
    }
}

if (!function_exists('nios_valid_substate')) {
    function nios_valid_substate($state, $substate) {
        $state = strtoupper((string)$state);
        $substate = strtoupper((string)$substate);

        $map = nios_substates();
        return isset($map[$state]) && in_array($substate, $map[$state], true);
    }
}