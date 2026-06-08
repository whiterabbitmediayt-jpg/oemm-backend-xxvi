<?php
defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$event_year = (int) get_option( 'oemm_event_year', (int) date('Y') );

// Checkpoints aus User-Meta laden (gespeichert vom oemm-startliste Plugin)
$checkpoint_key  = 'oemm_checkpoint_' . $event_year;
$checkpoints_raw = get_user_meta( $user->ID, $checkpoint_key, true );
$checkpoints     = is_array( $checkpoints_raw ) ? $checkpoints_raw : [];

// Checkpoint-Bezeichnungen + Reihenfolge (von Urban definiert)
$checkpoint_labels = [
    'start'       => [ 'label' => 'Start',             'icon' => '🚦', 'location' => 'Sölden, Eventgelände' ],
    'penserjoch'  => [ 'label' => 'Penserjoch',         'icon' => '⛰️', 'location' => 'Penserjoch, 2211m' ],
    'jaufenpass'  => [ 'label' => 'Jaufenpass',         'icon' => '🏔️', 'location' => 'Jaufenpass, 2094m' ],
    'timmelsjoch' => [ 'label' => 'Timmelsjoch',        'icon' => '🗻', 'location' => 'Timmelsjoch, 2509m' ],
    'ziel'        => [ 'label' => 'Ziel',               'icon' => '🏁', 'location' => 'Sölden, Eventgelände' ],
];

// Startnummer
global $wpdb;
$startnumber = $wpdb->get_var( $wpdb->prepare(
    "SELECT startnumber FROM {$wpdb->prefix}oemm_participants WHERE customer_id = %d AND event_year = %d LIMIT 1",
    $user->ID, $event_year
) );

// Gesamtzeit berechnen (Start → Ziel)
$total_time_str = '';
if ( ! empty( $checkpoints['start'] ) && ! empty( $checkpoints['ziel'] ) ) {
    $ts_start = strtotime( $checkpoints['start'] );
    $ts_ziel  = strtotime( $checkpoints['ziel'] );
    if ( $ts_start && $ts_ziel && $ts_ziel > $ts_start ) {
        $diff = $ts_ziel - $ts_start;
        $h    = floor( $diff / 3600 );
        $m    = floor( ( $diff % 3600 ) / 60 );
        $s    = $diff % 60;
        $total_time_str = sprintf( '%d:%02d:%02d', $h, $m, $s );
    }
}

$has_data = ! empty( $checkpoints );
?>
<style>
/* ============================================================
   ÖMM Ergebnisse — View Styles
   ============================================================ */
.omm-ergebnisse-wrap{max-width:520px;margin:0 auto;font-family:'Segoe UI',sans-serif}

/* Header-Karte */
.omm-erg-header{background:linear-gradient(135deg,rgba(240,192,64,.12),rgba(240,192,64,.04));border:1px solid rgba(240,192,64,.25);border-radius:16px;padding:22px 24px;margin-bottom:24px;display:flex;align-items:center;gap:18px}
.omm-erg-header-icon{font-size:42px;line-height:1;flex-shrink:0}
.omm-erg-header-info h2{font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin:0 0 4px}
.omm-erg-header-info p{font-size:13px;color:rgba(255,255,255,.45);margin:0}
.omm-erg-startnr{display:inline-flex;align-items:center;gap:6px;background:rgba(240,192,64,.15);color:#f0c040;border:1px solid rgba(240,192,64,.3);border-radius:8px;padding:4px 12px;font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;margin-top:8px}

/* Gesamtzeit */
.omm-erg-total{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);border-radius:14px;padding:18px 22px;margin-bottom:24px;text-align:center}
.omm-erg-total-label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:6px}
.omm-erg-total-time{font-family:'Oswald',sans-serif;font-size:42px;font-weight:700;color:#4ade80;line-height:1;letter-spacing:2px}
.omm-erg-total-sub{font-size:12px;color:rgba(255,255,255,.35);margin-top:6px}

/* Checkpoint-Liste */
.omm-erg-list{display:flex;flex-direction:column;gap:0}
.omm-erg-item{position:relative;display:flex;align-items:stretch;gap:0}

/* Verbindungslinie */
.omm-erg-line{display:flex;flex-direction:column;align-items:center;width:44px;flex-shrink:0;padding:0 0 0 6px}
.omm-erg-dot{width:18px;height:18px;border-radius:50%;border:2px solid rgba(255,255,255,.15);background:#1a1a2e;flex-shrink:0;margin-top:18px;z-index:1;transition:all .3s}
.omm-erg-dot.reached{background:#f0c040;border-color:#f0c040;box-shadow:0 0 12px rgba(240,192,64,.5)}
.omm-erg-dot.ziel.reached{background:#4ade80;border-color:#4ade80;box-shadow:0 0 14px rgba(74,222,128,.5)}
.omm-erg-connector{width:2px;background:rgba(255,255,255,.08);flex:1;min-height:20px;margin-top:2px}
.omm-erg-connector.reached{background:rgba(240,192,64,.3)}
.omm-erg-item:last-child .omm-erg-connector{display:none}

/* Checkpoint-Inhalt */
.omm-erg-content{flex:1;padding:12px 0 20px 14px}
.omm-erg-cp-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:2px}
.omm-erg-cp-name{font-family:'Oswald',sans-serif;font-size:16px;font-weight:700;color:rgba(255,255,255,.9)}
.omm-erg-cp-name.reached{color:#fff}
.omm-erg-cp-icon{font-size:18px;margin-right:6px}
.omm-erg-cp-location{font-size:11px;color:rgba(255,255,255,.3);margin-bottom:6px}
.omm-erg-cp-time{font-family:'Oswald',sans-serif;font-size:20px;font-weight:700;color:#f0c040;letter-spacing:.5px}
.omm-erg-cp-time.ziel{color:#4ade80}
.omm-erg-cp-datetime{font-size:11px;color:rgba(255,255,255,.35);margin-top:2px}
.omm-erg-cp-pending{font-size:13px;color:rgba(255,255,255,.2);font-style:italic}

/* Noch keine Daten */
.omm-erg-empty{text-align:center;padding:48px 20px}
.omm-erg-empty-icon{font-size:48px;margin-bottom:12px}
.omm-erg-empty-title{font-family:'Oswald',sans-serif;font-size:20px;font-weight:700;color:rgba(255,255,255,.5);margin-bottom:8px}
.omm-erg-empty-text{font-size:13px;color:rgba(255,255,255,.3);line-height:1.6}

/* Event noch nicht gefahren */
.omm-erg-notice{background:rgba(240,192,64,.06);border:1px solid rgba(240,192,64,.15);border-radius:12px;padding:16px 18px;margin-bottom:20px;font-size:13px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:10px}
</style>

<div class="omm-ergebnisse-wrap">

    <!-- Header -->
    <div class="omm-erg-header">
        <div class="omm-erg-header-icon">🏁</div>
        <div class="omm-erg-header-info">
            <h2>Meine Ergebnisse</h2>
            <p>ÖMM <?php echo esc_html( $event_year ); ?> — Ötztaler Moped Marathon</p>
            <?php if ( $startnumber ) : ?>
            <div class="omm-erg-startnr">🔢 Startnummer <?php echo esc_html( $startnumber ); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $has_data ) : ?>

        <!-- Noch keine Daten -->
        <div class="omm-erg-notice">
            <span style="font-size:18px">📡</span>
            <span>Die Zeitmessung erfolgt automatisch an den Checkpoints via GPS. Sobald du gescannt wirst, erscheinen deine Zeiten hier in Echtzeit.</span>
        </div>
        <div class="omm-erg-empty">
            <div class="omm-erg-empty-icon">⏱️</div>
            <div class="omm-erg-empty-title">Noch keine Zeiten</div>
            <div class="omm-erg-empty-text">Deine Checkpointzeiten werden hier angezeigt,<br>sobald du am <?php echo date('d.m.Y', strtotime($event_year . '-06-26')); ?> startest.</div>
        </div>

    <?php else : ?>

        <!-- Gesamtzeit wenn vorhanden -->
        <?php if ( $total_time_str ) : ?>
        <div class="omm-erg-total">
            <div class="omm-erg-total-label">⏱ Gesamtzeit</div>
            <div class="omm-erg-total-time"><?php echo esc_html( $total_time_str ); ?></div>
            <div class="omm-erg-total-sub">Start bis Ziel — <?php echo esc_html( $event_year ); ?></div>
        </div>
        <?php endif; ?>

        <!-- Checkpoint-Timeline -->
        <div class="omm-erg-list">
        <?php
        // Zeiten zwischen Checkpoints berechnen
        $prev_ts    = null;
        $prev_key   = null;
        $cp_keys    = array_keys( $checkpoint_labels );
        $total_keys = count( $cp_keys );

        foreach ( $checkpoint_labels as $cp_key => $cp_info ) :
            $is_reached  = isset( $checkpoints[ $cp_key ] );
            $ts          = $is_reached ? strtotime( $checkpoints[ $cp_key ] ) : null;
            $is_last     = ( $cp_key === array_key_last( $checkpoint_labels ) );
            $is_ziel     = ( $cp_key === 'ziel' );

            // Teilzeit berechnen
            $teilzeit_str = '';
            if ( $is_reached && $prev_ts && $ts > $prev_ts ) {
                $diff = $ts - $prev_ts;
                $h    = floor( $diff / 3600 );
                $m    = floor( ( $diff % 3600 ) / 60 );
                $s    = $diff % 60;
                if ( $h > 0 ) {
                    $teilzeit_str = sprintf( '+%dh %02d\'%02d"', $h, $m, $s );
                } else {
                    $teilzeit_str = sprintf( '+%d\'%02d"', $m, $s );
                }
            }
        ?>
        <div class="omm-erg-item">
            <div class="omm-erg-line">
                <div class="omm-erg-dot <?php echo $cp_key; ?> <?php echo $is_reached ? 'reached' : ''; ?>"></div>
                <?php if ( ! $is_last ) : ?><div class="omm-erg-connector <?php echo $is_reached ? 'reached' : ''; ?>"></div><?php endif; ?>
            </div>
            <div class="omm-erg-content">
                <div class="omm-erg-cp-header">
                    <div>
                        <span class="omm-erg-cp-icon"><?php echo $cp_info['icon']; ?></span>
                        <span class="omm-erg-cp-name <?php echo $is_reached ? 'reached' : ''; ?> <?php echo $is_ziel ? 'ziel' : ''; ?>">
                            <?php echo esc_html( $cp_info['label'] ); ?>
                        </span>
                    </div>
                    <?php if ( $is_reached && $teilzeit_str ) : ?>
                    <span style="font-size:11px;color:rgba(255,255,255,.3);font-family:'Oswald',sans-serif"><?php echo esc_html( $teilzeit_str ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="omm-erg-cp-location"><?php echo esc_html( $cp_info['location'] ); ?></div>

                <?php if ( $is_reached && $ts ) : ?>
                    <?php
                    // Uhrzeit aus Timestamp
                    $uhrzeit = date_i18n( 'H:i:s \U\h\r', $ts );
                    $datum   = date_i18n( 'd.m.Y', $ts );

                    // Abschnitt-Zeit (kumuliert seit Start)
                    $kumuliert_str = '';
                    if ( $cp_key !== 'start' && ! empty( $checkpoints['start'] ) ) {
                        $diff_start = $ts - strtotime( $checkpoints['start'] );
                        if ( $diff_start > 0 ) {
                            $kh = floor( $diff_start / 3600 );
                            $km = floor( ( $diff_start % 3600 ) / 60 );
                            $ks = $diff_start % 60;
                            $kumuliert_str = sprintf( '%d:%02d:%02d seit Start', $kh, $km, $ks );
                        }
                    }
                    ?>
                    <div class="omm-erg-cp-time <?php echo $is_ziel ? 'ziel' : ''; ?>"><?php echo esc_html( $uhrzeit ); ?></div>
                    <div class="omm-erg-cp-datetime">
                        <?php echo esc_html( $datum ); ?>
                        <?php if ( $kumuliert_str ) : ?>
                        &nbsp;·&nbsp; <span style="color:rgba(240,192,64,.5)"><?php echo esc_html( $kumuliert_str ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="omm-erg-cp-pending">Noch nicht erreicht</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
            if ( $is_reached ) { $prev_ts = $ts; $prev_key = $cp_key; }
        endforeach;
        ?>
        </div>

    <?php endif; ?>

</div>
