<?php
/**
 * Plugin Name: ÖMM Backend XXVI
 * Plugin URI:  https://mopedmarathon.at
 * Description: Login → HA-Gate → Dashboard. Schönes blaues Dashboard mit echten WooCommerce-Daten. PDF in Downloads.
 * Version:     2.3.27
 * Author:      Manuel Ribis GmbH
 * Text Domain: oemm-xxvi
 */

defined( 'ABSPATH' ) || exit;

define( 'OEMM_XXVI_VERSION', '2.3.27' );
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

define( 'OEMM_XXVI_TABLE',        'oemm_xxvi_agreements' );
define( 'OEMM_XXVI_FOTOS_TABLE',  'oemm_xxvi_fotos' );
define( 'OEMM_XXVI_LIKES_TABLE',  'oemm_xxvi_foto_likes' );
define( 'OEMM_XXVI_TIMETABLE',     'oemm_xxvi_timetable' );
define( 'OEMM_XXVI_CHECKIN_TABLE',  'oemm_xxvi_checkins' );

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

    // Timetable
    $tt_table = $wpdb->prefix . OEMM_XXVI_TIMETABLE;
    $sql_tt = "CREATE TABLE IF NOT EXISTS {$tt_table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_date  DATE NOT NULL,
        start_time  TIME NOT NULL,
        end_time    TIME DEFAULT NULL,
        title       VARCHAR(255) NOT NULL,
        subtitle    VARCHAR(255) DEFAULT NULL,
        icon        VARCHAR(20) DEFAULT '📅',
        category    VARCHAR(50) DEFAULT 'programm',
        sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_date_time (event_date, start_time)
    ) {$charset};";
    dbDelta( $sql_tt );

    // Timetable mit Initialdaten befüllen (nur wenn leer)
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tt_table}" );
    if ( (int)$count === 0 ) {
        $entries = [
            [ '2026-06-25', '16:00:00', '22:00:00', 'Ausgabe der Startpakete', 'Eventgelände Sölden', '📦', 'logistik', 10 ],
            [ '2026-06-26', '10:00:00', '22:00:00', 'Ausgabe der Startpakete', 'Eventgelände Sölden', '📦', 'logistik', 20 ],
            [ '2026-06-26', '12:30:00', null,        'Start Gletscherausfahrt', null, '🏍', 'rennen', 30 ],
            [ '2026-06-26', '19:30:00', null,        'Verbindliche Fahrerbesprechung', null, '📋', 'programm', 40 ],
            [ '2026-06-26', '23:59:00', null,        'Veranstaltungsende', null, '🎉', 'programm', 50 ],
            [ '2026-06-27', '06:00:00', null,        'Start Ötztaler Mopedmarathon XXVI', null, '🏁', 'rennen', 60 ],
            [ '2026-06-27', '14:00:00', null,        'Ankunft der ersten Rider im Ziel', 'ca. 14:00 Uhr', '🥇', 'rennen', 70 ],
            [ '2026-06-27', '17:30:00', null,        'Ankunft der letzten Rider im Ziel', null, '🏍', 'rennen', 80 ],
            [ '2026-06-27', '21:00:00', null,        'Siegerehrung', null, '🏆', 'programm', 90 ],
            [ '2026-06-27', '23:59:00', null,        'Veranstaltungsende', null, '🎉', 'programm', 100 ],
        ];
        foreach ( $entries as $e ) {
            $wpdb->insert( $tt_table, [
                'event_date' => $e[0],
                'start_time' => $e[1],
                'end_time'   => $e[2],
                'title'      => $e[3],
                'subtitle'   => $e[4],
                'icon'       => $e[5],
                'category'   => $e[6],
                'sort_order' => $e[7],
                'is_active'  => 1,
            ]);
        }
    }

    // Checkins-Tabelle
    $ci_table = $wpdb->prefix . OEMM_XXVI_CHECKIN_TABLE;
    $sql_ci = "CREATE TABLE IF NOT EXISTS {$ci_table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id         BIGINT UNSIGNED NOT NULL,
        pickup_slot     VARCHAR(100) DEFAULT NULL,
        checked_in_at   DATETIME DEFAULT NULL,
        checked_in_by   VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user (user_id)
    ) {$charset};";
    dbDelta( $sql_ci );

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

    $mime     = mime_content_type( $filepath ) ?: 'image/jpeg';
    $ext_map  = [
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov',
        'video/webm' => 'webm', 'video/x-msvideo' => 'avi',
    ];
    $ext      = $ext_map[ $mime ] ?? 'bin';
    $is_video = str_starts_with( $mime, 'video/' );
    $filesize = filesize( $filepath );

    header( 'Content-Type: ' . $mime );
    header( 'X-Content-Type-Options: nosniff' );

    // &dl=1 => Download-Header setzen
    if ( ! empty( $_GET['dl'] ) ) {
        header( 'Content-Disposition: attachment; filename="oemm_foto_' . (int) $foto->id . '.' . $ext . '"' );
        header( 'Content-Length: ' . $filesize );
        header( 'Cache-Control: private, no-cache' );
        readfile( $filepath );
        exit;
    }

    // Videos: Range-Request-Support fuer mobile Browser
    if ( $is_video ) {
        header( 'Accept-Ranges: bytes' );
        header( 'Cache-Control: private, max-age=3600' );
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ( $range && preg_match( '/bytes=(\d*)-(\d*)/', $range, $m ) ) {
            $start = $m[1] !== '' ? (int)$m[1] : 0;
            $end   = $m[2] !== '' ? (int)$m[2] : $filesize - 1;
            $end   = min( $end, $filesize - 1 );
            $length = $end - $start + 1;
            http_response_code( 206 );
            header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize );
            header( 'Content-Length: ' . $length );
            $fh = fopen( $filepath, 'rb' );
            fseek( $fh, $start );
            echo fread( $fh, $length );
            fclose( $fh );
        } else {
            header( 'Content-Length: ' . $filesize );
            readfile( $filepath );
        }
        exit;
    }

    // Bilder: normal ausliefern
    header( 'Content-Length: ' . $filesize );
    header( 'Cache-Control: private, max-age=3600' );
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

// WooCommerce muss die Endpoints in seinen Query-Vars kennen damit
// woocommerce_account_*_endpoint Hooks korrekt gefeuert werden
add_filter( 'woocommerce_get_query_vars', 'oemm_xxvi_register_wc_query_vars' );
function oemm_xxvi_register_wc_query_vars( $vars ) {
    $endpoints = [
        'haftungsausschluss', 'omm-dashboard', 'omm-bestellungen', 'omm-downloads',
        'omm-adresse', 'omm-kontodetails', 'omm-packliste', 'omm-freundebuch',
        'omm-ergebnisse', 'omm-fotos', 'omm-album',
    ];
    foreach ( $endpoints as $ep ) {
        $vars[ $ep ] = $ep;
    }
    return $vars;
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
function oemm_xxvi_page_ergebnisse()  { include OEMM_XXVI_PATH . 'views/ergebnisse.php'; }
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

    // Foto loeschen (eigene Fotos; Admins duerfen alle)
    register_rest_route( 'oemm-xxvi/v1', '/foto/delete', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_foto_delete',
        'permission_callback' => fn() => is_user_logged_in(),
    ] );

    // User-Upload: eingeloggte Teilnehmer koennen eigene Fotos/Videos hochladen
    register_rest_route( 'oemm-xxvi/v1', '/foto/user-upload', [
        'methods'             => 'POST',
        'callback'            => 'oemm_xxvi_rest_foto_user_upload',
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

/**
 * POST /wp-json/oemm-xxvi/v1/foto/user-upload
 * Eingeloggte Teilnehmer koennen eigene Fotos/Videos hochladen.
 * Multipart: datei=<file>, is_public=0|1
 * Max 100 MB (Videos), nur JPEG/PNG/MP4/MOV/WEBM
 */
function oemm_xxvi_rest_foto_user_upload( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $user       = wp_get_current_user();
    $event_year = (int) get_option( 'oemm_event_year', (int) date( 'Y' ) );

    // Muss Teilnehmer sein
    $is_participant = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}oemm_participants WHERE customer_id = %d AND event_year = %d LIMIT 1",
        $user->ID, $event_year
    ) );
    if ( ! $is_participant && ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'not_participant', 'Nur Teilnehmer koennen Dateien hochladen.', [ 'status' => 403 ] );
    }

    // Datei pruefen
    $files = $req->get_file_params();
    if ( empty( $files['datei'] ) || $files['datei']['error'] !== UPLOAD_ERR_OK ) {
        $err_code = $files['datei']['error'] ?? 'no_file';
        return new WP_Error( 'no_file', 'Keine Datei oder Upload-Fehler (Code: ' . $err_code . ').', [ 'status' => 400 ] );
    }

    $file     = $files['datei'];
    $tmp_path = $file['tmp_name'];
    $filesize = (int) $file['size'];
    $max_bytes = 25 * 1024 * 1024; // 25 MB

    if ( $filesize > $max_bytes ) {
        return new WP_Error( 'file_too_large', 'Datei zu gross (max 25 MB).', [ 'status' => 413 ] );
    }

    $mime = mime_content_type( $tmp_path ) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if ( ! isset( $allowed[ $mime ] ) ) {
        return new WP_Error( 'invalid_mime', 'Dateityp nicht erlaubt (JPEG/PNG/WEBP).', [ 'status' => 415 ] );
    }
    $ext      = $allowed[ $mime ];
    $is_video = false;

    $is_public = (int) ( $req->get_param( 'is_public' ) ?? 0 ) === 1 ? 1 : 0;

    // Verzeichnis + Dateiname
    $dir      = oemm_xxvi_fotos_ensure_dir( $user->ID, $event_year );
    $filename = 'user_' . date( 'YmdHis' ) . '_' . wp_generate_password( 8, false ) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    $upload   = wp_upload_dir();
    $rel_path = str_replace( trailingslashit( $upload['basedir'] ), '', $dest );

    if ( ! move_uploaded_file( $tmp_path, $dest ) ) {
        return new WP_Error( 'move_failed', 'Datei konnte nicht gespeichert werden.', [ 'status' => 500 ] );
    }



    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $wpdb->insert( $fotos_table, [
        'user_id'       => $user->ID,
        'event_year'    => $event_year,
        'filename'      => $filename,
        'filepath'      => $rel_path,
        'filesize'      => $filesize,
        'shot_at'       => current_time( 'mysql' ),
        'uploaded_at'   => current_time( 'mysql' ),
        'token_type'    => 'user',
        'upload_ms'     => 0,
        'is_public'     => $is_public,
    ], [ '%d','%d','%s','%s','%d','%s','%s','%s','%d','%d' ] );

    $foto_id  = (int) $wpdb->insert_id;
    $serve_url = oemm_xxvi_fotos_get_serve_url( $foto_id, $user->ID );

    return new WP_REST_Response( [
        'success'   => true,
        'foto_id'   => $foto_id,
        'url'       => $serve_url,
        'is_public' => $is_public,
        'filesize'  => $filesize,
    ], 200 );
}

/**
 * POST /wp-json/oemm-xxvi/v1/foto/delete
 * Body: { foto_id: 42 }
 * Loescht Foto + DB-Eintraege. User darf nur eigene Fotos loeschen; Admins alle.
 */
function oemm_xxvi_rest_foto_delete( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $user    = wp_get_current_user();
    $foto_id = (int) ( $req->get_param( 'foto_id' ) ?? 0 );

    if ( $foto_id <= 0 ) {
        return new WP_Error( 'invalid_id', 'Ungueltige Foto-ID.', [ 'status' => 400 ] );
    }

    $fotos_table = $wpdb->prefix . OEMM_XXVI_FOTOS_TABLE;
    $likes_table = $wpdb->prefix . OEMM_XXVI_LIKES_TABLE;
    $is_admin    = current_user_can( 'manage_options' );

    // Foto laden — Admin sieht alle, User nur eigene
    if ( $is_admin ) {
        $foto = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, user_id, filepath FROM {$fotos_table} WHERE id = %d LIMIT 1",
            $foto_id
        ) );
    } else {
        $foto = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, user_id, filepath FROM {$fotos_table} WHERE id = %d AND user_id = %d LIMIT 1",
            $foto_id, $user->ID
        ) );
    }

    if ( ! $foto ) {
        return new WP_Error( 'not_found', 'Foto nicht gefunden oder kein Zugriff.', [ 'status' => 404 ] );
    }

    // Datei vom Server loeschen
    $upload    = wp_upload_dir();
    $full_path = trailingslashit( $upload['basedir'] ) . ltrim( $foto->filepath, '/' );
    if ( file_exists( $full_path ) ) {
        @unlink( $full_path );
    }

    // Likes loeschen
    $wpdb->delete( $likes_table, [ 'foto_id' => $foto_id ], [ '%d' ] );

    // DB-Eintrag loeschen
    $wpdb->delete( $fotos_table, [ 'id' => $foto_id ], [ '%d' ] );

    return new WP_REST_Response( [ 'success' => true, 'foto_id' => $foto_id ], 200 );
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
    // Geburtsdatum aus Request oder User-Meta
    $dob_param = sanitize_text_field( $req->get_param('dob') );
    if ( ! $dob_param ) {
        $raw_dob = get_user_meta( $user->ID, 'billing_geburtsdatum', true );
        if ( $raw_dob && preg_match('/^(\d{2})(\d{2})(\d{4})$/', $raw_dob, $m) ) {
            $dob_param = $m[1] . '.' . $m[2] . '.' . $m[3];
        } elseif ( $raw_dob && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw_dob, $m) ) {
            $dob_param = $m[3] . '.' . $m[2] . '.' . $m[1];
        } else {
            $dob_param = (string) $raw_dob;
        }
    }

    $inserted = $wpdb->insert( $table, [
        'user_id'       => $user->ID,
        'fullname'      => $fullname,
        'username'      => $username,
        'signed_at'     => current_time( 'mysql' ),
        'signed_at_ts'  => $signed_ts,
        'geburtsdatum'  => $dob_param,
        'signature_png' => $sig,
        'ip_address'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
    ] );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Fehler beim Speichern.', [ 'status' => 500 ] );
    }

    update_user_meta( $user->ID, '_oemm_ha_dob', $dob_param );
    // Redesign-Hinweis wegräumen sobald neu unterschrieben
    delete_user_meta( $user->ID, '_oemm_ha_redesign_notice' );

    // HTML-Dokument als Download speichern
    oemm_xxvi_save_agreement( $user->ID, $fullname, $username, $signed_ts, $sig, $dob_param );

    return rest_ensure_response( [
        'success'  => true,
        'redirect' => wc_get_account_endpoint_url( 'omm-dashboard' ),
    ] );
}

/* ---------------------------------------------------------------
   AGREEMENT DOKUMENT SPEICHERN + DOWNLOAD ANLEGEN
--------------------------------------------------------------- */
function oemm_xxvi_save_agreement( $user_id, $fullname, $username, $signed_ts, $sig_png, $dob = '' ) {
    $upload   = wp_upload_dir();
    $dir      = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '.htaccess', 'deny from all' . PHP_EOL );
        file_put_contents( $dir . 'index.php', '<?php // silence' . PHP_EOL );
    }
    $filename = 'ha-' . $user_id . '.pdf';
    $filepath = $dir . $filename;
    oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob );

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
    // Sig-PNG + DOB fuer spatere Regenerierung aufbewahren
    if ( ! empty( $sig_png ) ) update_user_meta( $user_id, '_oemm_ha_sig_png', $sig_png );
    if ( ! empty( $dob ) )     update_user_meta( $user_id, '_oemm_ha_dob', $dob );
}

// Sicherer Download-Handler
add_action( 'init', 'oemm_xxvi_download_handler', 1 );
function oemm_xxvi_download_handler() {
    if ( empty( $_GET['oemm_dl'] ) ) return;
    if ( ! is_user_logged_in() ) { wp_die( 'Bitte einloggen.' ); }

    $user_id  = absint( $_GET['uid'] ?? 0 );
    $token    = sanitize_text_field( $_GET['token'] ?? '' );
    $is_admin = current_user_can( 'manage_options' );

    // Nur eigener User ODER Admin darf runterladen
    if ( ! $is_admin && get_current_user_id() !== $user_id ) wp_die( 'Kein Zugriff.' );

    $filepath = get_user_meta( $user_id, '_oemm_ha_dl_file', true );
    $expected = hash( 'sha256', AUTH_KEY . $user_id . basename( $filepath ) );

    // Token-Prüfung: Admin darf auch ohne Token (direkter Admin-Download)
    if ( ! $is_admin && ! hash_equals( $expected, $token ) ) wp_die( 'Ungültiger Token.' );

    // Auto-Regenerierung: falls Datei fehlt ODER noch .html (alte Version)
    if ( ! file_exists( $filepath ) || substr( $filepath, -5 ) === '.html' || filesize( $filepath ) < 500 ) {
        $u         = get_user_by( 'id', $user_id );
        $fullname  = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
        $username  = $u->user_login;
        $signed_ts  = get_user_meta( $user_id, '_oemm_ha_signed_ts', true ) ?: date('d.m.Y H:i:s');
        $sig_png    = get_user_meta( $user_id, '_oemm_ha_sig_png',  true ) ?: '';
        $dob_regen  = get_user_meta( $user_id, '_oemm_ha_dob', true ) ?: '';
        $upload    = wp_upload_dir();
        $dir       = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
        wp_mkdir_p( $dir );
        $filepath  = $dir . 'ha-' . $user_id . '.pdf';
        oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob_regen );
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

/* ---------------------------------------------------------------
   PDF GENERIERUNG via wkhtmltopdf (HTML-basiert, volle UTF-8 + Emojis)
--------------------------------------------------------------- */
function oemm_xxvi_generate_pdf_html( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob = '' ) {
    $logo_path = OEMM_XXVI_PATH . 'assets/rocky-logo-pdf.png';
    $logo_url  = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode( file_get_contents($logo_path) ) : '';
    $dob_pdf   = $dob ? ' (geb. ' . htmlspecialchars($dob, ENT_QUOTES) . ')' : '';
    $dob_line  = $dob ? ' <span style="color:rgba(255,255,255,0.5);font-size:12px;">(geb. ' . htmlspecialchars($dob, ENT_QUOTES) . ')</span>' : '';
    $sig_html  = (!empty($sig_png) && strpos($sig_png,'data:image/png;base64,')===0)
        ? '<img src="'.htmlspecialchars($sig_png, ENT_QUOTES).'" style="max-width:100%;max-height:120px;display:block;margin:0 auto;">'
        : '<p style="color:#aaa;text-align:center;">Keine Unterschrift</p>';
    $fn_esc  = htmlspecialchars($fullname,  ENT_QUOTES);
    $un_esc  = htmlspecialchars($username,  ENT_QUOTES);
    $ts_esc  = htmlspecialchars($signed_ts, ENT_QUOTES);

    $logo_img = $logo_url ? '<img src="'.$logo_url.'" style="width:50px;height:50px;" alt="Logo">' : '';

    $html = <<<HATML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#0d1b3e;color:#fff;margin:0;padding:20px;}
.wrap{max-width:750px;margin:0 auto;}
h4{font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:rgba(255,255,255,0.7);margin:14px 0 6px;}
.body-text{font-size:12px;color:rgba(255,255,255,0.6);line-height:1.7;margin-bottom:10px;}
.warning{background:rgba(240,192,64,0.1);border:1px solid rgba(240,192,64,0.3);border-radius:8px;padding:10px 14px;margin:8px 0;font-size:12px;color:#f0c040;}
.footer{background:#0f3460;border-radius:10px;padding:10px 18px;font-size:11px;color:rgba(255,255,255,0.3);text-align:center;margin-top:20px;}
</style>
</head>
<body>
<div class="wrap">

<table style="width:100%;border-collapse:collapse;background:#0f3460;border-radius:12px;margin-bottom:18px;" cellpadding="0" cellspacing="0">
<tr>
  <td style="width:60px;vertical-align:middle;padding:18px 0 18px 18px;">{$logo_img}</td>
  <td style="vertical-align:middle;padding:18px;">
    <div style="font-size:9px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,0.3);">&#214;tztaler Moped Verein</div>
    <div style="font-size:22px;font-weight:bold;color:#fff;margin:2px 0;">Haftungsausschluss</div>
    <div style="font-size:12px;color:#f0c040;">&#214;MM XXVI 2026 &mdash; mopedmarathon.at</div>
  </td>
</tr>
</table>

<table style="width:100%;border-collapse:collapse;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;margin-bottom:16px;" cellpadding="0" cellspacing="0">
<tr><td style="padding:12px 18px;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <tr>
      <td style="color:rgba(255,255,255,0.4);font-size:11px;text-transform:uppercase;width:110px;padding:3px 0;">Teilnehmer</td>
      <td style="color:rgba(255,255,255,0.7);padding:3px 0;"><strong style="color:#fff;">{$fn_esc}</strong>{$dob_pdf} &nbsp;<span style="color:rgba(255,255,255,0.3);font-size:11px;">@{$un_esc}</span></td>
    </tr>
    <tr>
      <td style="color:rgba(255,255,255,0.4);font-size:11px;text-transform:uppercase;padding:3px 0;">Veranstalter</td>
      <td style="color:rgba(255,255,255,0.7);padding:3px 0;"><strong style="color:#fff;">&#214;tztaler Moped Verein</strong>, S&ouml;lden, Tirol</td>
    </tr>
    <tr>
      <td style="color:rgba(255,255,255,0.4);font-size:11px;text-transform:uppercase;padding:3px 0;">Unterzeichnet</td>
      <td style="padding:3px 0;"><strong style="color:#4ade80;">{$ts_esc}</strong></td>
    </tr>
  </table>
</td></tr>
</table>

<div style="text-align:center;font-size:48px;margin:10px 0;">&#10084;&#65039;</div>
<div class="body-text" style="text-align:center;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.07);">
  <strong style="color:rgba(255,255,255,0.85);font-size:13px;">Vielen Dank f&uuml;r dein Vertrauen und herzlich Willkommen im Verein!</strong><br><br>
  Du kannst dir sicher sein, dass wir uns wieder die allergr&ouml;&szlig;te M&uuml;he geben werden, um f&uuml;r dich und die 50ccm Community ein gro&szlig;artiges Treffen auf die Beine zu stellen. Das ist und bleibt unser Qualit&auml;tsanspruch!<br><br>
  Nochmals vielen Dank f&uuml;r dein Vertrauen und beste Gr&uuml;&szlig;e aus S&ouml;lden,
  <strong style="color:rgba(255,255,255,0.7);">dein &Ouml;tztaler Moped Verein</strong>
</div>

<h4>Allgemeine Gesch&auml;ftsbedingungen des &Ouml;tztaler Moped Vereins</h4>
<p style="font-size:11px;color:rgba(255,255,255,0.3);margin-bottom:10px;">Letztes Update: Juni 2026</p>
<div class="body-text">Der Teilnehmer <strong style="color:rgba(255,255,255,0.85);">{$fn_esc}</strong>{$dob_pdf} (@{$un_esc}) erkl&auml;rt seinen Beitritt zum &Ouml;tztaler Moped Verein, sp&auml;ter &bdquo;&Ouml;MV&ldquo; genannt, mit Kauf der Mitgliedschaft, als ordentliches Mitglied ohne Stimmrecht f&uuml;r die Dauer bis Ende September des jeweiligen Jahres.</div>
<div class="body-text">Mit der Bezahlung des Mitgliedsbeitrags ist die Teilnahme an Veranstaltungen die durch den &Ouml;MV organisiert werden, allen voran der &Ouml;tztaler Mopedmarathon, m&ouml;glich. Diese Ausflugsfahrt erfolgt nicht gewerblich, ist kein Rennen und dient der Mitgliederwerbung bzw. zur Popularisierung der Mopedliebhaberei.</div>
<div class="body-text">Dieser Mitgliedsbeitrag wird f&uuml;r die reibungslose Durchf&uuml;hrung des &bdquo;&Ouml;MM&ldquo; verwendet, flie&szlig;t ungek&uuml;rzt und unmittelbar dem &Ouml;tztaler Moped Verein zu, der damit alleiniger Vertragspartner des &bdquo;Teilnehmers&ldquo; in den Allgemeinen Gesch&auml;ftsbedingungen und Haftungsausschluss ist.</div>
<div class="body-text">Alle Vereinsmitglieder, welche sich an der Organisation des &Ouml;MM beteiligen, handeln als Vertreter des &Ouml;MV und damit nicht in eigenem Namen.</div>

<h4>Teilnahmebedingungen &amp; Haftungsausschluss</h4>
<div class="body-text">Die Teilnahme ist nur bei Vollj&auml;hrigkeit gestattet. Bei Fahrern unter 18 Jahren muss vor dem Start die schriftliche Einwilligung der Eltern an den &Ouml;MV &uuml;bergeben werden.</div>
<div class="body-text">Mir, dem &bdquo;Teilnehmer&ldquo;, ist bewusst, dass eine derartige Ausflugsfahrt mit gewissen Risiken behaftet ist. Ich best&auml;tige daher ausdr&uuml;cklich, dass f&uuml;r Verletzungen und Sch&auml;den jeglicher Art dem &Ouml;MV keinerlei Schuld zuweisbar ist und ich den &Ouml;MV schad- und klaglos halte. Ich bin im Besitz einer g&uuml;ltigen Haftpflichtversicherung und pers&ouml;nlichen Unfallversicherung.</div>
<div class="body-text">Weiters verpflichte ich mich, mich an die Rundfahrt- und Sicherheitsvorschriften des &Ouml;MV zu halten.</div>
<div class="warning">&#9888;&#65039; Ich best&auml;tige hiermit ausdr&uuml;cklich, dass ich bei der verbindlichen Fahrerbesprechung des &Ouml;MV pers&ouml;nlich anwesend sein werde.</div>
<div class="body-text">Ich best&auml;tige, dass meine Ausr&uuml;stung keine M&auml;ngel aufweist. Bei augenscheinlichen M&auml;ngeln kann ich jederzeit von der Teilnahme ausgeschlossen werden.</div>
<div class="body-text">Es werden auf der Strecke Geschwindigkeits- und Alkoholkontrollen erhoben. Der Teilnehmer erkl&auml;rt ausdr&uuml;cklich, weder unter Alkohol- noch Drogeneinfluss zu stehen.</div>

<h4>Bild- &amp; Tonrechte</h4>
<div class="body-text">Der &Ouml;MV oder von ihm autorisierte Dritte sind berechtigt, Audio- und Videoaufzeichnungen sowie Fotos des &Ouml;MM f&uuml;r jegliche Zwecke in allen Medien weltweit zu verwenden.</div>

<h4>R&uuml;ckgaberecht &amp; Streitbeilegung</h4>
<div class="body-text">R&uuml;ckgaberecht laut Fernabsatzgesetz: 14 Tage ohne Angabe von Gr&uuml;nden. Gerichtsstand: Innsbruck.<br>
Information gem. &sect;19 Abs 3 AStG: Wir sind weder verpflichtet noch bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</div>

<table style="width:100%;border-collapse:collapse;border:1px solid rgba(255,255,255,0.1);border-radius:10px;overflow:hidden;margin-top:16px;" cellpadding="0" cellspacing="0">
<tr><td style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.08);">
  <div style="font-size:13px;font-weight:bold;color:rgba(255,255,255,0.8);margin-bottom:2px;">&#9997;&#65039; Digitale Unterschrift</div>
  <div style="font-size:11px;color:rgba(255,255,255,0.3);">Rechtsverbindlich gem&auml;&szlig; EU-Verordnung 910/2014 (eIDAS)</div>
</td></tr>
<tr><td style="background:#ffffff;padding:10px;text-align:center;">{$sig_html}</td></tr>
<tr><td style="padding:10px 18px;border-top:1px solid rgba(255,255,255,0.08);">
  <table style="width:100%;border-collapse:collapse;font-size:11px;color:rgba(255,255,255,0.4);">
  <tr>
    <td>&#128100; {$fn_esc}{$dob_pdf}</td>
    <td style="text-align:center;">@{$un_esc}</td>
    <td style="text-align:right;">&#128336; {$ts_esc}</td>
  </tr>
  </table>
</td></tr>
</table>

<div class="footer">&Ouml;tztaler Moped Verein &mdash; &Ouml;MM XXVI 2026 &mdash; <span style="color:#f0c040;">mopedmarathon.at</span> &mdash; Unterzeichnet: {$ts_esc}</div>
</div>
</body>
</html>
HATML;

    $tmp_html = tempnam( sys_get_temp_dir(), 'oemm_pdf_' ) . '.html';
    file_put_contents( $tmp_html, $html );

    $cmd = escapeshellcmd('/usr/bin/wkhtmltopdf')
        . ' --page-size A4'
        . ' --margin-top 8mm'
        . ' --margin-bottom 8mm'
        . ' --margin-left 10mm'
        . ' --margin-right 10mm'
        . ' --encoding UTF-8'
        . ' --no-stop-slow-scripts'
        . ' --enable-local-file-access'
        . ' --background'
        . ' --disable-smart-shrinking'
        . ' ' . escapeshellarg($tmp_html)
        . ' ' . escapeshellarg($filepath)
        . ' 2>/dev/null';

    exec($cmd, $out, $rc);
    unlink($tmp_html);

    if ( $rc !== 0 || ! file_exists($filepath) || filesize($filepath) < 500 ) {
        if ( ! class_exists('FPDF') ) require_once OEMM_XXVI_PATH . 'lib/fpdf.php';
        $pdf = new FPDF_OMM('P','mm','A4');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica','B',14);
        $pdf->Cell(0,10, iconv('UTF-8','ISO-8859-1//TRANSLIT','Haftungsausschluss OeMM 2026'),0,1,'C');
        $pdf->SetFont('Helvetica','',10);
        $pdf->Cell(0,8, iconv('UTF-8','ISO-8859-1//TRANSLIT',$fullname . ' (@' . $username . ')'),0,1);
        $pdf->Cell(0,8, iconv('UTF-8','ISO-8859-1//TRANSLIT','Unterzeichnet: ' . $signed_ts),0,1);
        $pdf->Output('F', $filepath);
    }
}

function oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob = '' ) {
    // wkhtmltopdf: HTML -> PDF, volle UTF-8/Emoji-Unterstützung
    $wk = '/usr/bin/wkhtmltopdf';
    if ( file_exists($wk) ) {
        oemm_xxvi_generate_pdf_html( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob );
        return;
    }
    // Fallback: FPDF (Latin-1, keine Umlaute/Emojis)
    if ( ! class_exists('FPDF') ) {
        require_once OEMM_XXVI_PATH . 'lib/fpdf.php';
    }
    $pdf = new FPDF_OMM('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetMargins(20, 20, 20);

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
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->SetXY(46, 7);
    $pdf->Cell(0, 8, 'OTZTALER MOPED VEREIN', 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(200, 215, 240);
    $pdf->SetX(46);
    $pdf->Cell(0, 5, 'AGB & Haftungsausschluss - Oetztaler Moped Marathon XXVI / 2026', 0, 1, 'L');
    $pdf->SetX(46);
    $pdf->SetTextColor(240, 192, 64);
    $pdf->Cell(0, 5, 'mopedmarathon.at', 0, 1, 'L');

    $pdf->SetY(42);

    // ========== META BOX ==========
    $dob_line_h = $dob ? 5 : 0;
    $pdf->SetFillColor(235, 241, 250);
    $pdf->SetDrawColor(200, 215, 240);
    $pdf->Rect(20, 42, 170, 26 + $dob_line_h, 'FD');
    $pdf->SetXY(25, 45);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(32, 5, 'Teilnehmer:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $dob_suffix = $dob ? '  (geb. ' . iconv('UTF-8','ISO-8859-1//TRANSLIT', $dob) . ')' : '';
    $pdf->Cell(0, 5, $fullname . $dob_suffix . '  (@' . $username . ')', 0, 1);
    $pdf->SetX(25);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->Cell(32, 5, 'Veranstalter:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 5, 'Oetztaler Moped Verein, Soelden, Tirol', 0, 1);
    $pdf->SetX(25);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->Cell(32, 5, 'Unterzeichnet am:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 5, $signed_ts, 0, 1);

    $pdf->SetY(74);

    // Meta-Box
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetX(20);
    $pdf->SetFillColor(235, 241, 250);
    $pdf->Rect(20, 34, 170, 24, 'F');
    $pdf->SetXY(24, 37);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(28, 5, 'Teilnehmer:', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, $fullname . ' (@' . $username . ')', 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(28, 5, 'Veranstalter:', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, 'Oetztaler Moped Verein, Soelden, Tirol', 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(28, 5, 'Unterzeichnet:', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, $signed_ts, 0, 1);
    $pdf->SetY(64);

    // AGB Text
    $pdf->SetTextColor(40, 40, 40);

    // HA-Text: exakt gleicher Wortlaut wie HTML-View (Herzversion)
    // Umlaute per iconv am Ende konvertiert - Quellcode bleibt UTF-8 lesbar
    $fn = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $fullname);
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
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(15, 52, 96);
        $pdf->SetX(20);
        $pdf->Cell(0, 6, strtoupper(iconv('UTF-8','ISO-8859-1//TRANSLIT',$title)), 0, 1);
        $pdf->SetDrawColor(15, 52, 96);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        foreach ( $paragraphs as $p ) {
            $pdf->SetX(20);
            $pdf->MultiCell(170, 5, iconv('UTF-8','ISO-8859-1//TRANSLIT',$p), 0, 'L');
            $pdf->Ln(1);
        }
        $pdf->Ln(3);
    }

    // ========== UNTERSCHRIFT SEKTION ==========
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 9);
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
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetX(30);
    $pdf->Cell(150, 5, $fullname . '  |  ' . $signed_ts, 0, 1, 'C');
    $pdf->SetX(30);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->Cell(150, 5, 'Rechtsverbindliche digitale Unterschrift gemaess EU-Verordnung 910/2014 (eIDAS)', 0, 1, 'C');

    // ========== FOOTER ==========
    $pdf->SetY(-18);
    $pdf->SetFillColor(15, 52, 96);
    $pdf->Rect(0, $pdf->GetY(), 210, 18, 'F');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(180, 200, 230);
    $pdf->SetX(20);
    $pdf->Cell(0, 9, 'Oetztaler Moped Verein - OeMM XXVI 2026  |  mopedmarathon.at  |  Elektronisch unterzeichnet: ' . $signed_ts, 0, 0, 'C');

    $pdf->Output('F', $filepath);
}

// FPDF_OMM: FPDF Subklasse mit RoundedRect
if ( ! class_exists('FPDF') ) {
    require_once OEMM_XXVI_PATH . 'lib/fpdf.php';
}
if ( ! class_exists('FPDF_OMM') ) {
    class FPDF_OMM extends FPDF {
        public function RoundedRect($x, $y, $w, $h, $r, $style='') {
            $k=$this->k; $hp=$this->h;
            if($style=='F') $op='f';
            elseif($style=='FD'||$style=='DF') $op='B';
            else $op='S';
            $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
            $xc=$x+$w-$r; $yc=$y+$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-$y)*$k));
            $this->_Arc3($xc,$yc,$r,90,0);
            $xc=$x+$w-$r; $yc=$y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
            $this->_Arc3($xc,$yc,$r,0,-90);
            $xc=$x+$r; $yc=$y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
            $this->_Arc3($xc,$yc,$r,-90,-180);
            $xc=$x+$r; $yc=$y+$r;
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
            $this->_Arc3($xc,$yc,$r,180,90);
            $this->_out($op);
        }
        private function _Arc3($x1,$y1,$r,$a1,$a2) {
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
    $dob_r     = get_user_meta( $uid, '_oemm_ha_dob', true ) ?: '';

    $upload   = wp_upload_dir();
    $dir      = trailingslashit( $upload['basedir'] ) . 'oemm-agreements/';
    wp_mkdir_p( $dir );
    $filename = 'ha-' . $uid . '.pdf';
    $filepath = $dir . $filename;

    oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png, $dob_r );

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
    add_submenu_page(
        'oemm-xxvi-admin',
        'Timetable',
        '📅 Timetable',
        'manage_options',
        'oemm-xxvi-timetable',
        'oemm_xxvi_timetable_admin_page'
    );
}

// ---------------------------------------------------------------
// TIMETABLE ADMIN
// ---------------------------------------------------------------
function oemm_xxvi_timetable_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $tt = $wpdb->prefix . OEMM_XXVI_TIMETABLE;

    // Tabelle anlegen falls noch nicht da (für bestehende Installs)
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE IF NOT EXISTS {$tt} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_date  DATE NOT NULL,
        start_time  TIME NOT NULL,
        end_time    TIME DEFAULT NULL,
        title       VARCHAR(255) NOT NULL,
        subtitle    VARCHAR(255) DEFAULT NULL,
        icon        VARCHAR(20) DEFAULT '📅',
        category    VARCHAR(50) DEFAULT 'programm',
        sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_date_time (event_date, start_time)
    ) {$charset};" );

    // Initialdaten falls leer
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tt}" );
    if ( $count === 0 ) {
        $entries = [
            [ '2026-06-25', '16:00:00', '22:00:00', 'Ausgabe der Startpakete', 'Eventgelände Sölden', '📦', 'logistik', 10 ],
            [ '2026-06-26', '10:00:00', '22:00:00', 'Ausgabe der Startpakete', 'Eventgelände Sölden', '📦', 'logistik', 20 ],
            [ '2026-06-26', '12:30:00', null,        'Start Gletscherausfahrt', null, '🏍', 'rennen', 30 ],
            [ '2026-06-26', '19:30:00', null,        'Verbindliche Fahrerbesprechung', null, '📋', 'programm', 40 ],
            [ '2026-06-26', '23:59:00', null,        'Veranstaltungsende', null, '🎉', 'programm', 50 ],
            [ '2026-06-27', '06:00:00', null,        'Start Ötztaler Mopedmarathon XXVI', null, '🏁', 'rennen', 60 ],
            [ '2026-06-27', '14:00:00', null,        'Ankunft der ersten Rider im Ziel', 'ca. 14:00 Uhr', '🥇', 'rennen', 70 ],
            [ '2026-06-27', '17:30:00', null,        'Ankunft der letzten Rider im Ziel', null, '🏍', 'rennen', 80 ],
            [ '2026-06-27', '21:00:00', null,        'Siegerehrung', null, '🏆', 'programm', 90 ],
            [ '2026-06-27', '23:59:00', null,        'Veranstaltungsende', null, '🎉', 'programm', 100 ],
        ];
        foreach ( $entries as $e ) {
            $wpdb->insert( $tt, [ 'event_date'=>$e[0], 'start_time'=>$e[1], 'end_time'=>$e[2],
                'title'=>$e[3], 'subtitle'=>$e[4], 'icon'=>$e[5], 'category'=>$e[6], 'sort_order'=>$e[7], 'is_active'=>1 ] );
        }
    }

    $action = $_GET['tt_action'] ?? '';
    $msg = '';

    // Löschen
    if ( $action === 'delete' && isset( $_GET['id'] ) && check_admin_referer( 'oemm_tt_delete_' . (int)$_GET['id'] ) ) {
        $wpdb->delete( $tt, [ 'id' => (int)$_GET['id'] ] );
        $msg = 'Eintrag gelöscht.';
    }
    // Toggle aktiv
    if ( $action === 'toggle' && isset( $_GET['id'] ) && check_admin_referer( 'oemm_tt_toggle_' . (int)$_GET['id'] ) ) {
        $cur = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$tt} WHERE id=%d", (int)$_GET['id'] ) );
        $wpdb->update( $tt, [ 'is_active' => $cur ? 0 : 1 ], [ 'id' => (int)$_GET['id'] ] );
        $msg = $cur ? 'Eintrag deaktiviert.' : 'Eintrag aktiviert.';
    }
    // Speichern (neu oder edit)
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['oemm_tt_nonce'] ) && wp_verify_nonce( $_POST['oemm_tt_nonce'], 'oemm_tt_save' ) ) {
        $data = [
            'event_date' => sanitize_text_field( $_POST['event_date'] ),
            'start_time' => sanitize_text_field( $_POST['start_time'] ),
            'end_time'   => !empty( $_POST['end_time'] ) ? sanitize_text_field( $_POST['end_time'] ) : null,
            'title'      => sanitize_text_field( $_POST['title'] ),
            'subtitle'   => sanitize_text_field( $_POST['subtitle'] ),
            'icon'       => sanitize_text_field( $_POST['icon'] ),
            'category'   => sanitize_text_field( $_POST['category'] ),
            'sort_order' => (int)$_POST['sort_order'],
            'is_active'  => 1,
        ];
        if ( !empty( $_POST['edit_id'] ) ) {
            $wpdb->update( $tt, $data, [ 'id' => (int)$_POST['edit_id'] ] );
            $msg = 'Eintrag aktualisiert.';
        } else {
            $wpdb->insert( $tt, $data );
            $msg = 'Eintrag hinzugefügt.';
        }
    }

    $edit_item = null;
    if ( $action === 'edit' && isset( $_GET['id'] ) ) {
        $edit_item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tt} WHERE id=%d", (int)$_GET['id'] ) );
    }

    $items = $wpdb->get_results( "SELECT * FROM {$tt} ORDER BY event_date, start_time, sort_order" );
    $page_url = admin_url( 'admin.php?page=oemm-xxvi-timetable' );
    ?>
    <div class="wrap">
    <h1>📅 ÖMM Timetable</h1>
    <?php if ( $msg ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>'; ?>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

    <!-- LISTE -->
    <div style="flex:1;min-width:400px;">
    <table class="wp-list-table widefat fixed striped">
    <thead><tr>
        <th width="34">Icon</th>
        <th width="90">Datum</th>
        <th width="110">Zeit</th>
        <th>Titel</th>
        <th width="90">Kategorie</th>
        <th width="50">Aktiv</th>
        <th width="90">Aktionen</th>
    </tr></thead>
    <tbody>
    <?php
    $prev_date = '';
    foreach ( $items as $item ) :
        $date_label = date('D d.m.Y', strtotime($item->event_date));
        $date_label = str_replace(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],['Mo','Di','Mi','Do','Fr','Sa','So'], $date_label);
        if ( $item->event_date !== $prev_date ) {
            echo '<tr><td colspan="7" class="column-primary" style="font-weight:700;background:#f0f0f1;border-top:2px solid #c3c4c7;">' . esc_html($date_label) . '</td></tr>';
            $prev_date = $item->event_date;
        }
        $time_str = substr($item->start_time, 0, 5);
        if ( $item->end_time ) $time_str .= ' – ' . substr($item->end_time, 0, 5);
    ?>
    <tr <?php echo !$item->is_active ? 'style="opacity:0.5;"' : ''; ?>>
        <td style="font-size:18px;"><?php echo esc_html($item->icon); ?></td>
        <td><?php echo esc_html($date_label); ?></td>
        <td><code><?php echo esc_html($time_str); ?></code></td>
        <td>
            <strong><?php echo esc_html($item->title); ?></strong>
            <?php if($item->subtitle) echo '<br><span class="description">'.esc_html($item->subtitle).'</span>'; ?>
        </td>
        <td><span class="dashicons-before"><?php echo esc_html(ucfirst($item->category)); ?></span></td>
        <td style="text-align:center"><?php echo $item->is_active ? '<span style="color:green">&#10003;</span>' : '<span style="color:#aaa">&mdash;</span>'; ?></td>
        <td>
            <a href="<?php echo wp_nonce_url( $page_url . '&tt_action=edit&id=' . $item->id, 'oemm_tt_edit_' . $item->id ); ?>" class="button button-small">Bearbeiten</a>
            <a href="<?php echo wp_nonce_url( $page_url . '&tt_action=toggle&id=' . $item->id, 'oemm_tt_toggle_' . $item->id ); ?>" class="button button-small"><?php echo $item->is_active ? 'Deaktivieren' : 'Aktivieren'; ?></a>
            <a href="<?php echo wp_nonce_url( $page_url . '&tt_action=delete&id=' . $item->id, 'oemm_tt_delete_' . $item->id ); ?>" class="button button-small" onclick="return confirm('Wirklich löschen?')" style="color:#b32d2e;">Löschen</a>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>

    <!-- FORMULAR -->
    <div style="min-width:280px;max-width:340px;">
    <div class="postbox">
    <div class="postbox-header"><h2 class="hndle"><?php echo $edit_item ? 'Eintrag bearbeiten' : 'Neuer Eintrag'; ?></h2></div>
    <div class="inside">
    <form method="post" action="<?php echo esc_url($page_url); ?>">
        <?php wp_nonce_field( 'oemm_tt_save', 'oemm_tt_nonce' ); ?>
        <?php if ($edit_item) echo '<input type="hidden" name="edit_id" value="' . (int)$edit_item->id . '">'; ?>

        <p><label><strong>Datum</strong><br>
        <input type="date" name="event_date" value="<?php echo esc_attr($edit_item->event_date ?? '2026-06-26'); ?>" required class="widefat"></label></p>

        <p><label><strong>Startzeit</strong><br>
        <input type="time" name="start_time" value="<?php echo esc_attr($edit_item ? substr($edit_item->start_time,0,5) : ''); ?>" required class="widefat"></label></p>

        <p><label><strong>Endzeit</strong> <span class="description">(optional)</span><br>
        <input type="time" name="end_time" value="<?php echo esc_attr($edit_item && $edit_item->end_time ? substr($edit_item->end_time,0,5) : ''); ?>" class="widefat"></label></p>

        <p><label><strong>Titel</strong><br>
        <input type="text" name="title" value="<?php echo esc_attr($edit_item->title ?? ''); ?>" required class="widefat"></label></p>

        <p><label><strong>Untertitel</strong> <span class="description">(optional)</span><br>
        <input type="text" name="subtitle" value="<?php echo esc_attr($edit_item->subtitle ?? ''); ?>" class="widefat"></label></p>

        <p><label><strong>Icon</strong> <span class="description">(Emoji)</span><br>
        <input type="text" name="icon" value="<?php echo esc_attr($edit_item->icon ?? '🎵'); ?>" style="width:60px;font-size:20px;"></label></p>

        <p><label><strong>Kategorie</strong><br>
        <select name="category" class="widefat">
        <?php foreach(['programm','rennen','logistik','musik','sonstiges'] as $cat) :
            $sel = ($edit_item && $edit_item->category===$cat) ? 'selected' : ''; ?>
        <option value="<?php echo $cat; ?>" <?php echo $sel; ?>><?php echo ucfirst($cat); ?></option>
        <?php endforeach; ?>
        </select></label></p>

        <p><label><strong>Reihenfolge</strong><br>
        <input type="number" name="sort_order" value="<?php echo esc_attr($edit_item->sort_order ?? 50); ?>" style="width:80px"></label></p>

        <?php submit_button( $edit_item ? 'Speichern' : 'Hinzufügen' ); ?>
        <?php if ($edit_item) : ?>
        <p><a href="<?php echo esc_url($page_url); ?>" class="button">Abbrechen</a></p>
        <?php endif; ?>
    </form>
    </div></div><!-- /postbox -->
    </div><!-- /formular -->

    </div><!-- /flex -->
    </div><!-- /wrap -->
    <?php
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
            $fn    = trim( $u2->first_name . ' ' . $u2->last_name ) ?: $u2->display_name;
            $ts    = get_user_meta( $uid, '_oemm_ha_signed_ts', true ) ?: date( 'd.m.Y H:i:s' );
            $sig   = get_user_meta( $uid, '_oemm_ha_sig_png', true ) ?: '';
            $dob_a = get_user_meta( $uid, '_oemm_ha_dob', true ) ?: '';
            $dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'oemm-agreements/';
            wp_mkdir_p( $dir );
            $fp  = $dir . 'ha-' . $uid . '.pdf';
            oemm_xxvi_generate_pdf( $fp, $fn, $u2->user_login, $ts, $sig, $dob_a );
            $dlurl = add_query_arg( [ 'oemm_dl' => 1, 'uid' => $uid, 'token' => hash( 'sha256', AUTH_KEY . $uid . basename($fp) ) ], home_url('/') );
            update_user_meta( $uid, '_oemm_ha_dl_file', $fp );
            update_user_meta( $uid, '_oemm_ha_dl_url', $dlurl );
        }
        wp_safe_redirect( add_query_arg( ['tab' => 'ha', 'regen_ok' => $uid], admin_url('admin.php?page=oemm-xxvi-admin') ) ); exit;
    }

    // HA Reset einzelner User
    if ( ! empty( $_GET['ha_reset'] ) && check_admin_referer( 'oemm_ha_reset_' . (int) $_GET['ha_reset'] ) ) {
        $uid = absint( $_GET['ha_reset'] );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . OEMM_XXVI_TABLE, [ 'user_id' => $uid ], [ '%d' ] );
        foreach ( ['_oemm_ha_signed_ts','_oemm_ha_dl_file','_oemm_ha_dl_url','_oemm_ha_sig_png','_oemm_ha_dob'] as $mk ) {
            delete_user_meta( $uid, $mk );
        }
        wp_safe_redirect( add_query_arg( ['tab' => 'ha', 'reset_ok' => $uid], admin_url('admin.php?page=oemm-xxvi-admin') ) ); exit;
    }

    // HA Reset ALLE User
    if ( ! empty( $_POST['ha_reset_all'] ) && check_admin_referer( 'oemm_ha_reset_all', 'ha_reset_all_nonce' ) ) {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . OEMM_XXVI_TABLE );
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('_oemm_ha_signed_ts','_oemm_ha_dl_file','_oemm_ha_dl_url','_oemm_ha_sig_png','_oemm_ha_dob')" );
        wp_safe_redirect( add_query_arg( ['tab' => 'ha', 'reset_all_ok' => 1], admin_url('admin.php?page=oemm-xxvi-admin') ) ); exit;
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
        <a href="<?php echo esc_url(add_query_arg('tab','ha',$page_url)); ?>" class="nav-tab <?php echo $tab==='ha'?'nav-tab-active':''; ?>">📝 Haftungsausschlüsse</a>
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

    <?php elseif ( $tab === 'ha' ) : ?>
    <!-- ==================== HAFTUNGSAUSSCHLUESSE ==================== -->
    <h2>📝 Haftungsausschlüsse ÖMM <?php echo $event_year; ?></h2>
    <?php
    if ( ! empty($_GET['reset_ok']) )    { $ru=get_user_by('id',absint($_GET['reset_ok']));    echo '<div class="notice notice-success is-dismissible"><p>HA von <strong>'.esc_html($ru->display_name??'User').'</strong> zurückgesetzt.</p></div>'; }
    if ( ! empty($_GET['reset_all_ok']) ) echo '<div class="notice notice-warning is-dismissible"><p><strong>Alle</strong> HA-Unterschriften zurückgesetzt.</p></div>';
    if ( ! empty($_GET['regen_ok']) )    { $ru=get_user_by('id',absint($_GET['regen_ok']));    echo '<div class="notice notice-success is-dismissible"><p>PDF für <strong>'.esc_html($ru->display_name??'User').'</strong> neu generiert.</p></div>'; }

    $ha_table       = $wpdb->prefix . OEMM_XXVI_TABLE;
    $parts_table    = $wpdb->prefix . 'oemm_participants';
    // Nur aktive Teilnehmer 2026 (bezahlt = in oemm_participants)
    $participant_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT customer_id FROM {$parts_table} WHERE event_year = %d ORDER BY startnumber+0 ASC",
        $event_year
    ) );
    $all_customers  = empty($participant_ids) ? [] : get_users( [
        'include'  => $participant_ids,
        'orderby'  => 'display_name',
        'number'   => -1,
    ] );
    $signed_rows    = $wpdb->get_results( "SELECT user_id, fullname, username, geburtsdatum, signed_at_ts FROM {$ha_table} ORDER BY signed_at DESC" );
    $signed_map     = [];
    foreach ( $signed_rows as $row ) $signed_map[$row->user_id] = $row;
    // Nur Unterschriften von aktiven Teilnehmern zählen
    $participant_id_set = array_flip( array_map('intval', $participant_ids) );
    $signed_count   = 0;
    $signed_rows_filtered = [];
    foreach ( $signed_rows as $row ) {
        if ( isset($participant_id_set[(int)$row->user_id]) ) {
            $signed_count++;
            $signed_rows_filtered[] = $row;
        }
    }
    $unsigned_count = 0;
    foreach ( $all_customers as $c ) { if ( ! isset($signed_map[$c->ID]) ) $unsigned_count++; }
    ?>
    <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
        <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;padding:16px 24px;text-align:center;min-width:120px"><div style="font-size:28px;font-weight:700;color:#155724"><?php echo $signed_count; ?></div><div style="font-size:12px;color:#155724">✅ Unterschrieben</div></div>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px 24px;text-align:center;min-width:120px"><div style="font-size:28px;font-weight:700;color:#856404"><?php echo $unsigned_count; ?></div><div style="font-size:12px;color:#856404">⏳ Noch offen</div></div>
        <div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:16px 24px;text-align:center;min-width:120px"><div style="font-size:28px;font-weight:700;color:#495057"><?php echo count($all_customers); ?></div><div style="font-size:12px;color:#495057">🏍 Teilnehmer <?php echo $event_year; ?></div></div>
    </div>
    <?php
    $total_parts  = count($all_customers);
    $pct          = $total_parts > 0 ? round($signed_count / $total_parts * 100) : 0;
    $bar_color    = $pct < 30 ? '#f87171' : ($pct < 70 ? '#f0c040' : '#4ade80');
    ?>
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:20px;max-width:600px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-size:13px;font-weight:600;color:#333">Fortschritt Haftungsausschlüsse</span>
            <span style="font-size:18px;font-weight:700;color:<?php echo $bar_color; ?>"><?php echo $pct; ?>%</span>
        </div>
        <div style="background:#eee;border-radius:8px;height:14px;overflow:hidden;">
            <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $bar_color; ?>;border-radius:8px;transition:width .4s;"></div>
        </div>
        <div style="font-size:12px;color:#888;margin-top:6px;"><?php echo $signed_count; ?> von <?php echo $total_parts; ?> Teilnehmern haben unterschrieben</div>
    </div>
    <h3>✅ Bereits unterschrieben (<?php echo $signed_count; ?>)</h3>
    <?php if ( $signed_count > 0 ) : ?>
    <table class="widefat striped" style="margin-bottom:30px">
        <thead><tr><th>Name</th><th>Benutzername</th><th>Geburtsdatum</th><th>Unterzeichnet am</th><th>PDF</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ( $signed_rows_filtered as $row ) :
            $dl_file   = get_user_meta( $row->user_id, '_oemm_ha_dl_file', true );
            $pdf_ok    = $dl_file && file_exists($dl_file) && filesize($dl_file) > 500;
            // Admin-Download-URL (kein Token nötig, Admin-Check im Handler)
            $admin_dl_url = add_query_arg( ['oemm_dl' => 1, 'uid' => $row->user_id, 'token' => '', 'adm' => 1], home_url('/') );
            $regen_url = wp_nonce_url( add_query_arg(['tab'=>'ha','regen'=>$row->user_id], $page_url), 'oemm_regen_'.$row->user_id );
            $reset_url = wp_nonce_url( add_query_arg(['tab'=>'ha','ha_reset'=>$row->user_id], $page_url), 'oemm_ha_reset_'.$row->user_id );
        ?>
        <tr>
            <td><strong><?php echo esc_html($row->fullname); ?></strong></td>
            <td style="color:#666">@<?php echo esc_html($row->username); ?></td>
            <td><?php echo $row->geburtsdatum ? esc_html($row->geburtsdatum) : '<span style="color:#ccc">—</span>'; ?></td>
            <td style="font-size:12px"><?php echo esc_html($row->signed_at_ts); ?></td>
            <td><?php if($pdf_ok): ?><a href="<?php echo esc_url($admin_dl_url); ?>" target="_blank" style="background:#0073aa;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:12px;font-weight:600">⬇ PDF</a><?php else: ?><a href="<?php echo esc_url($regen_url); ?>" style="background:#f59e0b;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:12px">🔄 Generieren</a><?php endif; ?></td>
            <td style="white-space:nowrap">
                <?php if($pdf_ok): ?><a href="<?php echo esc_url($regen_url); ?>" style="font-size:12px;color:#0073aa;margin-right:8px">🔄 PDF neu</a><?php endif; ?>
                <a href="<?php echo esc_url($reset_url); ?>" style="font-size:12px;color:#d63638" onclick="return confirm('HA von <?php echo esc_js($row->fullname); ?> zurücksetzen?')"> ❌ Reset</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?><p style="color:#666;margin-bottom:30px">Noch keine Unterschriften.</p><?php endif; ?>

    <h3>⏳ Noch nicht unterschrieben (<?php echo $unsigned_count; ?>)</h3>
    <?php if($unsigned_count>0): ?>
    <table class="widefat striped" style="margin-bottom:30px">
        <thead><tr><th>Name</th><th>Benutzername</th><th>E-Mail</th></tr></thead>
        <tbody>
        <?php foreach($all_customers as $c): if(isset($signed_map[$c->ID])) continue;
            $cname=trim($c->first_name.' '.$c->last_name)?:$c->display_name; ?>
        <tr><td><?php echo esc_html($cname); ?></td><td style="color:#666">@<?php echo esc_html($c->user_login); ?></td><td style="color:#888;font-size:12px"><?php echo esc_html($c->user_email); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="background:#fff5f5;border:2px solid #d63638;border-radius:8px;padding:20px;max-width:600px">
        <h3 style="color:#d63638;margin-top:0">🚨 Alle Haftungsausschlüsse zurücksetzen</h3>
        <p style="color:#555;margin-bottom:16px">Alle Unterschriften zurücksetzen — alle User müssen erneut unterzeichnen.</p>
        <form method="post"><?php wp_nonce_field('oemm_ha_reset_all','ha_reset_all_nonce'); ?>
            <button type="submit" name="ha_reset_all" value="1"
                style="background:#d63638;color:#fff;border:none;padding:10px 22px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer"
                onclick="return confirm('⚠️ ACHTUNG: Alle HA-Unterschriften werden zurückgesetzt!\n\nWirklich fortfahren?')">
                🚨 Alle Unterschriften zurücksetzen
            </button>
        </form>
    </div>

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
