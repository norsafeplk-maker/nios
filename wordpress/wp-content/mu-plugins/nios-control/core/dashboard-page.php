<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================
 * SHORTCODE
 * ============================================
 */
function nios_render_dashboard_shortcode($atts) {
    $atts = shortcode_atts([
        'state' => 'RECEIVING',
        'title' => '',
    ], $atts, 'nios_dashboard');

    $state = strtoupper(trim((string)$atts['state']));
    if (!in_array($state, nios_primary_states(), true)) {
        $state = 'RECEIVING';
    }

    $title = trim((string)$atts['title']);
    if ($title === '') {
        $title = $state . ' Dashboard';
    }

    wp_enqueue_style(
        'nios-dashboard-style',
        NIOS_KERNEL_URL . '/assets/nios-dashboard.css',
        [],
        '2.0.0'
    );

    wp_enqueue_script(
        'nios-dashboard-script',
        NIOS_KERNEL_URL . '/assets/nios-dashboard.js',
        [],
        '2.0.0',
        true
    );

    wp_localize_script('nios-dashboard-script', 'NIOS_CONFIG', [
        'dashboardStateUrl' => rest_url('nios/v1/dashboard/' . strtolower($state)),
        'actionUrl'         => rest_url('nios/v1/action-complete'),
        'apiKey'            => nios_api_key(),
        'state'             => $state,
        'title'             => $title,
        'refreshMs'         => 5000,
    ]);

    ob_start();
    ?>
    <div id="nios-app" data-state="<?php echo esc_attr($state); ?>">
        <div class="nios-shell">
            <div class="nios-topbar">
                <div>
                    <div class="nios-kicker">NIOS</div>
                    <h1 class="nios-title"><?php echo esc_html($title); ?></h1>
                </div>
                <div class="nios-status-wrap">
                    <div id="nios-last-updated" class="nios-last-updated">Connecting...</div>
                </div>
            </div>

            <div id="nios-error" class="nios-error hidden"></div>
            <div id="nios-grid" class="nios-grid"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('nios_dashboard', 'nios_render_dashboard_shortcode');

/**
 * ============================================
 * AUTO PAGE GENERATOR
 * ============================================
 */
function nios_dashboard_page_map() {
    return [
        'RECEIVING'   => ['title' => 'Receiving',   'slug' => 'receiving'],
        'QC'          => ['title' => 'QC',          'slug' => 'qc'],
        'EMBROIDERY'  => ['title' => 'Embroidery',  'slug' => 'embroidery'],
        'PRINTING'    => ['title' => 'Printing',    'slug' => 'printing'],
        'SUBLIMATION' => ['title' => 'Sublimation', 'slug' => 'sublimation'],
        'ADJUSTMENTS' => ['title' => 'Adjustments', 'slug' => 'adjustments'],
        'DISPATCH'    => ['title' => 'Dispatch',    'slug' => 'dispatch'],
        'COMPLETE'    => ['title' => 'Complete',    'slug' => 'complete'],
        'EXCEPTION'   => ['title' => 'Exception',   'slug' => 'exception'],
    ];
}

function nios_generate_dashboard_pages() {
    if (get_option('nios_dashboard_pages_generated') === 'yes') {
        return;
    }

    foreach (nios_dashboard_page_map() as $state => $page) {
        $existing = get_page_by_path($page['slug'], OBJECT, 'page');
        if ($existing) {
            continue;
        }

        wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $page['title'],
            'post_name'    => $page['slug'],
            'post_content' => '[nios_dashboard state="' . $state . '" title="' . $page['title'] . ' Dashboard"]',
        ]);
    }

    update_option('nios_dashboard_pages_generated', 'yes', false);
}

add_action('init', 'nios_generate_dashboard_pages');