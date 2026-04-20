<?php
if (!defined('ABSPATH')) exit;

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

function nios_get_substates($state) {
    $map = nios_substates();
    return $map[$state] ?? [];
}