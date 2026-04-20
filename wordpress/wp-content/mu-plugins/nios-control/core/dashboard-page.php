<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('nios_render_dashboard_shortcode')) {
    function nios_render_dashboard_shortcode($atts) {
        $atts = shortcode_atts([
            'state' => 'RECEIVING',
            'title' => '',
        ], $atts, 'nios_dashboard');

        $state = strtoupper(trim((string)$atts['state']));
        if (!nios_valid_state($state)) {
            $state = 'RECEIVING';
        }

        $title = trim((string)$atts['title']);
        if ($title === '') {
            $title = $state . ' Dashboard';
        }

        wp_enqueue_style(
            'nios-dashboard-style',
            NIOS_ASSETS_URL . 'nios-dashboard.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'nios-dashboard-script',
            NIOS_ASSETS_URL . 'nios-dashboard.js',
            [],
            '1.0.0',
            true
        );

        wp_add_inline_script(
            'nios-dashboard-script',
            'window.NIOS_CONFIG = ' . wp_json_encode([
                'dashboardStateUrl' => rest_url('nios/v1/dashboard/' . strtolower($state)),
                'actionUrl'         => rest_url('nios/v1/action-complete'),
                'apiKey'            => nios_api_key(),
                'refreshMs'         => 5000,
                'state'             => $state,
                'title'             => $title,
            ]) . ';',
            'before'
        );

        ob_start();
        ?>
        <div id="nios-app" data-state="<?php echo esc_attr($state); ?>">
            <div class="nios-shell">
                <div class="nios-topbar">
                    <div>
                        <div class="nios-kicker">NIOS</div>
                        <h1 class="nios-title"><?php echo esc_html($title); ?></h1>
                    </div>
                    <div id="nios-last-updated" class="nios-last-updated">Connecting...</div>
                </div>

                <div id="nios-error" class="nios-error hidden"></div>
                <div id="nios-grid" class="nios-grid">
                    <div class="nios-loading">Loading orders...</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('nios_dashboard', 'nios_render_dashboard_shortcode');