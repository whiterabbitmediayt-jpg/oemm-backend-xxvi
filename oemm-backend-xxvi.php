<?php
/**
 * Plugin Name: ÖMM Backend XXVI
 * Plugin URI:  https://mopedmarathon.at
 * Description: Login → HA-Gate → Dashboard. Schönes blaues Dashboard mit echten WooCommerce-Daten. PDF in Downloads.
 * Version:     1.7.0
 * Author:      Manuel Ribis GmbH
 * Text Domain: oemm-xxvi
 */

defined( 'ABSPATH' ) || exit;

define( 'OEMM_XXVI_VERSION', '1.7.0' );
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

    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="Haftungsausschluss_OeMM_2026.pdf"' );
    header( 'Content-Length: ' . filesize( $filepath ) );
    readfile( $filepath );
    exit;
}

function oemm_xxvi_generate_pdf( $filepath, $fullname, $username, $signed_ts, $sig_png ) {
    if ( ! class_exists('FPDF') ) {
        require_once OEMM_XXVI_PATH . 'lib/fpdf.php';
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);

    // Header
    $pdf->SetFillColor(15, 52, 96);
    $pdf->Rect(0, 0, 210, 28, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetXY(20, 8);
    $pdf->Cell(0, 8, iconv('UTF-8','ISO-8859-1','AGB & Haftungsausschluss'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetX(20);
    $pdf->Cell(0, 6, iconv('UTF-8','ISO-8859-1','Oetztaler Moped Marathon XXVI - 2026'), 0, 1, 'L');
    $pdf->SetY(34);

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
    $pdf->Cell(0, 5, iconv('UTF-8','ISO-8859-1', $fullname . ' (@' . $username . ')'), 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(28, 5, 'Veranstalter:', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, iconv('UTF-8','ISO-8859-1','Oetztaler Moped Verein, Soelden, Tirol'), 0, 1);
    $pdf->SetX(24);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(28, 5, 'Unterzeichnet:', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, iconv('UTF-8','ISO-8859-1', $signed_ts), 0, 1);
    $pdf->SetY(64);

    // AGB Text
    $pdf->SetTextColor(40, 40, 40);

    $sections = [
        'Allgemeine Geschaeftsbedingungen' => [
            $fullname . ' erklaert seinen Beitritt zum Oetztaler Moped Verein als ordentliches Mitglied ohne Stimmrecht fuer die Dauer bis Ende September 2026.',
            'Mit der Bezahlung des Mitgliedsbeitrags ist die Teilnahme an Veranstaltungen moeglich, allen voran der Oetztaler Mopedmarathon. Diese Ausflugsfahrt erfolgt nicht gewerblich und ist kein Rennen.',
        ],
        'Teilnahmebedingungen & Haftungsausschluss' => [
            'Die Teilnahme ist nur bei Volljaehrigkeit gestattet.',
            'Mir, dem Teilnehmer, ist bewusst, dass eine derartige Ausflugsfahrt mit gewissen Risiken behaftet ist. Ich bestatige ausdruecklich, dass fuer Verletzungen und Schaeden jeglicher Art dem OeMV keinerlei Schuld zuweisbar ist und ich den OeMV schad- und klaglos halte. Ich bin im Besitz einer gueltigen Haftpflicht- und Unfallversicherung.',
            'Weiters verpflichte ich mich, mich an die Rundfahrt- und Sicherheitsvorschriften des OeMV zu halten.',
            '!! ACHTUNG: Ich bestatige hiermit ausdruecklich, dass ich bei der verbindlichen Fahrerbesprechung des OeMV persoenlich anwesend sein werde.',
            'Ich bestatige, dass meine Ausruestung keine Maengel aufweist. Bei augenscheinlichen Maengeln kann ich jederzeit von der Teilnahme ausgeschlossen werden.',
        ],
        'Bild- & Tonrechte' => [
            'Der OeMV ist berechtigt, Fotos, Videos und Audio-Aufnahmen des OeMM fuer alle Zwecke in allen Medien weltweit zu verwenden. Der OeMV ist berechtigt, diese Rechte an Dritte zu uebertragen.',
        ],
        'Rueckgaberecht & Streitbeilegung' => [
            'Rueckgaberecht laut Fernabsatzgesetz: 14 Tage ohne Angabe von Gruenden. Gerichtsstand: Innsbruck.',
            'Gem. Par. 19 Abs 3 AStG: Wir sind weder verpflichtet noch bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.',
        ],
    ];

    foreach ( $sections as $title => $paragraphs ) {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(15, 52, 96);
        $pdf->SetX(20);
        $pdf->Cell(0, 6, strtoupper($title), 0, 1);
        $pdf->SetDrawColor(15, 52, 96);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        foreach ( $paragraphs as $p ) {
            $pdf->SetX(20);
            $pdf->MultiCell(170, 5, $p, 0, 'L');
            $pdf->Ln(1);
        }
        $pdf->Ln(3);
    }

    // Unterschrift
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(15, 52, 96);
    $pdf->SetX(20);
    $pdf->Cell(0, 6, 'DIGITALE UNTERSCHRIFT', 0, 1);
    $pdf->SetDrawColor(15, 52, 96);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(4);

    // Sig PNG einbetten
    if ( ! empty($sig_png) && strpos($sig_png, 'data:image/png;base64,') === 0 ) {
        $b64  = substr($sig_png, strlen('data:image/png;base64,'));
        $data = base64_decode($b64);
        if ( $data ) {
            $tmp = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tmp, $data);
            $sig_y = $pdf->GetY();
            $pdf->Image($tmp, 45, $sig_y, 120, 35);
            $pdf->SetY($sig_y + 38);
            unlink($tmp);
        }
    }

    // Signaturlinie
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Line(45, $pdf->GetY(), 165, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetX(45);
    $pdf->Cell(120, 5, iconv('UTF-8','ISO-8859-1', 'Digitale Unterschrift: ' . $fullname . ' | ' . $signed_ts), 0, 1, 'C');

    // Footer
    $pdf->SetY(-20);
    $pdf->SetFont('Helvetica', 'I', 7);
    $pdf->SetTextColor(160, 160, 160);
    $pdf->SetX(20);
    $pdf->Cell(0, 5, iconv('UTF-8','ISO-8859-1','Oetztaler Moped Verein - OeMM XXVI 2026 | Elektronisch unterzeichnet: ' . $signed_ts), 0, 0, 'C');

    $pdf->Output('F', $filepath);
}

// RoundedRect Hilfsfunktion (FPDF Erweiterung)
if ( ! function_exists('fpdf_rounded_rect_patched') ) {
    // Inline-Patch fuer FPDF::RoundedRect falls nicht vorhanden
    if ( class_exists('FPDF') && ! method_exists('FPDF', 'RoundedRect') ) {
        class FPDF_OMM extends FPDF {
            public function RoundedRect($x, $y, $w, $h, $r, $style='') {
                $k=$this->k; $hp=$this->h;
                if($style=='F') $op='f';
                elseif($style=='FD'||$style=='DF') $op='B';
                else $op='S';
                $MyArc=4/3*(sqrt(2)-1);
                $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
                $xc=$x+$w-$r; $yc=$y+$r;
                $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-$y)*$k));
                $this->_Arc($xc,$yc,$r,90,0);
                $xc=$x+$w-$r; $yc=$y+$h-$r;
                $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
                $this->_Arc($xc,$yc,$r,0,-90);
                $xc=$x+$r; $yc=$y+$h-$r;
                $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
                $this->_Arc($xc,$yc,$r,-90,-180);
                $xc=$x+$r; $yc=$y+$r;
                $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
                $this->_Arc($xc,$yc,$r,180,90);
                $this->_out($op);
            }
            private function _Arc($x1,$y1,$r,$a1,$a2) {
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
