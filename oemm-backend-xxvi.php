<?php
/**
 * Plugin Name: ÖMM Backend XXVI
 * Plugin URI:  https://mopedmarathon.at
 * Description: Login → HA-Gate → Dashboard. Schönes blaues Dashboard mit echten WooCommerce-Daten. PDF in Downloads.
 * Version:     1.4.0
 * Author:      Ötztaler Moped Verein
 * Text Domain: oemm-xxvi
 */

defined( 'ABSPATH' ) || exit;

define( 'OEMM_XXVI_VERSION', '1.4.0' );
define( 'OEMM_XXVI_GITHUB_REPO', 'whiterabbitmediayt-jpg/oemm-backend-xxvi' );
define( 'OEMM_XXVI_PLUGIN_SLUG', 'oemm-backend-xxvi/oemm-backend-xxvi.php' );

/* ---------------------------------------------------------------
   GITHUB AUTO-UPDATER
--------------------------------------------------------------- */
add_filter( 'pre_set_site_transient_update_plugins', 'oemm_xxvi_check_update' );
function oemm_xxvi_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $remote = get_transient( 'oemm_xxvi_github_release' );
    if ( false === $remote ) {
        $response = wp_remote_get(
            'https://api.github.com/repos/' . OEMM_XXVI_GITHUB_REPO . '/releases/latest',
            [ 'headers' => [ 'User-Agent' => 'WordPress/' . get_bloginfo('version') ], 'timeout' => 10 ]
        );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return $transient;
        $remote = json_decode( wp_remote_retrieve_body( $response ) );
        set_transient( 'oemm_xxvi_github_release', $remote, HOUR_IN_SECONDS * 6 );
    }

    if ( ! $remote || empty( $remote->tag_name ) ) return $transient;

    $latest = ltrim( $remote->tag_name, 'v' );
    if ( version_compare( OEMM_XXVI_VERSION, $latest, '<' ) ) {
        $zip_url = 'https://github.com/' . OEMM_XXVI_GITHUB_REPO . '/archive/refs/heads/main.zip';
        $transient->response[ OEMM_XXVI_PLUGIN_SLUG ] = (object) [
            'slug'        => 'oemm-backend-xxvi',
            'plugin'      => OEMM_XXVI_PLUGIN_SLUG,
            'new_version' => $latest,
            'url'         => 'https://github.com/' . OEMM_XXVI_GITHUB_REPO,
            'package'     => $zip_url,
        ];
    }
    return $transient;
}

// Nach Update: Plugin-Ordner korrekt umbenennen
add_filter( 'upgrader_source_selection', 'oemm_xxvi_fix_update_folder', 10, 4 );
function oemm_xxvi_fix_update_folder( $source, $remote_source, $upgrader, $args ) {
    if ( ! isset( $args['plugin'] ) || $args['plugin'] !== OEMM_XXVI_PLUGIN_SLUG ) return $source;
    global $wp_filesystem;
    $new = trailingslashit( $remote_source ) . 'oemm-backend-xxvi/';
    if ( $source !== $new ) {
        $wp_filesystem->move( $source, $new );
        return $new;
    }
    return $source;
}

// Update-Cache leeren wenn Plugin gespeichert wird
add_action( 'upgrader_process_complete', function() {
    delete_transient( 'oemm_xxvi_github_release' );
}, 10, 0 );
define( 'OEMM_XXVI_PATH',    plugin_dir_path( __FILE__ ) );
define( 'OEMM_XXVI_URL',     plugin_dir_url( __FILE__ ) );
define( 'OEMM_XXVI_TABLE',   'oemm_xxvi_agreements' );

/* ---------------------------------------------------------------
   ACTIVATION
--------------------------------------------------------------- */
register_activation_hook( __FILE__, 'oemm_xxvi_activate' );
function oemm_xxvi_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . OEMM_XXVI_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id       BIGINT UNSIGNED NOT NULL,
        fullname      VARCHAR(200) NOT NULL,
        username      VARCHAR(100) NOT NULL,
        signed_at     DATETIME NOT NULL,
        signed_at_ts  VARCHAR(60) NOT NULL DEFAULT '',
        signature_png LONGTEXT NOT NULL,
        ip_address    VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    oemm_xxvi_add_endpoints();
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ---------------------------------------------------------------
   HELPERS
--------------------------------------------------------------- */
function oemm_xxvi_has_signed( $user_id ) {
    global $wpdb;
    $table = $wpdb->prefix . OEMM_XXVI_TABLE;
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id
    ) );
}

function oemm_xxvi_is_admin_user( $user ) {
    return user_can( $user, 'manage_options' );
}

/* ---------------------------------------------------------------
   ENDPOINTS registrieren
--------------------------------------------------------------- */
add_action( 'init', 'oemm_xxvi_add_endpoints' );
function oemm_xxvi_add_endpoints() {
    add_rewrite_endpoint( 'haftungsausschluss', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-dashboard',      EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-bestellungen',   EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-downloads',      EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-adresse',        EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-kontodetails',   EP_ROOT | EP_PAGES );
}

/* ---------------------------------------------------------------
   GATE — nach Login weiterleiten wenn HA noch nicht unterschrieben
--------------------------------------------------------------- */
add_action( 'template_redirect', 'oemm_xxvi_gate', 5 );
function oemm_xxvi_gate() {
    if ( ! is_user_logged_in() ) return;
    if ( is_admin() )            return;

    $user = wp_get_current_user();
    if ( oemm_xxvi_is_admin_user( $user ) ) return;
    if ( oemm_xxvi_has_signed( $user->ID ) ) return;

    // Endlosschleife verhindern
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $uri, 'haftungsausschluss' ) !== false ) return;
    if ( strpos( $uri, 'wp-json'            ) !== false ) return;
    if ( strpos( $uri, 'wp-admin'           ) !== false ) return;
    if ( strpos( $uri, 'wp-cron'            ) !== false ) return;

    // Nur auf Mein-Konto-Seiten weiterleiten
    if ( is_account_page() ) {
        wp_safe_redirect( wc_get_account_endpoint_url( 'haftungsausschluss' ) );
        exit;
    }
}

/* ---------------------------------------------------------------
   WOOCOMMERCE MENÜ — Standard-Einträge ersetzen
--------------------------------------------------------------- */
add_filter( 'woocommerce_account_menu_items', 'oemm_xxvi_menu_items', 99 );
function oemm_xxvi_menu_items( $items ) {
    $user = wp_get_current_user();
    if ( oemm_xxvi_is_admin_user( $user ) ) return $items;

    // Komplettes Menü durch ÖMM-Menü ersetzen
    return [
        'omm-dashboard'      => '⊞ Dashboard',
        'omm-bestellungen'   => '📦 Bestellungen',
        'omm-downloads'      => '⬇ Downloads',
        'omm-adresse'        => '📍 Adresse',
        'omm-kontodetails'   => '⚙ Kontodetails',
        'customer-logout'    => '→ Abmelden',
    ];
}

/* ---------------------------------------------------------------
   ENDPOINT CALLBACKS
--------------------------------------------------------------- */
add_action( 'woocommerce_account_haftungsausschluss_endpoint', 'oemm_xxvi_page_ha' );
function oemm_xxvi_page_ha() {
    $user     = wp_get_current_user();
    $fullname = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
    $username = $user->user_login;
    $year     = '2026';
    if ( oemm_xxvi_has_signed( $user->ID ) ) {
        wp_safe_redirect( wc_get_account_endpoint_url( 'omm-dashboard' ) );
        exit;
    }
    include OEMM_XXVI_PATH . 'views/haftungsausschluss.php';
}

add_action( 'woocommerce_account_omm-dashboard_endpoint', 'oemm_xxvi_page_dashboard' );
function oemm_xxvi_page_dashboard() {
    $user = wp_get_current_user();
    if ( ! oemm_xxvi_is_admin_user( $user ) && ! oemm_xxvi_has_signed( $user->ID ) ) {
        wp_safe_redirect( wc_get_account_endpoint_url( 'haftungsausschluss' ) );
        exit;
    }
    include OEMM_XXVI_PATH . 'views/dashboard.php';
}

add_action( 'woocommerce_account_omm-bestellungen_endpoint', 'oemm_xxvi_page_bestellungen' );
function oemm_xxvi_page_bestellungen() {
    include OEMM_XXVI_PATH . 'views/bestellungen.php';
}

add_action( 'woocommerce_account_omm-downloads_endpoint', 'oemm_xxvi_page_downloads' );
function oemm_xxvi_page_downloads() {
    include OEMM_XXVI_PATH . 'views/downloads.php';
}

add_action( 'woocommerce_account_omm-adresse_endpoint', 'oemm_xxvi_page_adresse' );
function oemm_xxvi_page_adresse() {
    include OEMM_XXVI_PATH . 'views/adresse.php';
}

add_action( 'woocommerce_account_omm-kontodetails_endpoint', 'oemm_xxvi_page_kontodetails' );
function oemm_xxvi_page_kontodetails() {
    include OEMM_XXVI_PATH . 'views/kontodetails.php';
}

// Dashboard als Standard-Landingpage nach Login
add_filter( 'woocommerce_login_redirect', 'oemm_xxvi_login_redirect', 10, 2 );
function oemm_xxvi_login_redirect( $redirect, $user ) {
    if ( oemm_xxvi_is_admin_user( $user ) ) return $redirect;
    if ( ! oemm_xxvi_has_signed( $user->ID ) ) {
        return wc_get_account_endpoint_url( 'haftungsausschluss' );
    }
    return wc_get_account_endpoint_url( 'omm-dashboard' );
}

// /my-account/ direkt → omm-dashboard weiterleiten (kein Standard-Dashboard)
add_action( 'template_redirect', 'oemm_xxvi_redirect_dashboard', 4 );
function oemm_xxvi_redirect_dashboard() {
    if ( ! is_user_logged_in() ) return;
    if ( is_admin() ) return;
    $user = wp_get_current_user();
    if ( oemm_xxvi_is_admin_user( $user ) ) return;

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Endlosschleife verhindern
    if ( strpos( $uri, 'omm-dashboard' )      !== false ) return;
    if ( strpos( $uri, 'haftungsausschluss' ) !== false ) return;
    if ( strpos( $uri, 'omm-bestellungen' )   !== false ) return;
    if ( strpos( $uri, 'omm-downloads' )      !== false ) return;
    if ( strpos( $uri, 'omm-adresse' )        !== false ) return;
    if ( strpos( $uri, 'omm-kontodetails' )   !== false ) return;

    // Nur auf exaktem /my-account/ (ohne Endpoint)
    if ( is_account_page() && ! is_wc_endpoint_url() ) {
        if ( ! oemm_xxvi_has_signed( $user->ID ) ) {
            wp_safe_redirect( wc_get_account_endpoint_url( 'haftungsausschluss' ) );
        } else {
            wp_safe_redirect( wc_get_account_endpoint_url( 'omm-dashboard' ) );
        }
        exit;
    }
}

/* ---------------------------------------------------------------
   REST API
--------------------------------------------------------------- */
add_action( 'rest_api_init', 'oemm_xxvi_register_routes' );
function oemm_xxvi_register_routes() {
    register_rest_route( 'oemm-xxvi/v1', '/sign', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_sign',
        'permission_callback' => fn() => is_user_logged_in(),
    ] );
}

function oemm_xxvi_rest_sign( WP_REST_Request $req ) {
    global $wpdb;
    $user  = wp_get_current_user();
    $table = $wpdb->prefix . OEMM_XXVI_TABLE;

    if ( oemm_xxvi_has_signed( $user->ID ) ) {
        return rest_ensure_response( [ 'success' => true, 'already_signed' => true,
            'redirect' => wc_get_account_endpoint_url( 'omm-dashboard' ) ] );
    }

    $sig = $req->get_param( 'signature_png' );
    if ( empty( $sig ) || strpos( $sig, 'data:image/png;base64,' ) !== 0 ) {
        return new WP_Error( 'invalid_sig', 'Ungültige Unterschrift.', [ 'status' => 400 ] );
    }

    $fullname  = sanitize_text_field( $req->get_param('fullname') )  ?: trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
    $username  = sanitize_text_field( $req->get_param('username') )  ?: $user->user_login;
    $signed_ts = sanitize_text_field( $req->get_param('signed_ts') ) ?: current_time( 'd.m.Y — H:i:s \U\h\r' );

    $inserted = $wpdb->insert( $table, [
        'user_id'       => $user->ID,
        'fullname'      => $fullname,
        'username'      => $username,
        'signed_at'     => current_time( 'mysql' ),
        'signed_at_ts'  => $signed_ts,
        'signature_png' => $sig,
        'ip_address'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
    ] );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Fehler beim Speichern.', [ 'status' => 500 ] );
    }

    // HTML-Dokument als Download speichern
    oemm_xxvi_save_agreement( $user->ID, $fullname, $username, $signed_ts, $sig );

    return rest_ensure_response( [
        'success'  => true,
        'redirect' => wc_get_account_endpoint_url( 'omm-dashboard' ),
    ] );
}

/* ---------------------------------------------------------------
   AGREEMENT DOKUMENT SPEICHERN + DOWNLOAD ANLEGEN
--------------------------------------------------------------- */
function oemm_xxvi_save_agreement( $user_id, $fullname, $username, $signed_ts, $sig_png ) {
    $upload   = wp_upload_dir();
    $dir      = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '.htaccess', 'deny from all' . PHP_EOL );
        file_put_contents( $dir . 'index.php', '<?php // silence' . PHP_EOL );
    }
    $filename = 'ha-' . $user_id . '.html';
    $filepath = $dir . $filename;
    file_put_contents( $filepath, oemm_xxvi_agreement_html( $fullname, $username, $signed_ts, $sig_png ) );

    // WC Download Permission anlegen
    $dl_key = 'oemm_ha_' . $user_id;
    $dl_url = add_query_arg( [
        'oemm_dl'  => 1,
        'uid'      => $user_id,
        'token'    => hash( 'sha256', AUTH_KEY . $user_id . $filename ),
    ], home_url( '/' ) );

    // Als user meta speichern (einfacher als WC-Product)
    update_user_meta( $user_id, '_oemm_ha_dl_url', $dl_url );
    update_user_meta( $user_id, '_oemm_ha_dl_file', $filepath );
    update_user_meta( $user_id, '_oemm_ha_signed_ts', $signed_ts );
}

// Sicherer Download-Handler
add_action( 'init', 'oemm_xxvi_download_handler', 1 );
function oemm_xxvi_download_handler() {
    if ( empty( $_GET['oemm_dl'] ) ) return;
    if ( ! is_user_logged_in() ) { wp_die( 'Bitte einloggen.' ); }

    $user_id  = absint( $_GET['uid'] ?? 0 );
    $token    = sanitize_text_field( $_GET['token'] ?? '' );

    if ( get_current_user_id() !== $user_id ) wp_die( 'Kein Zugriff.' );

    $filepath = get_user_meta( $user_id, '_oemm_ha_dl_file', true );
    $expected = hash( 'sha256', AUTH_KEY . $user_id . basename( $filepath ) );

    if ( ! hash_equals( $expected, $token ) ) wp_die( 'Ungültiger Token.' );
    if ( ! file_exists( $filepath ) )         wp_die( 'Datei nicht gefunden.' );

    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="Haftungsausschluss_OeMM_2026.html"' );
    header( 'Content-Length: ' . filesize( $filepath ) );
    readfile( $filepath );
    exit;
}

function oemm_xxvi_agreement_html( $fullname, $username, $signed_ts, $sig_png ) {
    return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<title>Haftungsausschluss ÖMM 2026</title>
<style>body{font-family:Arial,sans-serif;font-size:13px;line-height:1.7;color:#222;max-width:720px;margin:40px auto;padding:0 24px}
h1{font-size:20px;color:#0f3460;border-bottom:2px solid #0f3460;padding-bottom:8px;margin-bottom:20px}
h2{font-size:13px;text-transform:uppercase;color:#555;margin:20px 0 6px}
.meta{background:#f5f5f5;border-radius:8px;padding:14px 18px;margin:20px 0}
.sig-box{border:1px solid #ccc;border-radius:8px;padding:12px;margin:20px 0;text-align:center}
.sig-box img{max-width:100%;height:120px}
.warn{background:#fff8e1;border:1px solid #f0c040;border-radius:6px;padding:10px 14px;margin:14px 0}
.footer{margin-top:40px;padding-top:16px;border-top:1px solid #eee;font-size:11px;color:#aaa}
@media print{body{margin:0}}</style></head><body>
<h1>AGB &amp; Haftungsausschluss — Ötztaler Moped Marathon 2026</h1>
<div class="meta">
<strong>Teilnehmer:</strong> ' . esc_html($fullname) . ' (@' . esc_html($username) . ')<br>
<strong>Veranstalter:</strong> Ötztaler Moped Verein, Sölden, Tirol<br>
<strong>Unterzeichnet:</strong> ' . esc_html($signed_ts) . '
</div>
<p><strong>' . esc_html($fullname) . '</strong> erklärt seinen Beitritt zum Ötztaler Moped Verein als ordentliches Mitglied ohne Stimmrecht für die Dauer bis Ende September 2026.</p>
<h2>Teilnahmebedingungen &amp; Haftungsausschluss</h2>
<p>Die Teilnahme ist nur bei Volljährigkeit gestattet. Der Teilnehmer bestätigt ausdrücklich, dass für Verletzungen und Schäden jeglicher Art dem ÖMV keinerlei Schuld zuweisbar ist. Der Teilnehmer ist im Besitz einer gültigen Haftpflicht- und Unfallversicherung.</p>
<div class="warn">⚠️ Der Teilnehmer bestätigt ausdrücklich, bei der verbindlichen Fahrerbesprechung persönlich anwesend zu sein.</div>
<p>Der Teilnehmer verpflichtet sich, weder unter Alkohol- noch Drogeneinfluss zu fahren. Bei Verdacht ist der ÖMV berechtigt, den Teilnehmer ohne Rückerstattung auszuschließen.</p>
<h2>Bild- &amp; Tonrechte</h2>
<p>Der ÖMV ist berechtigt, Fotos, Videos und Audio-Aufnahmen des ÖMM für alle Zwecke in allen Medien weltweit zu verwenden.</p>
<h2>Rückgaberecht &amp; Streitbeilegung</h2>
<p>Rückgaberecht laut Fernabsatzgesetz: 14 Tage. Gerichtsstand: Innsbruck.</p>
<div class="sig-box">
<img src="' . esc_attr($sig_png) . '" alt="Unterschrift"><br>
<small>Digitale Unterschrift von ' . esc_html($fullname) . '</small>
</div>
<div class="footer">Ötztaler Moped Verein — ÖMM XXVI 2026 | Elektronisch unterzeichnet: ' . esc_html($signed_ts) . '</div>
</body></html>';
}

/* ---------------------------------------------------------------
   FULL-WIDTH TEMPLATE — WC-Wrapper entfernen auf ÖMM-Seiten
--------------------------------------------------------------- */
add_filter( 'template_include', 'oemm_xxvi_full_width_template', 99 );
function oemm_xxvi_full_width_template( $template ) {
    if ( ! is_account_page() ) return $template;
    if ( ! is_user_logged_in() ) return $template;
    $user = wp_get_current_user();
    if ( oemm_xxvi_is_admin_user( $user ) ) return $template;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Haftungsausschluss: eigenes Standalone-Template (komplettes HTML, keine Sidebar)
    if ( strpos( $uri, 'haftungsausschluss' ) !== false ) {
        return OEMM_XXVI_PATH . 'views/ha-standalone.php';
    }
    // Alle anderen ÖMM-Seiten: Dashboard-Layout mit Sidebar
    $oemm_pages = [ 'omm-dashboard', 'omm-bestellungen', 'omm-downloads', 'omm-adresse', 'omm-kontodetails' ];
    foreach ( $oemm_pages as $page ) {
        if ( strpos( $uri, $page ) !== false ) {
            return OEMM_XXVI_PATH . 'views/full-width.php';
        }
    }
    return $template;
}

// Logout-Link fixen
add_filter( 'woocommerce_get_endpoint_url', 'oemm_xxvi_fix_logout', 10, 4 );
function oemm_xxvi_fix_logout( $url, $endpoint, $value, $permalink ) {
    if ( $endpoint !== 'customer-logout' ) return $url;
    return wp_logout_url( home_url() );
}

/* ---------------------------------------------------------------
   ASSETS
--------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'oemm_xxvi_enqueue' );
function oemm_xxvi_enqueue() {
    if ( ! is_account_page() ) return;
    wp_enqueue_script( 'oemm-xxvi', OEMM_XXVI_URL . 'assets/oemm-xxvi.js', [], OEMM_XXVI_VERSION, true );
    $user = wp_get_current_user();
    wp_localize_script( 'oemm-xxvi', 'OEMM_XXVI', [
        'restUrl'  => rest_url( 'oemm-xxvi/v1/sign' ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'dashUrl'  => wc_get_account_endpoint_url( 'omm-dashboard' ),
        'fullname' => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
        'username' => $user->user_login,
    ] );
}

/* ---------------------------------------------------------------
   WC ACCOUNT PAGE TITLE override
--------------------------------------------------------------- */
add_filter( 'the_title', 'oemm_xxvi_page_title', 10, 2 );
function oemm_xxvi_page_title( $title, $id ) {
    if ( ! is_account_page() ) return $title;
    global $wp_query;
    if ( isset( $wp_query->query_vars['omm-dashboard'] ) )    return 'Mein ÖMV Account';
    if ( isset( $wp_query->query_vars['haftungsausschluss'] ) ) return 'AGB & Haftungsausschluss';
    if ( isset( $wp_query->query_vars['omm-bestellungen'] ) )  return 'Bestellungen';
    if ( isset( $wp_query->query_vars['omm-downloads'] ) )     return 'Downloads';
    if ( isset( $wp_query->query_vars['omm-adresse'] ) )       return 'Adresse';
    if ( isset( $wp_query->query_vars['omm-kontodetails'] ) )  return 'Kontodetails';
    return $title;
}

// Sicherheitsnetz: Endpoints + Flush bei jedem WC-Init bis Rewrite Rules stimmen
add_action( 'woocommerce_init', function() {
    $flushed = get_option( 'oemm_xxvi_flushed_v', '0' );
    if ( $flushed !== OEMM_XXVI_VERSION ) {
        oemm_xxvi_add_endpoints();
        flush_rewrite_rules( true );
        update_option( 'oemm_xxvi_flushed_v', OEMM_XXVI_VERSION );
    }
}, 20 );
