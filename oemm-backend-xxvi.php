<?php
/**
 * Plugin Name: ÖMM Backend XXVI
 * Plugin URI:  https://mopedmarathon.at
 * Description: Login → HA-Gate → Dashboard. Schönes blaues Dashboard mit echten WooCommerce-Daten. PDF in Downloads.
 * Version:     2.3.11
 * Author:      Manuel Ribis GmbH
 * Text Domain: oemm-xxvi
 */

defined( 'ABSPATH' ) || exit;

define( 'OEMM_XXVI_VERSION', '2.3.11' );
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

// tFPDF global laden (muss vor Klassendeklaration tFPDF_OMM stehen)
if ( ! defined('FPDF_FONTPATH') ) {
    define( 'FPDF_FONTPATH', OEMM_XXVI_PATH . 'lib/' );
}
if ( ! class_exists('tFPDF') ) {
    require_once OEMM_XXVI_PATH . 'lib/tfpdf.php';
}
define( 'OEMM_XXVI_TABLE',        'oemm_xxvi_agreements' );
define( 'OEMM_XXVI_FOTOS_TABLE',  'oemm_xxvi_fotos' );
define( 'OEMM_XXVI_LIKES_TABLE',  'oemm_xxvi_foto_likes' );

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

    // Fotos-Tabelle
    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $sql_fotos = "CREATE TABLE IF NOT EXISTS {$fotos_table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id         BIGINT UNSIGNED NOT NULL,
        event_year      SMALLINT UNSIGNED NOT NULL DEFAULT 2026,
        filename        VARCHAR(255) NOT NULL,
        filepath        VARCHAR(500) NOT NULL,
        filesize        INT UNSIGNED DEFAULT NULL,
        shot_at         DATETIME DEFAULT NULL,
        uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_public       TINYINT(1) NOT NULL DEFAULT 0,
        public_set_at   DATETIME DEFAULT NULL,
        token_type      VARCHAR(10) DEFAULT NULL COMMENT 'app oder paper',
        upload_ms       INT UNSIGNED DEFAULT NULL COMMENT 'Upload-Dauer in ms',
        PRIMARY KEY (id),
        KEY idx_user_year (user_id, event_year),
        KEY idx_public (is_public, event_year),
        KEY idx_uploaded (uploaded_at)
    ) {$charset};";
    dbDelta( $sql_fotos );

    // Likes-Tabelle
    $likes_table = $wpdb->prefix . OEMM_XXVI_LIKES_TABLE;
    $sql_likes = "CREATE TABLE IF NOT EXISTS {$likes_table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        foto_id         BIGINT UNSIGNED NOT NULL,
        liker_user_id   BIGINT UNSIGNED NOT NULL,
        liked_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_foto_user (foto_id, liker_user_id),
        KEY idx_foto (foto_id),
        KEY idx_liker (liker_user_id)
    ) {$charset};";
    dbDelta( $sql_likes );

    // Foto-Storage Verzeichnis anlegen + sichern
    oemm_xxvi_fotos_init_storage();

    oemm_xxvi_add_endpoints();
    flush_rewrite_rules();
}

/* ---------------------------------------------------------------
   FOTO STORAGE — Pfade, Verzeichnis, .htaccess
--------------------------------------------------------------- */

/**
 * Gibt den Basis-Upload-Pfad für Fotos zurück
 * Struktur: /wp-content/uploads/oemm-fotos/{year}/{user_id}/
 */
function oemm_xxvi_fotos_get_dir( int $user_id, int $year = 0 ): string {
    if ( ! $year ) {
        $year = (int) get_option( 'oemm_event_year', date( 'Y' ) );
    }
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . "oemm-fotos/{$year}/{$user_id}";
}

/**
 * Gibt die öffentliche URL zurück (wird NICHT direkt exposed — nur intern)
 */
function oemm_xxvi_fotos_get_url( int $user_id, int $year = 0 ): string {
    if ( ! $year ) {
        $year = (int) get_option( 'oemm_event_year', date( 'Y' ) );
    }
    $upload = wp_upload_dir();
    return trailingslashit( $upload['baseurl'] ) . "oemm-fotos/{$year}/{$user_id}";
}

/**
 * Verzeichnis für einen User anlegen (inkl. Index-Schutz)
 */
function oemm_xxvi_fotos_ensure_dir( int $user_id, int $year = 0 ): string {
    $dir = oemm_xxvi_fotos_get_dir( $user_id, $year );
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
        // index.php Schutz gegen Directory-Listing
        file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
    }
    return $dir;
}

/**
 * Initialisiert das Root-Storage-Verzeichnis beim Plugin-Aktivieren
 * Schreibt .htaccess: kein Direktzugriff auf Fotos
 */
function oemm_xxvi_fotos_init_storage(): void {
    $upload   = wp_upload_dir();
    $root_dir = trailingslashit( $upload['basedir'] ) . 'oemm-fotos';

    if ( ! is_dir( $root_dir ) ) {
        wp_mkdir_p( $root_dir );
    }

    // .htaccess: Direktzugriff komplett sperren
    $htaccess = $root_dir . '/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        $rules  = "# OeMM Fotos - kein Direktzugriff\n";
        $rules .= "Options -Indexes\n";
        $rules .= "<FilesMatch \".\\+\\.(jpg|jpeg|png|gif|webp)$\">\n";
        $rules .= "    Require all denied\n";
        $rules .= "</FilesMatch>\n";
        file_put_contents( $htaccess, $rules );
    }

    // index.php im Root
    $index = $root_dir . '/index.php';
    if ( ! file_exists( $index ) ) {
        file_put_contents( $index, '<?php // Silence is golden.' );
    }
}

/**
 * Foto sicher ausliefern (eingeloggt + event_year Check)
 * Aufruf: ?oemm_foto=<foto_id>&token=<hash>
 */
add_action( 'init', 'oemm_xxvi_fotos_serve' );
function oemm_xxvi_fotos_serve(): void {
    if ( empty( $_GET['oemm_foto'] ) ) return;

    $foto_id = (int) $_GET['oemm_foto'];
    $token   = sanitize_text_field( $_GET['token'] ?? '' );

    // Eingeloggt?
    if ( ! is_user_logged_in() ) {
        wp_die( 'Bitte einloggen.', 403 );
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $table        = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;

    $foto = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $foto_id
    ) );

    if ( ! $foto ) wp_die( 'Foto nicht gefunden.', 404 );

    // Token-Check (verhindert enumeration: user muss korrekten hash kennen)
    $expected = hash( 'sha256', AUTH_KEY . $foto->id . $foto->user_id );
    if ( ! hash_equals( $expected, $token ) ) wp_die( 'Ungültiger Token.', 403 );

    // Jahres-Isolation: nur Teilnehmer desselben event_year
    $event_year = (int) $foto->event_year;
    $is_same_year_participant = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}oemm_participants WHERE customer_id = %d AND event_year = %d LIMIT 1",
        $current_user->ID, $event_year
    ) );

    // Eigenes Foto ODER anderes Foto (nur public) + selbes Jahr
    $is_owner  = ( (int) $foto->user_id === (int) $current_user->ID );
    $is_public = ( (int) $foto->is_public === 1 );

    if ( ! $is_owner && ( ! $is_public || ! $is_same_year_participant ) ) {
        wp_die( 'Zugriff verweigert.', 403 );
    }

    // Datei ausliefern
    $filepath = ABSPATH . 'wp-content/uploads/' . ltrim( $foto->filepath, '/' );
    if ( ! file_exists( $filepath ) ) wp_die( 'Datei nicht gefunden.', 404 );

    $mime = mime_content_type( $filepath ) ?: 'image/jpeg';
    header( 'Content-Type: ' . $mime );
    header( 'Content-Length: ' . filesize( $filepath ) );
    header( 'Cache-Control: private, max-age=3600' );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $filepath );
    exit;
}


/* ---------------------------------------------------------------
   ZIP DOWNLOAD — alle Fotos eines Users on-demand
--------------------------------------------------------------- */
add_action( 'init', 'oemm_xxvi_fotos_serve_zip' );
function oemm_xxvi_fotos_serve_zip(): void {
    if ( empty( $_GET['oemm_zip'] ) ) return;

    // Eingeloggt?
    if ( ! is_user_logged_in() ) {
        wp_die( 'Bitte einloggen.', 403 );
    }

    $current_user = wp_get_current_user();
    $uid          = (int) ( $_GET['uid']   ?? 0 );
    $year         = (int) ( $_GET['year']  ?? 0 );
    $token        = sanitize_text_field( $_GET['token'] ?? '' );

    // Token + User validieren
    $expected = hash( 'sha256', AUTH_KEY . $uid . $year . 'zip' );
    if ( ! hash_equals( $expected, $token ) || $uid !== (int) $current_user->ID ) {
        wp_die( 'Ungültiger Token.', 403 );
    }

    // ZIP noch nicht verfügbar?
    $zip_date = get_option( 'oemm_zip_available_date', '' );
    if ( ! $zip_date || strtotime( $zip_date ) > time() ) {
        wp_die( 'ZIP noch nicht verfügbar.', 403 );
    }

    global $wpdb;
    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $fotos = $wpdb->get_results( $wpdb->prepare(
        "SELECT filename, filepath FROM {$fotos_table}
         WHERE user_id = %d AND event_year = %d ORDER BY shot_at ASC",
        $uid, $year
    ) );

    if ( empty( $fotos ) ) {
        wp_die( 'Keine Fotos vorhanden.', 404 );
    }

    $upload   = wp_upload_dir();
    $basedir  = trailingslashit( $upload['basedir'] );

    // ZipArchive
    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_die( 'ZIP-Funktion nicht verfügbar.', 500 );
    }

    $zip_tmp = sys_get_temp_dir() . '/oemm_fotos_' . $uid . '_' . $year . '_' . time() . '.zip';
    $zip     = new ZipArchive();

    if ( $zip->open( $zip_tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
        wp_die( 'ZIP konnte nicht erstellt werden.', 500 );
    }

    $added = 0;
    foreach ( $fotos as $foto ) {
        $filepath = $basedir . ltrim( $foto->filepath, '/' );
        if ( file_exists( $filepath ) ) {
            $zip->addFile( $filepath, 'oemm_' . $year . '_' . $foto->filename );
            $added++;
        }
    }
    $zip->close();

    if ( $added === 0 ) {
        @unlink( $zip_tmp );
        wp_die( 'Keine Dateien gefunden.', 404 );
    }

    // Download senden
    $filename = 'oemm_fotos_' . $year . '_' . $uid . '.zip';
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $zip_tmp ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    readfile( $zip_tmp );
    @unlink( $zip_tmp );
    exit;
}

/**
 * Foto-URL generieren (für Frontend — mit Token)
 */
function oemm_xxvi_fotos_get_serve_url( int $foto_id, int $user_id ): string {
    $token = hash( 'sha256', AUTH_KEY . $foto_id . $user_id );
    return add_query_arg( [ 'oemm_foto' => $foto_id, 'token' => $token ], home_url( '/' ) );
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
    add_rewrite_endpoint( 'omm-packliste',      EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-freundebuch',    EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-ergebnisse',     EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-fotos',          EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'omm-album',          EP_ROOT | EP_PAGES );
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
        'omm-packliste'      => '✅ Packliste',
        'omm-freundebuch'    => '👥 Freundebuch',
        'omm-ergebnisse'     => '🏁 Ergebnisse',
        'omm-fotos'          => '📷 Meine Fotos',
        'omm-album'          => '🖼️ Öffentliches Album',
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

// WC Standard view-order Endpoint überschreiben — verhindert den "Anzeigen"-Button und alten Content
add_action( 'init', function() {
    // WC rendert view-order via woocommerce_account_view-order_endpoint
    // Wir entfernen den WC-Default und ersetzen durch leeren Output
    // (unser full-width.php lädt view-order.php direkt via include)
    remove_all_actions( 'woocommerce_account_view-order_endpoint' );
    // Leerer Replacement-Hook damit WC keinen Fehler wirft
    add_action( 'woocommerce_account_view-order_endpoint', '__return_null' );
}, 20 );

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

// NEUE SEITEN: Placeholder-Callbacks (Inhalt kommt in späterer Version)
add_action( 'woocommerce_account_omm-packliste_endpoint',   'oemm_xxvi_page_packliste' );
add_action( 'woocommerce_account_omm-freundebuch_endpoint', 'oemm_xxvi_page_freundebuch' );
add_action( 'woocommerce_account_omm-ergebnisse_endpoint',  'oemm_xxvi_page_ergebnisse' );
add_action( 'woocommerce_account_omm-fotos_endpoint',       'oemm_xxvi_page_fotos' );

function oemm_xxvi_placeholder_page( $title, $icon, $desc ) {
    echo '<div style="text-align:center;padding:60px 20px;">';
    echo '<span style="font-size:48px;display:block;margin-bottom:16px">' . $icon . '</span>';
    echo '<h2 style="font-family:Oswald,sans-serif;font-size:22px;color:#fff;margin-bottom:8px">' . esc_html($title) . '</h2>';
    echo '<p style="color:rgba(255,255,255,.4);font-size:14px">' . esc_html($desc) . '</p>';
    echo '</div>';
}
function oemm_xxvi_page_packliste()   { include OEMM_XXVI_PATH . 'views/packliste.php'; }
function oemm_xxvi_page_freundebuch() { oemm_xxvi_placeholder_page('Freundebuch','👥','Wer fährt noch mit? Das Freundebuch kommt bald.'); }
function oemm_xxvi_page_ergebnisse()  { oemm_xxvi_placeholder_page('Ergebnisse','🏁','Die Ergebnisliste wird nach dem Event veröffentlicht.'); }
function oemm_xxvi_page_fotos() {
    include OEMM_XXVI_PATH . 'views/fotos.php';
}

add_action( 'woocommerce_account_omm-album_endpoint', 'oemm_xxvi_page_album' );
function oemm_xxvi_page_album() {
    include OEMM_XXVI_PATH . 'views/album.php';
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
    if ( strpos( $uri, 'omm-packliste' )       !== false ) return;
    if ( strpos( $uri, 'omm-freundebuch' )     !== false ) return;
    if ( strpos( $uri, 'omm-ergebnisse' )      !== false ) return;
    if ( strpos( $uri, 'omm-fotos' )           !== false ) return;
    if ( strpos( $uri, 'omm-album' )           !== false ) return;

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

/* ---------------------------------------------------------------
   TEMPORÄRER DEBUG ENDPOINT — NACH FIX ENTFERNEN
--------------------------------------------------------------- */
add_action( 'rest_api_init', function() {
    register_rest_route( 'oemm-xxvi/v1', '/debug-album', [
        'methods'             => 'GET',
        'callback'            => 'oemm_xxvi_debug_album',
        'permission_callback' => function() { return current_user_can( 'manage_options' ); },
    ] );
} );
function oemm_xxvi_debug_album() {
    global $wpdb;
    $event_year   = (int) get_option( 'oemm_event_year', date('Y') );
    $album_active = get_option( 'oemm_album_active', 0 );
    $fotos_table  = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $likes_table  = $wpdb->prefix . OEMM_XXVI_LIKES_TABLE;

    $fotos_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fotos_table ) ) === $fotos_table );
    $likes_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $likes_table ) ) === $likes_table );

    $all_fotos = $fotos_exists ? $wpdb->get_results(
        "SELECT id, user_id, is_public, event_year, filepath FROM {$fotos_table} ORDER BY id"
    ) : [];

    $public_fotos = $fotos_exists ? $wpdb->get_results( $wpdb->prepare(
        "SELECT id, user_id, filepath, event_year FROM {$fotos_table} WHERE is_public=1 AND event_year=%d",
        $event_year
    ) ) : [];

    $serve_test = [];
    foreach ( $public_fotos as $f ) {
        $token    = hash( 'sha256', AUTH_KEY . $f->id . $f->user_id );
        $url      = home_url( '/' ) . '?oemm_foto=' . $f->id . '&token=' . $token;
        $filepath = ABSPATH . 'wp-content/uploads/' . ltrim( $f->filepath, '/' );
        $serve_test[] = [
            'id'          => $f->id,
            'filepath'    => $f->filepath,
            'file_exists' => file_exists( $filepath ),
            'serve_url'   => $url,
        ];
    }

    return [
        'event_year'        => $event_year,
        'album_active'      => $album_active,
        'fotos_table'       => $fotos_table,
        'likes_table'       => $likes_table,
        'fotos_table_exists'=> $fotos_exists,
        'likes_table_exists'=> $likes_exists,
        'all_fotos_count'   => count( $all_fotos ),
        'all_fotos'         => $all_fotos,
        'public_fotos_count'=> count( $public_fotos ),
        'serve_test'        => $serve_test,
        'wp_prefix'         => $wpdb->prefix,
    ];
}

add_action( 'rest_api_init', 'oemm_xxvi_register_routes' );
function oemm_xxvi_register_routes() {
    register_rest_route( 'oemm-xxvi/v1', '/sign', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_sign',
        'permission_callback' => fn() => is_user_logged_in(),
    ] );

    // Foto-Upload vom Raspberry Pi
    register_rest_route( 'oemm-xxvi/v1', '/foto/upload', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_foto_upload',
        'permission_callback' => 'oemm_xxvi_rest_check_foto_key',
    ] );

    // Foto Public/Privat Toggle (eingeloggte User)
    register_rest_route( 'oemm-xxvi/v1', '/foto/toggle-public', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_foto_toggle_public',
        'permission_callback' => fn() => is_user_logged_in(),
    ] );

    // Like/Unlike (eingeloggte User)
    register_rest_route( 'oemm-xxvi/v1', '/foto/like', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_foto_like',
        'permission_callback' => fn() => is_user_logged_in(),
    ] );
}

/* ---------------------------------------------------------------
   FOTO REST API — Upload, Toggle, Like
--------------------------------------------------------------- */

/**
 * Permission: prüft X-OEMM-Foto-Key Header
 */
function oemm_xxvi_rest_check_foto_key( WP_REST_Request $req ): bool {
    $key    = $req->get_header( 'X-OEMM-Foto-Key' );
    $stored = get_option( 'oemm_foto_api_key', '' );
    if ( ! $stored || ! $key ) return false;
    return hash_equals( (string) $stored, (string) $key );
}

/**
 * POST /wp-json/oemm-xxvi/v1/foto/upload
 *
 * Erwartet: multipart/form-data
 *   token    = QR-Token des Teilnehmers (app oder paper)
 *   foto     = JPEG-Datei
 *   shot_at  = optionaler ISO-Zeitstempel (wann Foto gemacht)
 *
 * Header: X-OEMM-Foto-Key: <api_key>
 */
function oemm_xxvi_rest_foto_upload( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;

    $start_ms = round( microtime( true ) * 1000 );

    // --- Token auflösen ---
    $token = sanitize_text_field( $req->get_param( 'token' ) ?? '' );
    if ( strlen( $token ) < 10 ) {
        return new WP_Error( 'invalid_token', 'Token fehlt oder zu kurz.', [ 'status' => 400 ] );
    }

    $parts_table = $wpdb->prefix . 'oemm_participants';
    $event_year  = (int) get_option( 'oemm_event_year', (int) date( 'Y' ) );

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT customer_id, token_app, token_paper FROM {$parts_table}
         WHERE (token_app = %s OR token_paper = %s) AND event_year = %d LIMIT 1",
        $token, $token, $event_year
    ) );

    if ( ! $row ) {
        return new WP_Error( 'token_not_found', 'Token nicht gefunden.', [ 'status' => 404 ] );
    }

    $user_id    = (int) $row->customer_id;
    $token_type = ( $row->token_app === $token ) ? 'app' : 'paper';

    // --- Rate-Limit: max 1 Upload alle 5 Sekunden pro User ---
    $rate_key  = 'oemm_foto_rate_' . $user_id;
    $last_upload = get_transient( $rate_key );
    if ( $last_upload ) {
        return new WP_Error( 'rate_limit', 'Zu schnell. Bitte 5 Sekunden warten.', [ 'status' => 429 ] );
    }
    set_transient( $rate_key, 1, 5 );

    // --- Datei prüfen ---
    $files = $req->get_file_params();
    if ( empty( $files['foto'] ) || $files['foto']['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'no_file', 'Keine Datei oder Upload-Fehler.', [ 'status' => 400 ] );
    }

    $file     = $files['foto'];
    $tmp_path = $file['tmp_name'];
    $filesize = (int) $file['size'];

    // Max 25 MB
    if ( $filesize > 25 * 1024 * 1024 ) {
        return new WP_Error( 'file_too_large', 'Datei zu groß (max 25 MB).', [ 'status' => 413 ] );
    }

    // MIME prüfen (nur JPEG/PNG akzeptiert)
    $mime = mime_content_type( $tmp_path );
    $allowed_mimes = [ 'image/jpeg', 'image/jpg', 'image/png' ];
    if ( ! in_array( $mime, $allowed_mimes, true ) ) {
        return new WP_Error( 'invalid_mime', 'Nur JPEG/PNG erlaubt.', [ 'status' => 415 ] );
    }

    $ext = ( $mime === 'image/png' ) ? 'png' : 'jpg';

    // --- Verzeichnis anlegen ---
    $dir = oemm_xxvi_fotos_ensure_dir( $user_id, $event_year );

    // Eindeutiger Dateiname: timestamp + random
    $filename = date( 'YmdHis' ) . '_' . wp_generate_password( 8, false ) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    // Relativer Pfad (ohne ABSPATH/wp-content/uploads/ Prefix)
    $upload      = wp_upload_dir();
    $rel_path    = str_replace( trailingslashit( $upload['basedir'] ), '', $dest );

    // --- Datei verschieben ---
    if ( ! move_uploaded_file( $tmp_path, $dest ) ) {
        return new WP_Error( 'move_failed', 'Datei konnte nicht gespeichert werden.', [ 'status' => 500 ] );
    }

    // --- shot_at parsen ---
    $shot_at_raw = sanitize_text_field( $req->get_param( 'shot_at' ) ?? '' );
    $shot_at     = null;
    if ( $shot_at_raw ) {
        $ts = strtotime( $shot_at_raw );
        if ( $ts ) $shot_at = date( 'Y-m-d H:i:s', $ts );
    }
    if ( ! $shot_at ) $shot_at = current_time( 'mysql' );

    // --- Upload-Dauer ---
    $upload_ms = round( microtime( true ) * 1000 ) - $start_ms;

    // --- DB-Eintrag ---
    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $inserted = $wpdb->insert( $fotos_table, [
        'user_id'     => $user_id,
        'event_year'  => $event_year,
        'filename'    => $filename,
        'filepath'    => $rel_path,
        'filesize'    => $filesize,
        'shot_at'     => $shot_at,
        'uploaded_at' => current_time( 'mysql' ),
        'token_type'  => $token_type,
        'upload_ms'   => (int) $upload_ms,
    ], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d' ] );

    if ( ! $inserted ) {
        // Datei wieder löschen wenn DB-Insert fehlschlägt
        @unlink( $dest );
        return new WP_Error( 'db_error', 'Datenbankfehler beim Speichern.', [ 'status' => 500 ] );
    }

    $foto_id = (int) $wpdb->insert_id;

    return new WP_REST_Response( [
        'success'    => true,
        'foto_id'    => $foto_id,
        'user_id'    => $user_id,
        'token_type' => $token_type,
        'filename'   => $filename,
        'upload_ms'  => (int) $upload_ms,
    ], 200 );
}

/**
 * POST /wp-json/oemm-xxvi/v1/foto/toggle-public
 * Body: { foto_id: 42, public: true/false }
 */
function oemm_xxvi_rest_foto_toggle_public( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $user    = wp_get_current_user();
    $foto_id = (int) ( $req->get_param( 'foto_id' ) ?? 0 );
    $public  = (bool) ( $req->get_param( 'public' ) ?? false );
    $table   = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;

    // Nur eigene Fotos
    $foto = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, user_id FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
        $foto_id, $user->ID
    ) );

    if ( ! $foto ) {
        return new WP_Error( 'not_found', 'Foto nicht gefunden oder kein Zugriff.', [ 'status' => 404 ] );
    }

    $data   = [ 'is_public' => $public ? 1 : 0 ];
    $format = [ '%d' ];
    if ( $public ) {
        $data['public_set_at'] = current_time( 'mysql' );
        $format[]              = '%s';
    } else {
        $data['public_set_at'] = null;
        $format[]              = '%s';
    }

    $wpdb->update( $table, $data, [ 'id' => $foto_id ], $format, [ '%d' ] );

    return new WP_REST_Response( [ 'success' => true, 'foto_id' => $foto_id, 'is_public' => $public ], 200 );
}

/**
 * POST /wp-json/oemm-xxvi/v1/foto/like
 * Body: { foto_id: 42 }  — togglet Like (like wenn nicht vorhanden, unlike wenn vorhanden)
 */
function oemm_xxvi_rest_foto_like( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $user    = wp_get_current_user();
    $foto_id = (int) ( $req->get_param( 'foto_id' ) ?? 0 );

    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $likes_table = $wpdb->prefix . OEMM_XXVI_LIKES_TABLE;

    // Foto muss existieren + Jahres-Isolation prüfen
    $event_year = (int) get_option( 'oemm_event_year', (int) date( 'Y' ) );
    $foto = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, user_id, event_year FROM {$fotos_table} WHERE id = %d LIMIT 1", $foto_id
    ) );

    if ( ! $foto ) {
        return new WP_Error( 'not_found', 'Foto nicht gefunden.', [ 'status' => 404 ] );
    }

    // Jahres-Check: User muss Teilnehmer desselben Jahres sein
    $is_participant = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}oemm_participants WHERE customer_id = %d AND event_year = %d LIMIT 1",
        $user->ID, (int) $foto->event_year
    ) );

    if ( ! $is_participant ) {
        return new WP_Error( 'forbidden', 'Kein Zugriff auf dieses Event-Jahr.', [ 'status' => 403 ] );
    }

    // Like schon vorhanden?
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$likes_table} WHERE foto_id = %d AND liker_user_id = %d LIMIT 1",
        $foto_id, $user->ID
    ) );

    if ( $existing ) {
        // Unlike
        $wpdb->delete( $likes_table, [ 'foto_id' => $foto_id, 'liker_user_id' => $user->ID ], [ '%d', '%d' ] );
        $liked = false;
    } else {
        // Like
        $wpdb->insert( $likes_table, [
            'foto_id'       => $foto_id,
            'liker_user_id' => $user->ID,
            'liked_at'      => current_time( 'mysql' ),
        ], [ '%d', '%d', '%s' ] );
        $liked = true;
    }

    $like_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$likes_table} WHERE foto_id = %d", $foto_id
    ) );

    return new WP_REST_Response( [
        'success'    => true,
        'foto_id'    => $foto_id,
        'liked'      => $liked,
        'like_count' => $like_count,
    ], 200 );
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
    $filename = 'ha-' . $user_id . '.pdf';
    $filepath = $dir . $filename;
    oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png );

    // WC Download Permission anlegen
    $dl_key = 'oemm_ha_' . $user_id;
    $dl_url = add_query_arg( [
        'oemm_dl'  => 1,
        'uid'      => $user_id,
        'token'    => hash( 'sha256', AUTH_KEY . $user_id . $filename ),
    ], home_url( '/' ) );

    // Als user meta speichern (einfacher als WC-Product)
    update_user_meta( $user_id, '_oemm_ha_dl_url',   $dl_url );
    update_user_meta( $user_id, '_oemm_ha_dl_file',  $filepath );
    update_user_meta( $user_id, '_oemm_ha_signed_ts', $signed_ts );
    // Sig-PNG fuer spatere Regenerierung aufbewahren
    if ( ! empty( $sig_png ) ) {
        update_user_meta( $user_id, '_oemm_ha_sig_png', $sig_png );
    }
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

    // Auto-Regenerierung: falls Datei fehlt ODER noch .html (alte Version)
    if ( ! file_exists( $filepath ) || substr( $filepath, -5 ) === '.html' || filesize( $filepath ) < 500 ) {
        $u         = get_user_by( 'id', $user_id );
        $fullname  = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
        $username  = $u->user_login;
        $signed_ts = get_user_meta( $user_id, '_oemm_ha_signed_ts', true ) ?: date('d.m.Y H:i:s');
        $sig_png   = get_user_meta( $user_id, '_oemm_ha_sig_png',  true ) ?: '';
        $upload    = wp_upload_dir();
        $dir       = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
        wp_mkdir_p( $dir );
        $filepath  = $dir . 'ha-' . $user_id . '.pdf';
        oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png );
        // Update DL-Link und File-Path
        $filename = basename( $filepath );
        $dl_url = add_query_arg( [
            'oemm_dl' => 1, 'uid' => $user_id,
            'token'   => hash( 'sha256', AUTH_KEY . $user_id . $filename ),
        ], home_url( '/' ) );
        update_user_meta( $user_id, '_oemm_ha_dl_file', $filepath );
        update_user_meta( $user_id, '_oemm_ha_dl_url',  $dl_url );
    }

    if ( ! file_exists( $filepath ) ) wp_die( 'PDF konnte nicht erstellt werden.' );

    // WP-Ausgabe vollstaendig verwerfen
    while ( ob_get_level() ) ob_end_clean();

    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="Haftungsausschluss_OeMM_2026.pdf"' );
    header( 'Content-Length: ' . filesize( $filepath ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    readfile( $filepath );
    exit;
}

function oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png ) {
    // tFPDF_OMM wird global geladen (siehe oben nach OEMM_XXVI_PATH define)
    $pdf = new tFPDF_OMM('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetMargins(20, 20, 20);

    // UTF-8 Fonts registrieren (tFPDF sucht in FPDF_FONTPATH/unifont/)
    $pdf->AddFont('DejaVu', '',  'DejaVuSans.ttf',      true);
    $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);

    $pdf->AddPage();

    // ========== HEADER BLOCK ==========
    $pdf->SetFillColor(15, 52, 96);
    $pdf->Rect(0, 0, 210, 36, 'F');

    // Logo
    $logo_path = OEMM_XXVI_PATH . 'assets/rocky-logo-pdf.png';
    if ( file_exists($logo_path) ) {
        $pdf->Image($logo_path, 14, 4, 28, 28);
    }

    // Titel neben Logo
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('DejaVu', 'B', 15);
    $pdf->SetXY(46, 7);
    $pdf->Cell(0, 8, 'OTZTALER MOPED VEREIN', 0, 1, 'L');
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(200, 215, 240);
    $pdf->SetX(46);
    $pdf->Cell(0, 5, 'AGB & Haftungsausschluss - Oetztaler Moped Marathon XXVI / 2026', 0, 1, 'L');
    $pdf->SetX(46);
    $pdf->SetTextColor(240, 192, 64);
    $pdf->Cell(0, 5, 'mopedmarathon.at', 0, 1, 'L');

    $pdf->SetY(42);

    // ========== META BOX ==========
    $pdf->SetFillColor(235, 241, 250);
    $pdf->SetDrawColor(200, 215, 240);
    $pdf->Rect(20, 42, 170, 26, 'FD');
    $pdf->SetXY(25, 45);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->SetFont('DejaVu', 'B', 8);
    $pdf->Cell(32, 5, 'Teilnehmer:', 0, 0);
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 5, $fullname . '  (@' . $username . ''), 0, 1);
    $pdf->SetX(25);
    $pdf->SetFont('DejaVu', 'B', 8);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->Cell(32, 5, 'Veranstalter:', 0, 0);
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 5, 'Oetztaler Moped Verein, Soelden, Tirol', 0, 1);
    $pdf->SetX(25);
    $pdf->SetFont('DejaVu', 'B', 8);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->Cell(32, 5, 'Unterzeichnet am:', 0, 0);
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 5, $signed_ts, 0, 1);

    $pdf->SetY(74);

    // Meta-Box
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->SetX(20);
    $pdf->SetFillColor(235, 241, 250);
    $pdf->Rect(20, 34, 170, 24, 'F');
    $pdf->SetXY(24, 37);
    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->Cell(28, 5, 'Teilnehmer:', 0, 0);
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->Cell(0, 5, $fullname . ' (@' . $username . ''), 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->Cell(28, 5, 'Veranstalter:', 0, 0);
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->Cell(0, 5, 'Oetztaler Moped Verein, Soelden, Tirol', 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->Cell(28, 5, 'Unterzeichnet:', 0, 0);
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->Cell(0, 5, $signed_ts, 0, 1);
    $pdf->SetY(64);

    // AGB Text
    $pdf->SetTextColor(40, 40, 40);

    // HA-Text: exakt gleicher Wortlaut wie HTML-View (Herzversion)
    // Umlaute per iconv am Ende konvertiert - Quellcode bleibt UTF-8 lesbar
    $fn = $fullname;
    $sections = [
        'ALLGEMEINE GESCHAEFTSBEDINGUNGEN DES OeMV' => [
            $fn . ' erklaert seinen Beitritt zum Oetztaler Moped Verein, spaeter "OeMV" genannt, mit Kauf der Mitgliedschaft, als ordentliches Mitglied ohne Stimmrecht fuer die Dauer bis Ende September des jeweiligen Jahres.',
            'Mit der Bezahlung des Mitgliedsbeitrags ist die Teilnahme an Veranstaltungen die durch den OeMV organisiert werden, allen voran der OeMM, moeglich. Diese Ausflugsfahrt erfolgt nicht gewerblich, ist kein Rennen und dient der Mitgliederwerbung und Popularisierung der Mopedliebhaberei.',
            'Dieser Mitgliedsbeitrag wird fuer die reibungslose Durchfuehrung des OeMM verwendet, fliesst ungekoerzt und unmittelbar dem OeMV zu. Weitere Veranstaltungen des OeMV werden nicht auf Kosten des Vereins organisiert. Lediglich die offizielle Vereinsausfahrt unter dem Namen OeMM ist durch den Mitgliedsbeitrag gedeckt.',
            'Alle Vereinsmitglieder, welche sich an der Organisation des OeMM beteiligen, handeln als Vertreter des OeMV und damit nicht in eigenem Namen.',
        ],
        'TEILNAHMEBEDINGUNGEN & HAFTUNGSAUSSCHLUSS' => [
            'Die Teilnahme ist nur bei Volljaehrigkeit gestattet. Bei Fahrern unter 18 Jahren muss vor dem Start die schriftliche Einwilligung der Eltern an den OeMV uebergeben werden.',
            'Mir, dem Teilnehmer, ist bewusst, dass eine derartige Ausflugsfahrt mit gewissen Risiken behaftet ist. Ich bestatige ausdruecklich, dass fuer Verletzungen und Schaeden jeglicher Art dem OeMV keinerlei Schuld zuweisbar ist und ich den OeMV schad- und klaglos halte. Ich bin im Besitz einer gueltigen Haftpflichtversicherung und persoenlichen Unfallversicherung.',
            'Weiters verpflichte ich mich, mich an die Rundfahrt- und Sicherheitsvorschriften des OeMV zu halten.',
            'ACHTUNG: Ich bestatige hiermit ausdruecklich, dass ich bei der verbindlichen Fahrerbesprechung des OeMV persoenlich anwesend sein werde.',
            'Ich bestatige, dass meine Ausruestung keine Maengel aufweist. Bei augenscheinlichen Maengeln kann ich jederzeit von der Teilnahme ausgeschlossen werden.',
            'Es werden auf der Strecke Geschwindigkeits- und Alkoholkontrollen erhoben. Der Teilnehmer erklaert ausdruecklich, weder unter Alkohol- noch Drogeneinfluss zu stehen.',
        ],
        'BILD- & TONRECHTE' => [
            'Der OeMV oder von ihm autorisierte Dritte sind berechtigt, Audio- und Videoaufzeichnungen sowie Fotos des OeMM fuer jegliche Zwecke in allen Medien weltweit zu verwenden. Der OeMV ist berechtigt, diese Rechte an Dritte zu uebertragen.',
        ],
        'RUECKGABERECHT & STREITBEILEGUNG' => [
            'Rueckgaberecht laut Fernabsatzgesetz: 14 Tage ohne Angabe von Gruenden. Gerichtsstand: Innsbruck.',
            'Information gem. Par. 19 Abs 3 AStG: Wir sind weder verpflichtet noch bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.',
        ],
    ];

    foreach ( $sections as $title => $paragraphs ) {
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->SetTextColor(15, 52, 96);
        $pdf->SetX(20);
        $pdf->Cell(0, 6, strtoupper($title), 0, 1);
        $pdf->SetDrawColor(15, 52, 96);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        foreach ( $paragraphs as $p ) {
            $pdf->SetX(20);
            $pdf->MultiCell(170, 5, $p, 0, 'L');
            $pdf->Ln(1);
        }
        $pdf->Ln(3);
    }

    // ========== UNTERSCHRIFT SEKTION ==========
    $pdf->Ln(4);
    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->SetX(20);
    $pdf->Cell(0, 6, 'DIGITALE UNTERSCHRIFT DES TEILNEHMERS', 0, 1);
    $pdf->SetDrawColor(15, 52, 96);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(5);

    // Unterschriften-Box
    $sig_box_y = $pdf->GetY();
    $pdf->SetFillColor(252, 252, 255);
    $pdf->SetDrawColor(200, 215, 240);
    $pdf->Rect(30, $sig_box_y, 150, 45, 'FD');

    // Sig PNG einbetten
    if ( ! empty($sig_png) && strpos($sig_png, 'data:image/png;base64,') === 0 ) {
        $b64  = substr($sig_png, strlen('data:image/png;base64,'));
        $data = base64_decode($b64);
        if ( $data ) {
            $tmp = tempnam(sys_get_temp_dir(), 'oemm_sig_') . '.png';
            file_put_contents($tmp, $data);
            if ( filesize($tmp) > 100 ) {
                $pdf->Image($tmp, 35, $sig_box_y + 3, 140, 36);
            }
            unlink($tmp);
        }
    }

    $pdf->SetY($sig_box_y + 47);
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Line(30, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetX(30);
    $pdf->Cell(150, 5, $fullname . '  |  ' . $signed_ts, 0, 1, 'C');
    $pdf->SetX(30);
    $pdf->SetFont('DejaVu', '', 7);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->Cell(150, 5, 'Rechtsverbindliche digitale Unterschrift gemaess EU-Verordnung 910/2014 (eIDAS)', 0, 1, 'C');

    // ========== FOOTER ==========
    $pdf->SetY(-18);
    $pdf->SetFillColor(15, 52, 96);
    $pdf->Rect(0, $pdf->GetY(), 210, 18, 'F');
    $pdf->SetFont('DejaVu', '', 7);
    $pdf->SetTextColor(180, 200, 230);
    $pdf->SetX(20);
    $pdf->Cell(0, 9, 'Oetztaler Moped Verein - OeMM XXVI 2026  |  mopedmarathon.at  |  Elektronisch unterzeichnet: ' . $signed_ts, 0, 0, 'C');

    $pdf->Output('F', $filepath);
}

// tFPDF_OMM: tFPDF Subklasse mit RoundedRect (UTF-8 fähig, kein FPDF nötig)
if ( ! class_exists('tFPDF_OMM') ) {
    class tFPDF_OMM extends tFPDF {
        public function RoundedRect($x, $y, $w, $h, $r, $style='') {
            $k=$this->k; $hp=$this->h;
            if($style=='F') $op='f';
            elseif($style=='FD'||$style=='DF') $op='B';
            else $op='S';
            $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
            $xc=$x+$w-$r; $yc=$y+$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-$y)*$k));
            $this->_Arc2($xc,$yc,$r,90,0);
            $xc=$x+$w-$r; $yc=$y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
            $this->_Arc2($xc,$yc,$r,0,-90);
            $xc=$x+$r; $yc=$y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
            $this->_Arc2($xc,$yc,$r,-90,-180);
            $xc=$x+$r; $yc=$y+$r;
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
            $this->_Arc2($xc,$yc,$r,180,90);
            $this->_out($op);
        }
        private function _Arc2($x1,$y1,$r,$a1,$a2) {
            $a1=deg2rad($a1); $a2=deg2rad($a2);
            $b=$r*4/3*(1-cos(($a1-$a2)/2))/sin(($a1-$a2)/2);
            $x2=$x1+$r*cos($a1); $y2=$y1-$r*sin($a1);
            $x3=$x1+$r*cos($a2); $y3=$y1-$r*sin($a2);
            $k=$this->k; $hp=$this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
                ($x2-$b*sin($a1))*$k, ($hp-($y2-$b*cos($a1)))*$k,
                ($x3+$b*sin($a2))*$k, ($hp-($y3+$b*cos($a2)))*$k,
                $x3*$k, ($hp-$y3)*$k));
        }
    }
}

/* ---------------------------------------------------------------
   PDF REGENERATE — Admin-Funktion fuer bestehende User
   Aufruf: /wp-admin/?oemm_regen_pdf=USER_ID
--------------------------------------------------------------- */
add_action( 'admin_init', 'oemm_xxvi_admin_regen_pdf' );
function oemm_xxvi_admin_regen_pdf() {
    if ( empty( $_GET['oemm_regen_pdf'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff.' );
    $uid = absint( $_GET['oemm_regen_pdf'] );
    $u   = get_user_by( 'id', $uid );
    if ( ! $u ) wp_die( 'User nicht gefunden.' );

    $fullname  = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
    $username  = $u->user_login;
    $signed_ts = get_user_meta( $uid, '_oemm_ha_signed_ts', true ) ?: date('d.m.Y H:i:s');
    $sig_png   = get_user_meta( $uid, '_oemm_ha_sig_png',  true ) ?: '';

    $upload   = wp_upload_dir();
    $dir      = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
    wp_mkdir_p( $dir );
    $filename = 'ha-' . $uid . '.pdf';
    $filepath = $dir . $filename;

    oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png );

    $dl_url = add_query_arg( [
        'oemm_dl' => 1,
        'uid'     => $uid,
        'token'   => hash( 'sha256', AUTH_KEY . $uid . $filename ),
    ], home_url( '/' ) );
    update_user_meta( $uid, '_oemm_ha_dl_url',  $dl_url );
    update_user_meta( $uid, '_oemm_ha_dl_file', $filepath );

    $size = file_exists($filepath) ? filesize($filepath) : 0;
    wp_die( 'PDF regeneriert fuer ' . esc_html($fullname) . ' | Groesse: ' . $size . ' Bytes | <a href="' . esc_url($dl_url) . '">Download testen</a>' );
}

/* ---------------------------------------------------------------
   ADMIN MENU + DIAGNOSE
--------------------------------------------------------------- */
add_action( 'admin_menu', 'oemm_xxvi_admin_menu' );
function oemm_xxvi_admin_menu() {
    add_menu_page(
        'OeMM Backend',
        'OeMM XXVI',
        'manage_options',
        'oemm-xxvi-admin',
        'oemm_xxvi_admin_page',
        'dashicons-tickets-alt',
        56
    );
}
function oemm_xxvi_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Update-Cache leeren und zu WP Updates weiterleiten
    if ( isset( $_GET['oemm_check_update'] ) && check_admin_referer( 'oemm_check_update' ) ) {
        delete_transient( 'oemm_xxvi_github_release' );
        delete_site_transient( 'update_plugins' );
        wp_redirect( admin_url( 'update-core.php' ) );
        exit;
    }

    // Settings speichern
    if ( isset( $_POST['oemm_settings_nonce'] ) && wp_verify_nonce( $_POST['oemm_settings_nonce'], 'oemm_save_settings' ) ) {
        // API Key neu generieren?
        if ( ! empty( $_POST['oemm_regen_key'] ) ) {
            $new_key = bin2hex( random_bytes( 24 ) );
            update_option( 'oemm_foto_api_key', $new_key );
        }
        // ZIP Datum
        if ( isset( $_POST['oemm_zip_available_date'] ) ) {
            update_option( 'oemm_zip_available_date', sanitize_text_field( $_POST['oemm_zip_available_date'] ) );
        }
        // Album aktiv
        update_option( 'oemm_album_active', ! empty( $_POST['oemm_album_active'] ) ? '1' : '0' );
        echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
    }

    // PDF Regenerieren
    if ( ! empty( $_GET['regen'] ) && check_admin_referer( 'oemm_regen_' . (int) $_GET['regen'] ) ) {
        $uid = absint( $_GET['regen'] );
        $u2  = get_user_by( 'id', $uid );
        if ( $u2 ) {
            $fn  = trim( $u2->first_name . ' ' . $u2->last_name ) ?: $u2->display_name;
            $ts  = get_user_meta( $uid, '_oemm_ha_signed_ts', true ) ?: date( 'd.m.Y H:i:s' );
            $sig = get_user_meta( $uid, '_oemm_ha_sig_png', true ) ?: '';
            $dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'oemm-agreements/';
            wp_mkdir_p( $dir );
            $fp  = $dir . 'ha-' . $uid . '.pdf';
            oemm_xxvi_generate_pdf( $fp, $fn, $u2->user_login, $ts, $sig );
            $dlurl = add_query_arg( [ 'oemm_dl' => 1, 'uid' => $uid, 'token' => hash( 'sha256', AUTH_KEY . $uid . basename($fp) ) ], home_url('/') );
            update_user_meta( $uid, '_oemm_ha_dl_file', $fp );
            update_user_meta( $uid, '_oemm_ha_dl_url', $dlurl );
        }
    }

    global $wpdb;
    $tab         = sanitize_key( $_GET['tab'] ?? 'settings' );
    $api_key     = get_option( 'oemm_foto_api_key', '' );
    $zip_date    = get_option( 'oemm_zip_available_date', '' );
    $album_active = get_option( 'oemm_album_active', '0' );
    $event_year  = (int) get_option( 'oemm_event_year', date('Y') );

    // --- Stats ---
    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $likes_table = $wpdb->prefix . OEMM_XXVI_LIKES_TABLE;
    $stats = [ 'total' => 0, 'public' => 0, 'likes' => 0, 'app' => 0, 'paper' => 0, 'top_users' => [], 'top_foto' => null, 'per_hour' => [] ];
    $tables_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fotos_table ) ) === $fotos_table;
    if ( $tables_exist ) {
        $stats['total']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$fotos_table} WHERE event_year=%d", $event_year ) );
        $stats['public'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$fotos_table} WHERE event_year=%d AND is_public=1", $event_year ) );
        $stats['likes']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$likes_table} l JOIN {$fotos_table} f ON f.id=l.foto_id WHERE f.event_year=%d", $event_year ) );
        $stats['app']    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$fotos_table} WHERE event_year=%d AND token_type='app'", $event_year ) );
        $stats['paper']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$fotos_table} WHERE event_year=%d AND token_type='paper'", $event_year ) );
        $stats['top_users'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT f.user_id, u.display_name, COUNT(*) as foto_count,
                    SUM(f.is_public) as public_count,
                    (SELECT COUNT(*) FROM {$likes_table} l WHERE l.foto_id IN (SELECT id FROM {$fotos_table} f2 WHERE f2.user_id=f.user_id AND f2.event_year=%d)) as like_count
             FROM {$fotos_table} f LEFT JOIN {$wpdb->users} u ON u.ID=f.user_id
             WHERE f.event_year=%d GROUP BY f.user_id ORDER BY foto_count DESC LIMIT 10",
            $event_year, $event_year
        ) );
        $stats['top_foto'] = $wpdb->get_row( $wpdb->prepare(
            "SELECT f.id, f.user_id, f.filename, u.display_name, COUNT(l.id) as like_count
             FROM {$fotos_table} f
             LEFT JOIN {$likes_table} l ON l.foto_id=f.id
             LEFT JOIN {$wpdb->users} u ON u.ID=f.user_id
             WHERE f.event_year=%d GROUP BY f.id ORDER BY like_count DESC LIMIT 1",
            $event_year
        ) );
        $stats['per_hour'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT HOUR(uploaded_at) as hour, COUNT(*) as count
             FROM {$fotos_table} WHERE event_year=%d GROUP BY HOUR(uploaded_at) ORDER BY hour ASC",
            $event_year
        ) );
    }

    $page_url = admin_url( 'admin.php?page=oemm-xxvi-admin' );
    ?>
    <div class="wrap">
    <h1 style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <span style="font-size:28px">🏍️</span> ÖMM XXVI — Admin
        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'oemm_check_update', '1', $page_url ), 'oemm_check_update' ) ); ?>"
           class="button button-secondary" style="font-size:13px;margin-top:2px">
            🔄 Nach Update suchen
        </a>
        <span style="font-size:13px;color:#666;font-weight:400">Installierte Version: <?php echo OEMM_XXVI_VERSION; ?></span>
    </h1>

    <!-- TABS -->
    <nav class="nav-tab-wrapper" style="margin-bottom:20px">
        <a href="<?php echo esc_url(add_query_arg('tab','settings',$page_url)); ?>" class="nav-tab <?php echo $tab==='settings'?'nav-tab-active':''; ?>">⚙️ Einstellungen</a>
        <a href="<?php echo esc_url(add_query_arg('tab','stats',$page_url)); ?>" class="nav-tab <?php echo $tab==='stats'?'nav-tab-active':''; ?>">📊 Statistiken</a>
        <a href="<?php echo esc_url(add_query_arg('tab','users',$page_url)); ?>" class="nav-tab <?php echo $tab==='users'?'nav-tab-active':''; ?>">👥 Teilnehmer</a>
        <a href="<?php echo esc_url(add_query_arg('tab','diagnose',$page_url)); ?>" class="nav-tab <?php echo $tab==='diagnose'?'nav-tab-active':''; ?>">🔧 Diagnose</a>
    </nav>

    <?php if ( $tab === 'settings' ) : ?>
    <!-- ==================== EINSTELLUNGEN ==================== -->
    <form method="post">
        <?php wp_nonce_field( 'oemm_save_settings', 'oemm_settings_nonce' ); ?>
        <table class="form-table" style="max-width:700px">
            <tr>
                <th style="width:220px">📷 Fotobox API Key</th>
                <td>
                    <code style="background:#f0f0f0;padding:6px 12px;border-radius:4px;font-size:13px;display:inline-block;margin-bottom:8px;word-break:break-all">
                        <?php echo $api_key ? esc_html($api_key) : '<em style="color:#999">Noch nicht generiert</em>'; ?>
                    </code><br>
                    <label><input type="checkbox" name="oemm_regen_key" value="1">
                    <?php echo $api_key ? 'API Key neu generieren (alter Key wird ungültig!)' : 'API Key jetzt generieren'; ?></label>
                    <p class="description">Wird im Raspberry Pi Script als <code>X-OEMM-Foto-Key</code> Header verwendet.</p>
                </td>
            </tr>
            <tr>
                <th>📅 ZIP verfügbar ab</th>
                <td>
                    <input type="date" name="oemm_zip_available_date"
                           value="<?php echo esc_attr($zip_date); ?>"
                           style="font-size:14px;padding:6px 10px">
                    <p class="description">Ab diesem Datum ist der ZIP-Download für Teilnehmer aktiv. Leer lassen = nie.</p>
                </td>
            </tr>
            <tr>
                <th>🖼️ Öffentliches Album aktiv</th>
                <td>
                    <label>
                        <input type="checkbox" name="oemm_album_active" value="1" <?php checked($album_active,'1'); ?>>
                        Album für Teilnehmer sichtbar schalten
                    </label>
                    <p class="description">Nur Teilnehmer des aktuellen Event-Jahres (<?php echo $event_year; ?>) können das Album sehen.</p>
                </td>
            </tr>
        </table>
        <p><input type="submit" class="button button-primary button-large" value="💾 Einstellungen speichern"></p>
    </form>

    <?php elseif ( $tab === 'stats' ) : ?>
    <!-- ==================== STATISTIKEN ==================== -->
    <h2>📊 Foto-Statistiken ÖMM <?php echo $event_year; ?></h2>
    <?php if ( ! $tables_exist ) : ?>
        <div class="notice notice-warning"><p>Foto-Tabellen noch nicht angelegt. Plugin neu aktivieren.</p></div>
    <?php else : ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:30px">
        <?php
        $stat_boxes = [
            ['📸', 'Fotos gesamt', $stats['total'], '#0073aa'],
            ['🌍', 'Öffentlich', $stats['public'], '#00a32a'],
            ['❤️', 'Likes gesamt', $stats['likes'], '#d63638'],
            ['📱', 'App-QR', $stats['app'], '#8b5cf6'],
            ['🗒️', 'Papier-QR', $stats['paper'], '#f59e0b'],
            ['📊', 'Ø Likes/Foto', $stats['total'] > 0 ? round($stats['likes']/$stats['total'],1) : 0, '#10b981'],
        ];
        foreach ($stat_boxes as [$icon,$label,$val,$color]) : ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;text-align:center;border-top:3px solid <?php echo $color; ?>">
            <div style="font-size:24px"><?php echo $icon; ?></div>
            <div style="font-size:28px;font-weight:700;color:<?php echo $color; ?>;margin:4px 0"><?php echo esc_html($val); ?></div>
            <div style="font-size:12px;color:#666"><?php echo esc_html($label); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( ! empty($stats['top_foto']) && $stats['top_foto']->like_count > 0 ) : ?>
    <h3>🏆 Meistgeliktes Foto</h3>
    <p><strong><?php echo esc_html($stats['top_foto']->display_name); ?></strong> — Foto #<?php echo $stats['top_foto']->id; ?> mit <?php echo $stats['top_foto']->like_count; ?> Likes</p>
    <?php endif; ?>

    <?php if ( ! empty($stats['per_hour']) ) : ?>
    <h3>⏰ Uploads nach Uhrzeit</h3>
    <table class="widefat striped" style="max-width:500px">
        <thead><tr><th>Stunde</th><th>Fotos</th><th>Balken</th></tr></thead>
        <tbody>
        <?php $max_h = max(array_column((array)$stats['per_hour'],'count')); foreach ($stats['per_hour'] as $h) : ?>
        <tr>
            <td><?php echo str_pad($h->hour,2,'0',STR_PAD_LEFT); ?>:00</td>
            <td><?php echo $h->count; ?></td>
            <td><div style="background:#0073aa;height:16px;border-radius:3px;width:<?php echo round($h->count/$max_h*200); ?>px"></div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php endif; ?>

    <?php elseif ( $tab === 'users' ) : ?>
    <!-- ==================== TEILNEHMER ==================== -->
    <h2>👥 Teilnehmer ÖMM <?php echo $event_year; ?></h2>
    <?php if ( ! $tables_exist ) : ?>
        <div class="notice notice-warning"><p>Foto-Tabellen noch nicht angelegt.</p></div>
    <?php elseif ( empty($stats['top_users']) ) : ?>
        <p style="color:#666">Noch keine Fotos hochgeladen.</p>
    <?php else : ?>
    <table class="widefat striped">
        <thead><tr><th>#</th><th>Teilnehmer</th><th>Fotos</th><th>Öffentlich</th><th>Likes erhalten</th></tr></thead>
        <tbody>
        <?php foreach ($stats['top_users'] as $i => $u) : ?>
        <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo esc_html($u->display_name ?: 'User #'.$u->user_id); ?></td>
            <td><?php echo $u->foto_count; ?></td>
            <td><?php echo $u->public_count; ?></td>
            <td><?php echo $u->like_count; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php elseif ( $tab === 'diagnose' ) : ?>
    <!-- ==================== DIAGNOSE ==================== -->
    <h2>🔧 Diagnose</h2>
    <pre style="background:#f0f0f0;padding:20px;font-size:11px;overflow:auto;max-height:70vh;border-radius:4px">
<?php
    // Benutzer mit HA
    echo "=== BENUTZER MIT HA-UNTERSCHRIFT ===\n";
    $users = $wpdb->get_results(
        "SELECT u.ID, u.user_login,
                MAX(CASE WHEN m.meta_key='_oemm_ha_signed_ts' THEN m.meta_value END) as signed_ts,
                MAX(CASE WHEN m.meta_key='_oemm_startnumber'  THEN m.meta_value END) as startnr,
                MAX(CASE WHEN m.meta_key='_oemm_ha_dl_file'   THEN m.meta_value END) as dl_file
         FROM {$wpdb->users} u JOIN {$wpdb->usermeta} m ON u.ID=m.user_id
         WHERE m.meta_key IN ('_oemm_ha_signed_ts','_oemm_startnumber','_oemm_ha_dl_file')
         GROUP BY u.ID ORDER BY u.ID"
    );
    foreach ($users as $u) {
        $fok = $u->dl_file && file_exists($u->dl_file) ? 'OK' : 'FEHLT';
        $regen_url = wp_nonce_url( add_query_arg(['tab'=>'diagnose','regen'=>$u->ID], $page_url), 'oemm_regen_'.$u->ID );
        echo "User #{$u->ID} ({$u->user_login}) signed={$u->signed_ts} pdf={$fok} ";
        echo "<a href='" . esc_url($regen_url) . "'>PDF neu</a>\n";
    }

    // DB Tabellen
    echo "\n=== PLUGIN-TABELLEN ===\n";
    $all = $wpdb->get_col('SHOW TABLES');
    $rel = array_filter($all, fn($t) => preg_match('/(startl|omm|oemm|marathon)/i',$t));
    foreach ($rel as $table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        echo "{$table}: {$count} Einträge\n";
    }

    // Settings
    echo "\n=== SETTINGS ===\n";
    $opts = ['oemm_event_year','oemm_foto_api_key','oemm_zip_available_date','oemm_album_active','oemm_app_url'];
    foreach ($opts as $o) echo "{$o} = " . get_option($o,'(leer)') . "\n";

    // Storage Check
    echo "\n=== STORAGE ===\n";
    $upload  = wp_upload_dir();
    $fotosdir = trailingslashit($upload['basedir']) . 'oemm-fotos';
    echo "Fotos-Root: {$fotosdir}\n";
    echo "Existiert: " . (is_dir($fotosdir) ? 'JA' : 'NEIN') . "\n";
    echo ".htaccess: " . (file_exists($fotosdir.'/.htaccess') ? 'OK' : 'FEHLT') . "\n";
    if (is_dir($fotosdir)) {
        $years = glob($fotosdir.'/*', GLOB_ONLYDIR);
        foreach ($years as $y) {
            $users_dirs = glob($y.'/*', GLOB_ONLYDIR);
            $file_count = 0;
            foreach ($users_dirs as $ud) $file_count += count(glob($ud.'/*.jpg')) + count(glob($ud.'/*.png'));
            echo basename($y) . ": " . count($users_dirs) . " User-Ordner, {$file_count} Dateien\n";
        }
    }
?>
    </pre>
    <?php endif; ?>
    </div>
    <?php
}

add_action( 'admin_init', 'oemm_xxvi_admin_diagnose' );
function oemm_xxvi_admin_diagnose() {
    if ( empty( $_GET['oemm_diagnose'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff.' );

    global $wpdb;
    $out = '<h2>OeMM Diagnose</h2><pre style="font-size:12px;">';

    // Alle User mit HA unterzeichnet
    $users = $wpdb->get_results(
        "SELECT u.ID, u.user_login, u.display_name,
                MAX(CASE WHEN m.meta_key='_oemm_ha_signed_ts'  THEN m.meta_value END) as signed_ts,
                MAX(CASE WHEN m.meta_key='_oemm_startnumber'   THEN m.meta_value END) as startnr,
                MAX(CASE WHEN m.meta_key='_oemm_ha_dl_file'    THEN m.meta_value END) as dl_file,
                MAX(CASE WHEN m.meta_key='_oemm_ha_dl_url'     THEN m.meta_value END) as dl_url
         FROM {$wpdb->users} u
         JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
         WHERE m.meta_key IN ('_oemm_ha_signed_ts','_oemm_startnumber','_oemm_ha_dl_file','_oemm_ha_dl_url')
         GROUP BY u.ID ORDER BY u.ID"
    );
    foreach ( $users as $u ) {
        $file_ok  = $u->dl_file && file_exists($u->dl_file) ? 'OK (' . filesize($u->dl_file) . ' B)' : 'FEHLT';
        $out .= "User #{$u->ID} {$u->user_login}\n";
        $out .= "  Signed:     {$u->signed_ts}\n";
        $out .= "  Startnr:    {$u->startnr}\n";
        $out .= "  PDF:        {$file_ok}\n";
        $out .= "  DL-URL:     {$u->dl_url}\n\n";
    }

    // Startlisten-Plugin Tabellen suchen
    $tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
    $startliste_tables = [];
    foreach ( $tables as $t ) {
        if ( stripos($t[0], 'startli') !== false || stripos($t[0], 'oemm') !== false || stripos($t[0], 'omm') !== false ) {
            $startliste_tables[] = $t[0];
        }
    }
    $out .= "\nDatenbank-Tabellen mit 'startli'/'oemm'/'omm':\n";
    foreach ( $startliste_tables as $t ) {
        $out .= "  - $t\n";
        $cols = $wpdb->get_results( "DESCRIBE $t", ARRAY_N );
        foreach ( $cols as $c ) $out .= "      {$c[0]} ({$c[1]})\n";
        $sample = $wpdb->get_results( "SELECT * FROM $t LIMIT 3" );
        $out .= "    Sample: " . json_encode($sample) . "\n";
    }

    // Plugin-Options nach App-URL suchen
    $opts = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options}
         WHERE option_name LIKE '%oemm%' OR option_name LIKE '%omm%' OR option_name LIKE '%startlist%'
         LIMIT 30"
    );
    $out .= "\nPlugin-Options (oemm/omm/startlist):\n";
    foreach ( $opts as $o ) {
        $val = strlen($o->option_value) > 200 ? substr($o->option_value,0,200).'...' : $o->option_value;
        $out .= "  {$o->option_name}: $val\n";
    }

    $out .= '</pre>';
    wp_die( $out );
}

/* ---------------------------------------------------------------
   AVATAR UPLOAD
--------------------------------------------------------------- */
add_action( 'wp_ajax_oemm_upload_avatar', 'oemm_xxvi_upload_avatar' );
function oemm_xxvi_upload_avatar() {
    check_ajax_referer( 'oemm_avatar', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();
    if ( empty($_FILES['avatar']) ) wp_send_json_error(['msg'=>'Keine Datei']);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['avatar'];
    // Nur Bilder erlauben
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if ( ! in_array($file['type'], $allowed) ) wp_send_json_error(['msg'=>'Nur Bilder erlaubt']);

    $upload = wp_handle_upload($file, ['test_form'=>false]);
    if ( isset($upload['error']) ) wp_send_json_error(['msg'=>$upload['error']]);

    update_user_meta( get_current_user_id(), '_oemm_avatar_url', $upload['url'] );
    wp_send_json_success(['url' => $upload['url']]);
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
    $oemm_pages = [ 'omm-dashboard', 'omm-bestellungen', 'omm-downloads', 'omm-adresse', 'omm-kontodetails', 'omm-packliste', 'omm-freundebuch', 'omm-ergebnisse', 'omm-fotos', 'omm-album', 'view-order' ];
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
