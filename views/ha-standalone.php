<?php
/**
 * Standalone Template für Haftungsausschluss — kein WP-Header/Footer/Sidebar
 */
defined( 'ABSPATH' ) || exit;

// WP braucht wp_head() für Nonces etc.
// Wir geben ein komplett eigenes HTML aus
if ( ! is_user_logged_in() ) {
    wp_redirect( wc_get_page_permalink('myaccount') );
    exit;
}

// Nonce und REST URL für JS verfügbar machen
add_action( 'wp_head', function() {
    echo '<script>var oemm_nonce="' . esc_js( wp_create_nonce('wp_rest') ) . '";var oemm_rest="' . esc_js( rest_url('oemm-xxvi/v1/sign') ) . '";var oemm_dashboard="' . esc_js( wc_get_account_endpoint_url('omm-dashboard') ) . '";</script>';
});

// wp_head und wp_footer werden durch die View selbst aufgerufen
include OEMM_XXVI_PATH . 'views/haftungsausschluss.php';
