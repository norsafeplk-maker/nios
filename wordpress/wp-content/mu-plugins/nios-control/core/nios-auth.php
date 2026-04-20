<?php
if (!defined('ABSPATH')) exit;

/**
 * NIOS Auth / Access Control
 *
 * This file handles:
 * - auto-create the dashboard page
 * - redirect mapped NIOS users to dashboard after login
 * - protect dashboard page from logged-out access
 */

class NIOS_Auth {

    const DASHBOARD_SLUG  = 'nios-dashboard';
    const DASHBOARD_TITLE = 'NIOS Dashboard';

    public static function init() {
        add_action('init', [__CLASS__, 'ensure_dashboard_page']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);
        add_action('template_redirect', [__CLASS__, 'protect_dashboard_page']);
    }

    public static function ensure_dashboard_page() {
        $page = get_page_by_path(self::DASHBOARD_SLUG);

        if ($page) return;

        wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => self::DASHBOARD_TITLE,
            'post_name'    => self::DASHBOARD_SLUG,
            'post_content' => '[nios_dashboard]',
        ]);
    }

    public static function login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!($user instanceof WP_User)) {
            return $redirect_to;
        }

        $email = strtolower(trim((string) $user->user_email));

        $nios_users = [
            'receiving@norsafe.co.za',
            'qc@norsafe.co.za',
            'embroidery@norsafe.co.za',
            'printing@norsafe.co.za',
            'sublimation@norsafe.co.za',
            'adjustments@norsafe.co.za',
            'dispatch@norsafe.co.za',
            'liaison@norsafe.co.za',
            'thys@norsafe.co.za',
            'inge@norsafe.co.za',
        ];

        if (in_array($email, $nios_users, true)) {
            return home_url('/' . self::DASHBOARD_SLUG . '/');
        }

        return $redirect_to;
    }

    public static function protect_dashboard_page() {
        if (!is_page(self::DASHBOARD_SLUG)) {
            return;
        }

        if (!is_user_logged_in()) {
            auth_redirect();
        }
    }
}

NIOS_Auth::init();