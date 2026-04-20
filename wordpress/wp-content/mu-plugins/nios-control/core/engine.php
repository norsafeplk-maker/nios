<?php
if (!defined('ABSPATH')) exit;

/**
 * NIOS Engine
 * Permissions-first control layer
 *
 * This file handles:
 * - user -> role mapping
 * - dashboard endpoint (logged-in users only)
 * - action completion endpoint (logged-in users only)
 * - state transition logic
 * - owner combined dashboard
 * - audit logging
 * - shortcode UI [nios_dashboard]
 *
 * IMPORTANT:
 * - This does NOT replace your external ingestion endpoint /nios/v1/order
 * - Keep your Python/API-key ingestion route where it already lives
 */

class NIOS_Engine {

    const ORDERS_OPTION = 'nios_orders';
    const AUDIT_OPTION  = 'nios_audit_log';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_shortcode('nios_dashboard', [__CLASS__, 'render_dashboard_shortcode']);
    }

    /* =========================================================
     * ROLES / ACCESS
     * ========================================================= */
    public static function get_user_role() {
        if (!is_user_logged_in()) return null;

        $user = wp_get_current_user();
        $email = strtolower(trim((string) $user->user_email));

        $map = [
            // department users
            'receiving@norsafe.co.za'   => 'RECEIVING',
            'qc@norsafe.co.za'          => 'QC',
            'embroidery@norsafe.co.za'  => 'EMBROIDERY',
            'printing@norsafe.co.za'    => 'PRINTING',
            'sublimation@norsafe.co.za' => 'SUBLIMATION',
            'adjustments@norsafe.co.za' => 'ADJUSTMENTS',
            'dispatch@norsafe.co.za'    => 'DISPATCH',

            // control
            'liaison@norsafe.co.za'     => 'LIAISON',

            // combined owner view
            'thys@norsafe.co.za'        => 'OWNER',
            'inge@norsafe.co.za'        => 'OWNER',
        ];

        return $map[$email] ?? null;
    }

    public static function get_visible_states_for_role($role) {
        $all_states = self::get_all_primary_states();

        if ($role === 'OWNER') {
            return $all_states;
        }

        if ($role === 'LIAISON') {
            return ['EXCEPTION'];
        }

        if (in_array($role, $all_states, true)) {
            return [$role];
        }

        return [];
    }

    public static function can_user_see_state($role, $state) {
        return in_array($state, self::get_visible_states_for_role($role), true);
    }

    public static function can_user_act_on_state($role, $state) {
        if ($role === 'OWNER') return true;
        if ($role === 'LIAISON') return $state === 'EXCEPTION';
        return $role === $state;
    }

    public static function get_all_primary_states() {
        return [
            'RECEIVING',
            'QC',
            'EMBROIDERY',
            'PRINTING',
            'SUBLIMATION',
            'ADJUSTMENTS',
            'DISPATCH',
            'COMPLETE',
            'EXCEPTION'
        ];
    }

    /* =========================================================
     * ROUTES
     * ========================================================= */
    public static function register_routes() {

        register_rest_route('nios/v1', '/dashboard', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'dashboard_endpoint'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('nios/v1', '/action-complete', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'action_complete_endpoint'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    /* =========================================================
     * DASHBOARD ENDPOINT
     * ========================================================= */
    public static function dashboard_endpoint(WP_REST_Request $request) {
        $role = self::get_user_role();

        if (!$role) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'User is not mapped to a NIOS role.'
            ], 403);
        }

        $orders = get_option(self::ORDERS_OPTION, []);
        if (!is_array($orders)) $orders = [];

        $visible_states = self::get_visible_states_for_role($role);

        $grouped = [];
        foreach ($visible_states as $state) {
            $grouped[$state] = [];
        }

        foreach ($orders as $order) {
            $state = strtoupper(trim((string) ($order['state'] ?? 'RECEIVING')));

            if (!self::can_user_see_state($role, $state)) {
                continue;
            }

            $grouped[$state][] = self::normalize_order_for_output($order, $role);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'role'           => $role,
                'visible_states' => $visible_states,
                'grouped'        => $grouped,
                'counts'         => self::build_counts($grouped),
                'server_time'    => current_time('mysql'),
            ]
        ], 200);
    }

    protected static function build_counts($grouped) {
        $counts = [];
        foreach ($grouped as $state => $items) {
            $counts[$state] = is_array($items) ? count($items) : 0;
        }
        return $counts;
    }

    protected static function normalize_order_for_output($order, $role) {
        $state = strtoupper(trim((string) ($order['state'] ?? 'RECEIVING')));
        $substate = strtoupper(trim((string) ($order['substate'] ?? self::default_substate_for_state($state))));
        $current_action = strtoupper(trim((string) ($order['current_action'] ?? self::default_action_for_state($state, $substate))));
        $action_status = strtoupper(trim((string) ($order['action_status'] ?? 'OPEN')));

        return [
            'so_number'      => (string) ($order['so_number'] ?? ''),
            'customer'       => (string) ($order['customer'] ?? ''),
            'creation_date'  => (string) ($order['creation_date'] ?? ''),
            'state'          => $state,
            'substate'       => $substate,
            'current_action' => $current_action,
            'action_status'  => $action_status,
            'owner'          => (string) ($order['owner'] ?? $state),
            'due_at'         => (string) ($order['due_at'] ?? ''),
            'updated_at'     => (string) ($order['updated_at'] ?? ''),
            'notes'          => isset($order['notes']) && is_array($order['notes']) ? $order['notes'] : [],
            'lines'          => isset($order['lines']) && is_array($order['lines']) ? $order['lines'] : [],
            'indicators'     => isset($order['indicators']) && is_array($order['indicators']) ? $order['indicators'] : [],
            'can_act'        => self::can_user_act_on_state($role, $state),
        ];
    }

    /* =========================================================
     * ACTION COMPLETION ENDPOINT
     * ========================================================= */
    public static function action_complete_endpoint(WP_REST_Request $request) {
        $role = self::get_user_role();

        if (!$role) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'User is not mapped to a NIOS role.'
            ], 403);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = [];

        $so_number   = strtoupper(trim((string) ($body['so_number'] ?? '')));
        $action_type = strtoupper(trim((string) ($body['action_type'] ?? '')));
        $note        = trim((string) ($body['note'] ?? ''));

        if ($so_number === '' || $action_type === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'so_number and action_type are required.'
            ], 400);
        }

        $orders = get_option(self::ORDERS_OPTION, []);
        if (!is_array($orders)) $orders = [];

        $found = false;
        $result_order = null;

        foreach ($orders as $index => $order) {
            $existing_so = strtoupper(trim((string) ($order['so_number'] ?? '')));
            if ($existing_so !== $so_number) {
                continue;
            }

            $found = true;

            $current_state    = strtoupper(trim((string) ($order['state'] ?? 'RECEIVING')));
            $current_substate = strtoupper(trim((string) ($order['substate'] ?? self::default_substate_for_state($current_state))));
            $expected_action  = strtoupper(trim((string) ($order['current_action'] ?? self::default_action_for_state($current_state, $current_substate))));

            if (!self::can_user_act_on_state($role, $current_state)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Unauthorized state action.'
                ], 403);
            }

            if ($expected_action !== '' && $action_type !== $expected_action) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid action for current state.',
                    'data'    => [
                        'expected_action' => $expected_action,
                        'current_state'   => $current_state,
                        'current_substate'=> $current_substate,
                    ]
                ], 409);
            }

            $transition = self::get_transition($order, $action_type);

            if (!$transition['ok']) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $transition['message']
                ], 409);
            }

            $prev_state = $current_state;
            $prev_substate = $current_substate;

            $order['state']          = $transition['state'];
            $order['substate']       = $transition['substate'];
            $order['current_action'] = $transition['current_action'];
            $order['action_status']  = $transition['action_status'];
            $order['owner']          = $transition['owner'];
            $order['due_at']         = $transition['due_at'];
            $order['updated_at']     = current_time('mysql');

            if (!isset($order['notes']) || !is_array($order['notes'])) {
                $order['notes'] = [];
            }

            if ($note !== '') {
                $order['notes'][] = [
                    'timestamp' => current_time('mysql'),
                    'user'      => wp_get_current_user()->user_email,
                    'note'      => $note,
                ];
            }

            $orders[$index] = $order;
            $result_order = $order;

            self::append_audit_log([
                'so_number'      => $so_number,
                'timestamp'      => current_time('mysql'),
                'user'           => wp_get_current_user()->user_email,
                'role'           => $role,
                'action'         => $action_type,
                'state_from'     => $prev_state,
                'state_to'       => $transition['state'],
                'substate_from'  => $prev_substate,
                'substate_to'    => $transition['substate'],
                'owner'          => $transition['owner'],
                'notes'          => $note,
            ]);

            break;
        }

        if (!$found) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        update_option(self::ORDERS_OPTION, $orders, false);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Action completed.',
            'data'    => self::normalize_order_for_output($result_order, $role)
        ], 200);
    }

    /* =========================================================
     * TRANSITIONS
     * ========================================================= */
    protected static function get_transition($order, $action_type) {
        $state = strtoupper(trim((string) ($order['state'] ?? 'RECEIVING')));
        $substate = strtoupper(trim((string) ($order['substate'] ?? self::default_substate_for_state($state))));

        switch ($state) {

            case 'RECEIVING':
                if ($action_type !== 'RECEIVE_GOODS') {
                    return self::transition_error('Only RECEIVE_GOODS is allowed in RECEIVING.');
                }

                return self::build_transition('QC', 'AWAITING_QC');

            case 'QC':
                if ($action_type !== 'QUALITY_CHECK') {
                    return self::transition_error('Only QUALITY_CHECK is allowed in QC.');
                }

                $next_state = self::determine_production_route($order);

                if ($next_state === 'DISPATCH') {
                    return self::build_transition('DISPATCH', 'AWAITING_PACK');
                }

                return self::build_transition($next_state, self::default_substate_for_state($next_state));

            case 'EMBROIDERY':
                if ($action_type !== 'COMPLETE_EMBROIDERY') {
                    return self::transition_error('Only COMPLETE_EMBROIDERY is allowed in EMBROIDERY.');
                }
                return self::build_transition('DISPATCH', 'AWAITING_PACK');

            case 'PRINTING':
                if ($action_type !== 'COMPLETE_PRINTING') {
                    return self::transition_error('Only COMPLETE_PRINTING is allowed in PRINTING.');
                }
                return self::build_transition('DISPATCH', 'AWAITING_PACK');

            case 'SUBLIMATION':
                if ($action_type !== 'COMPLETE_SUBLIMATION') {
                    return self::transition_error('Only COMPLETE_SUBLIMATION is allowed in SUBLIMATION.');
                }
                return self::build_transition('DISPATCH', 'AWAITING_PACK');

            case 'ADJUSTMENTS':
                if ($action_type !== 'COMPLETE_ADJUSTMENTS') {
                    return self::transition_error('Only COMPLETE_ADJUSTMENTS is allowed in ADJUSTMENTS.');
                }
                return self::build_transition('DISPATCH', 'AWAITING_PACK');

            case 'DISPATCH':
                if ($substate === 'AWAITING_PACK' && $action_type === 'PACK_ORDER') {
                    return self::build_transition('DISPATCH', 'READY_FOR_DISPATCH');
                }

                if ($substate === 'READY_FOR_DISPATCH' && $action_type === 'DISPATCH_ORDER') {
                    return self::build_transition('COMPLETE', 'DELIVERED');
                }

                return self::transition_error('Invalid DISPATCH action for current substate.');

            case 'EXCEPTION':
                if ($action_type !== 'RESOLVE_EXCEPTION') {
                    return self::transition_error('Only RESOLVE_EXCEPTION is allowed in EXCEPTION.');
                }
                return self::build_transition('QC', 'AWAITING_QC');

            case 'COMPLETE':
                return self::transition_error('Completed orders cannot be moved.');

            default:
                return self::transition_error('Unknown state.');
        }
    }

    protected static function determine_production_route($order) {
        $indicators = self::extract_indicators($order);

        if (!empty($indicators['EMB']))   return 'EMBROIDERY';
        if (!empty($indicators['PRINT'])) return 'PRINTING';
        if (!empty($indicators['CON-DE'])) return 'SUBLIMATION';
        if (!empty($indicators['SUB']))    return 'SUBLIMATION';
        if (!empty($indicators['SEW']))    return 'ADJUSTMENTS';

        return 'DISPATCH';
    }

    protected static function extract_indicators($order) {
        $indicators = [];

        if (isset($order['indicators']) && is_array($order['indicators'])) {
            foreach ($order['indicators'] as $key => $value) {
                $indicators[strtoupper(trim((string) $key))] = !empty($value);
            }
        }

        if (isset($order['payload']) && is_array($order['payload']) && isset($order['payload']['indicators']) && is_array($order['payload']['indicators'])) {
            foreach ($order['payload']['indicators'] as $key => $value) {
                $indicators[strtoupper(trim((string) $key))] = !empty($value);
            }
        }

        return $indicators;
    }

    protected static function build_transition($new_state, $new_substate) {
        $new_state = strtoupper(trim((string) $new_state));
        $new_substate = strtoupper(trim((string) $new_substate));

        return [
            'ok'            => true,
            'message'       => 'OK',
            'state'         => $new_state,
            'substate'      => $new_substate,
            'current_action'=> self::default_action_for_state($new_state, $new_substate),
            'action_status' => $new_state === 'COMPLETE' ? 'DONE' : 'OPEN',
            'owner'         => self::owner_for_state($new_state),
            'due_at'        => self::calculate_due_at_for_state($new_state, $new_substate),
        ];
    }

    protected static function transition_error($message) {
        return [
            'ok'      => false,
            'message' => $message,
        ];
    }

    protected static function default_substate_for_state($state) {
        switch ($state) {
            case 'RECEIVING':   return 'AWAITING_SUPPLIER';
            case 'QC':          return 'AWAITING_QC';
            case 'EMBROIDERY':  return 'AWAITING_DIGITIZING';
            case 'PRINTING':    return 'AWAITING_ARTWORK';
            case 'SUBLIMATION': return 'AWAITING_DESIGN';
            case 'ADJUSTMENTS': return 'AWAITING_ALTERATION';
            case 'DISPATCH':    return 'AWAITING_PACK';
            case 'COMPLETE':    return 'DELIVERED';
            case 'EXCEPTION':   return 'SYSTEM_MISMATCH';
            default:            return 'SYSTEM_MISMATCH';
        }
    }

    protected static function default_action_for_state($state, $substate = '') {
        switch ($state) {
            case 'RECEIVING':   return 'RECEIVE_GOODS';
            case 'QC':          return 'QUALITY_CHECK';
            case 'EMBROIDERY':  return 'COMPLETE_EMBROIDERY';
            case 'PRINTING':    return 'COMPLETE_PRINTING';
            case 'SUBLIMATION': return 'COMPLETE_SUBLIMATION';
            case 'ADJUSTMENTS': return 'COMPLETE_ADJUSTMENTS';
            case 'DISPATCH':
                return $substate === 'READY_FOR_DISPATCH' ? 'DISPATCH_ORDER' : 'PACK_ORDER';
            case 'EXCEPTION':   return 'RESOLVE_EXCEPTION';
            case 'COMPLETE':    return '';
            default:            return '';
        }
    }

    protected static function owner_for_state($state) {
        switch ($state) {
            case 'RECEIVING':   return 'receiving@norsafe.co.za';
            case 'QC':          return 'qc@norsafe.co.za';
            case 'EMBROIDERY':  return 'embroidery@norsafe.co.za';
            case 'PRINTING':    return 'printing@norsafe.co.za';
            case 'SUBLIMATION': return 'sublimation@norsafe.co.za';
            case 'ADJUSTMENTS': return 'adjustments@norsafe.co.za';
            case 'DISPATCH':    return 'dispatch@norsafe.co.za';
            case 'EXCEPTION':   return 'liaison@norsafe.co.za';
            case 'COMPLETE':    return 'system';
            default:            return 'system';
        }
    }

    protected static function calculate_due_at_for_state($state, $substate) {
        $hours = self::sla_hours_for_state($state, $substate);
        if ($hours <= 0) return '';

        $timestamp = current_time('timestamp') + ($hours * HOUR_IN_SECONDS);
        return date_i18n('Y-m-d H:i:s', $timestamp);
    }

    protected static function sla_hours_for_state($state, $substate) {
        switch ($state) {
            case 'RECEIVING': return 24;
            case 'QC': return 24;
            case 'EMBROIDERY': return 24;
            case 'PRINTING': return 24;
            case 'SUBLIMATION': return 24;
            case 'ADJUSTMENTS': return 24;
            case 'DISPATCH': return 24;
            case 'EXCEPTION': return 4;
            default: return 0;
        }
    }

    /* =========================================================
     * AUDIT
     * ========================================================= */
    protected static function append_audit_log($row) {
        $log = get_option(self::AUDIT_OPTION, []);
        if (!is_array($log)) $log = [];

        $log[] = $row;

        update_option(self::AUDIT_OPTION, $log, false);
    }

    /* =========================================================
     * SHORTCODE UI
     * ========================================================= */
    public static function render_dashboard_shortcode() {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<div class="nios-login-required"><p>You must be logged in to view NIOS.</p><p><a href="' . esc_url($login_url) . '">Log in</a></p></div>';
        }

        $nonce = wp_create_nonce('wp_rest');
        $dashboard_url = esc_url_raw(rest_url('nios/v1/dashboard'));
        $action_url = esc_url_raw(rest_url('nios/v1/action-complete'));

        ob_start();
        ?>
        <div id="nios-app" class="nios-app">
            <div class="nios-topbar">
                <div>
                    <h2>NIOS Dashboard</h2>
                    <div id="nios-meta" class="nios-meta">Loading...</div>
                </div>
                <button id="nios-refresh" type="button">Refresh</button>
            </div>

            <div id="nios-board" class="nios-board"></div>
        </div>

        <style>
            .nios-app{
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                padding: 18px;
                background: #f4f6f8;
                border-radius: 16px;
            }
            .nios-topbar{
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:16px;
                margin-bottom:18px;
            }
            .nios-topbar h2{
                margin:0 0 4px 0;
                font-size:24px;
            }
            .nios-meta{
                font-size:13px;
                color:#5d6772;
            }
            .nios-topbar button{
                background:#111827;
                color:#fff;
                border:none;
                border-radius:12px;
                padding:10px 14px;
                cursor:pointer;
            }
            .nios-board{
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap:16px;
            }
            .nios-column{
                background:#e9edf1;
                border-radius:16px;
                padding:14px;
                min-height:180px;
            }
            .nios-column h3{
                margin:0 0 12px 0;
                font-size:16px;
            }
            .nios-card{
                background:#fff;
                border-radius:14px;
                padding:14px;
                margin-bottom:12px;
                box-shadow:0 2px 10px rgba(0,0,0,0.06);
            }
            .nios-card:last-child{
                margin-bottom:0;
            }
            .nios-so{
                font-weight:700;
                font-size:15px;
                margin-bottom:6px;
            }
            .nios-line{
                font-size:13px;
                color:#4b5563;
                margin-bottom:4px;
            }
            .nios-lines{
                margin-top:10px;
                padding-top:10px;
                border-top:1px solid #e5e7eb;
                font-size:12px;
                color:#4b5563;
            }
            .nios-lines div{
                margin-bottom:4px;
            }
            .nios-actions{
                margin-top:12px;
                display:flex;
                gap:8px;
                flex-wrap:wrap;
            }
            .nios-actions button{
                background:#0f172a;
                color:#fff;
                border:none;
                border-radius:10px;
                padding:8px 10px;
                cursor:pointer;
                font-size:12px;
            }
            .nios-empty{
                color:#6b7280;
                font-size:13px;
            }
            .nios-note{
                width:100%;
                min-height:54px;
                resize:vertical;
                margin-top:10px;
                border:1px solid #d1d5db;
                border-radius:10px;
                padding:8px;
                font-size:12px;
            }
        </style>

        <script>
        (function(){
            const DASHBOARD_URL = <?php echo wp_json_encode($dashboard_url); ?>;
            const ACTION_URL    = <?php echo wp_json_encode($action_url); ?>;
            const NONCE         = <?php echo wp_json_encode($nonce); ?>;

            const board = document.getElementById('nios-board');
            const meta  = document.getElementById('nios-meta');
            const refreshBtn = document.getElementById('nios-refresh');

            async function loadBoard() {
                meta.textContent = 'Loading...';

                const res = await fetch(DASHBOARD_URL, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': NONCE
                    },
                    credentials: 'same-origin'
                });

                const json = await res.json();

                if (!json.success) {
                    meta.textContent = json.message || 'Failed to load dashboard.';
                    board.innerHTML = '';
                    return;
                }

                const data = json.data || {};
                const grouped = data.grouped || {};
                const states = data.visible_states || [];
                const role = data.role || 'UNKNOWN';

                meta.textContent = 'Role: ' + role + ' | Visible states: ' + states.join(', ') + ' | Server time: ' + (data.server_time || '');

                board.innerHTML = '';

                states.forEach(state => {
                    const col = document.createElement('div');
                    col.className = 'nios-column';

                    const list = Array.isArray(grouped[state]) ? grouped[state] : [];
                    let html = '<h3>' + state + ' (' + list.length + ')</h3>';

                    if (!list.length) {
                        html += '<div class="nios-empty">No orders in this state.</div>';
                    } else {
                        html += list.map(renderCard).join('');
                    }

                    col.innerHTML = html;
                    board.appendChild(col);
                });

                bindButtons();
            }

            function renderCard(order) {
                const lines = Array.isArray(order.lines) ? order.lines : [];

                const lineHtml = lines.length
                    ? '<div class="nios-lines">' + lines.map(line => {
                        const product = escapeHtml(String(line.product_name || ''));
                        const qty = escapeHtml(String(line.quantity || ''));
                        return '<div>' + product + ' × ' + qty + '</div>';
                    }).join('') + '</div>'
                    : '';

                const noteBox = order.can_act
                    ? '<textarea class="nios-note" data-so="' + escapeAttr(order.so_number) + '" placeholder="Optional note..."></textarea>'
                    : '';

                const actionBtn = order.can_act && order.current_action
                    ? '<div class="nios-actions"><button type="button" data-so="' + escapeAttr(order.so_number) + '" data-action="' + escapeAttr(order.current_action) + '">' + escapeHtml(order.current_action) + '</button></div>'
                    : '';

                return ''
                    + '<div class="nios-card">'
                    +   '<div class="nios-so">' + escapeHtml(order.so_number) + '</div>'
                    +   '<div class="nios-line"><strong>Customer:</strong> ' + escapeHtml(order.customer || '-') + '</div>'
                    +   '<div class="nios-line"><strong>Substate:</strong> ' + escapeHtml(order.substate || '-') + '</div>'
                    +   '<div class="nios-line"><strong>Action:</strong> ' + escapeHtml(order.current_action || '-') + '</div>'
                    +   '<div class="nios-line"><strong>Due:</strong> ' + escapeHtml(order.due_at || '-') + '</div>'
                    +   lineHtml
                    +   noteBox
                    +   actionBtn
                    + '</div>';
            }

            function bindButtons() {
                const buttons = board.querySelectorAll('button[data-so][data-action]');
                buttons.forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const so = btn.getAttribute('data-so');
                        const action = btn.getAttribute('data-action');
                        const noteEl = board.querySelector('.nios-note[data-so="' + cssEscape(so) + '"]');
                        const note = noteEl ? noteEl.value : '';

                        btn.disabled = true;
                        btn.textContent = 'Working...';

                        try {
                            const res = await fetch(ACTION_URL, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': NONCE
                                },
                                body: JSON.stringify({
                                    so_number: so,
                                    action_type: action,
                                    note: note
                                })
                            });

                            const json = await res.json();

                            if (!json.success) {
                                alert(json.message || 'Action failed.');
                            }

                            await loadBoard();
                        } catch (err) {
                            alert('Action failed.');
                            await loadBoard();
                        }
                    });
                });
            }

            function escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function escapeAttr(value) {
                return escapeHtml(value);
            }

            function cssEscape(value) {
                if (window.CSS && window.CSS.escape) return window.CSS.escape(value);
                return String(value).replace(/"/g, '\\"');
            }

            refreshBtn.addEventListener('click', loadBoard);
            loadBoard();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

NIOS_Engine::init();