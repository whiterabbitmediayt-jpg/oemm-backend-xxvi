<?php defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$fullname = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$username = $user->user_login;
$initials = strtoupper( mb_substr( $fullname, 0, 1 ) );

// Startnummer + App-URL aus oemm_participants (Startlisten-Plugin)
// Tabellenstruktur: customer_id, event_year, startnumber, token_app
function oemm_xxvi_get_startnumber( int $user_id ): ?string {
    global $wpdb;
    $table = $wpdb->prefix . 'oemm_participants';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return get_user_meta( $user_id, '_oemm_startnumber', true ) ?: null;
    }
    $year = (int) get_option( 'oemm_event_year', (int) date( 'Y' ) );
    return $wpdb->get_var( $wpdb->prepare(
        "SELECT startnumber FROM {$table} WHERE customer_id = %d AND event_year = %d LIMIT 1",
        $user_id, $year
    ) ) ?: null;
}
$startnumber = oemm_xxvi_get_startnumber( $user->ID ) ?: '—';

// Personalisierter App-Link: Basis-URL + token_app aus oemm_participants
function oemm_xxvi_get_app_url( int $user_id ): string {
    global $wpdb;
    $table = $wpdb->prefix . 'oemm_participants';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        $year  = (int) get_option( 'oemm_event_year', (int) date( 'Y' ) );
        $token = $wpdb->get_var( $wpdb->prepare(
            "SELECT token_app FROM {$table} WHERE customer_id = %d AND event_year = %d LIMIT 1",
            $user_id, $year
        ) );
        if ( $token ) {
            $base = rtrim( (string) get_option( 'oemm_app_url', 'https://moped-tracker.web.app/t/' ), '/' );
            return $base . '/' . $token;
        }
    }
    // Fallback: kein personalisierter Token verfuegbar
    $fallback = get_option( 'oemm_app_url' ) ?: get_option( 'omm_app_url' ) ?: 'https://app.mopedmarathon.at';
    return rtrim( (string) $fallback, '/' );
}
$app_url = oemm_xxvi_get_app_url( $user->ID );

// HA-Status
$ha_signed = get_user_meta( $user->ID, '_oemm_ha_signed_ts', true );

// Checkin-Status (Startpaket abgeholt?)
function oemm_xxvi_get_checkin( int $user_id ): ?object {
    global $wpdb;
    $ci = $wpdb->prefix . OEMM_XXVI_CHECKIN_TABLE;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ci ) ) !== $ci ) return null;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ci} WHERE user_id=%d", $user_id ) ) ?: null;
}
$checkin = oemm_xxvi_get_checkin( $user->ID );
$has_checkin = $checkin && !empty( $checkin->checked_in_at );

// Pickup-Slot speichern
if ( isset( $_POST['oemm_pickup_nonce'] ) && wp_verify_nonce( $_POST['oemm_pickup_nonce'], 'oemm_save_pickup_' . $user->ID ) ) {
    global $wpdb;
    $ci = $wpdb->prefix . OEMM_XXVI_CHECKIN_TABLE;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ci ) ) === $ci ) {
        $slot = sanitize_text_field( $_POST['pickup_slot'] ?? '' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ci} WHERE user_id=%d", $user->ID ) );
        if ( $existing ) {
            $wpdb->update( $ci, [ 'pickup_slot' => $slot ], [ 'user_id' => $user->ID ] );
        } else {
            $wpdb->insert( $ci, [ 'user_id' => $user->ID, 'pickup_slot' => $slot ] );
        }
        // Neu laden
        $checkin = oemm_xxvi_get_checkin( $user->ID );
    }
}
$saved_slot = $checkin->pickup_slot ?? '';

// Gesamtzahl aktiver Teilnehmer 2026 als Schwellenwert
function oemm_xxvi_get_participant_count(): int {
    global $wpdb;
    $n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oemm_participants WHERE event_year=2026" );
    return $n > 0 ? $n : 1;
}
$total_participants = oemm_xxvi_get_participant_count();
$slot_count_total = 8; // Anzahl Slots
$slot_capacity = max( 1, (int) ceil( $total_participants / $slot_count_total ) );

// Füllstand pro Slot (für Anzeige)
function oemm_xxvi_get_slot_counts(): array {
    global $wpdb;
    $ci = $wpdb->prefix . OEMM_XXVI_CHECKIN_TABLE;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ci ) ) !== $ci ) return [];
    $rows = $wpdb->get_results( "SELECT pickup_slot, COUNT(*) as cnt FROM {$ci} WHERE pickup_slot IS NOT NULL AND pickup_slot != '' GROUP BY pickup_slot" );
    $result = [];
    foreach ( $rows as $r ) $result[$r->pickup_slot] = (int)$r->cnt;
    return $result;
}
$slot_counts = oemm_xxvi_get_slot_counts();
$total_with_slot = array_sum($slot_counts);

// Timetable laden
function oemm_xxvi_get_timetable(): array {
    global $wpdb;
    $tt = $wpdb->prefix . OEMM_XXVI_TIMETABLE;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tt ) ) !== $tt ) return [];
    // Nur aktive Einträge + noch nicht abgelaufen (Endzeit oder Startzeit + 1h)
    $now = current_time( 'Y-m-d H:i:s' );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$tt}
         WHERE is_active = 1
           AND ( end_time IS NOT NULL
                 AND CONCAT(event_date,' ',end_time) >= %s
                 OR end_time IS NULL
                 AND CONCAT(event_date,' ',start_time) >= DATE_SUB(%s, INTERVAL 60 MINUTE) )
         ORDER BY event_date, start_time, sort_order
         LIMIT 20",
        $now, $now
    ) ) ?: [];
}
$timetable = oemm_xxvi_get_timetable();

// Letzte Bestellungen (max 3)
$orders = wc_get_orders( [
    'customer' => $user->ID,
    'limit'    => 3,
    'orderby'  => 'date',
    'order'    => 'DESC',
] );

// Bestellungen Anzahl
$order_count = wc_get_customer_order_count( $user->ID );
$last_order  = ! empty( $orders ) ? $orders[0] : null;
$last_order_date = $last_order ? $last_order->get_date_created()->date_i18n( 'd.m.Y' ) : '—';

// Status-Badge Helper
function oemm_status_badge( $status ) {
    $map = [
        'completed'  => ['badge-success',    'Abgeschlossen'],
        'processing' => ['badge-processing', 'In Bearbeitung'],
        'pending'    => ['badge-pending',    'Ausstehend'],
        'on-hold'    => ['badge-pending',    'Wartend'],
        'cancelled'  => ['badge',            'Storniert'],
        'refunded'   => ['badge',            'Erstattet'],
    ];
    $s = isset( $map[$status] ) ? $map[$status] : ['badge', ucfirst($status)];
    return '<span class="badge ' . $s[0] . '">' . $s[1] . '</span>';
}
?>
<style>
  /* ===== OEMM EVENT CARD ===== */
  .oemm-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 18px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  }
  .oemm-card-header {
    padding: 15px 24px;
    background: rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .oemm-card-header h3 { font-size: 15px; font-weight: 600; color: #fff; }
  .oemm-card-body {
    padding: 28px 24px;
    display: flex;
    align-items: center;
    gap: 32px;
  }
  .oemm-startnumber { text-align: center; flex-shrink: 0; }
  .oemm-sn-label {
    display: block;
    font-family: 'Oswald', sans-serif;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.45);
    margin-bottom: 4px;
  }
  .oemm-sn-number {
    display: block;
    font-family: 'Oswald', sans-serif;
    font-size: 90px;
    font-weight: 700;
    color: #f0c040;
    line-height: 1;
    text-shadow: 0 4px 24px rgba(240,192,64,0.35);
    letter-spacing: -3px;
  }
  .oemm-vdivider { width: 1px; height: 90px; background: rgba(255,255,255,0.1); flex-shrink: 0; }
  .oemm-info { flex: 1; }
  .oemm-info h4 { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 6px; }
  .oemm-info p { font-size: 13px; color: rgba(255,255,255,0.45); margin-bottom: 18px; line-height: 1.55; }

  /* ===== QUICK CARDS ===== */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 18px;
  }
  .quick-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 14px;
    padding: 18px 18px 16px;
    text-decoration: none;
    display: block;
    transition: all 0.18s;
  }
  .quick-card:hover {
    background: rgba(255,255,255,0.09);
    border-color: rgba(255,255,255,0.18);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  }
  .quick-card-icon { font-size: 22px; margin-bottom: 10px; display: block; }
  .quick-card-label {
    font-family: 'Oswald', sans-serif;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgba(255,255,255,0.35);
    margin-bottom: 3px;
  }
  .quick-card-value { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.85); }
  .quick-card-sub { font-size: 12px; color: rgba(255,255,255,0.3); margin-top: 3px; }

  /* ===== GLASS CARDS ===== */
  .glass-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 18px;
  }
  .glass-card-header {
    padding: 15px 22px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .glass-card-header h3 {
    font-family: 'Oswald', sans-serif;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: rgba(255,255,255,0.65);
  }
  .glass-card-header a { font-size: 12px; color: #4a90d9; text-decoration: none; font-weight: 500; }

  /* ===== ORDER ROWS ===== */
  .order-row {
    display: flex;
    align-items: center;
    padding: 13px 22px;
    gap: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.12s;
    text-decoration: none;
  }
  .order-row:last-child { border-bottom: none; }
  .order-row:hover { background: rgba(255,255,255,0.04); }
  .order-id { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.7); min-width: 72px; }
  .order-name { font-size: 13px; color: rgba(255,255,255,0.5); flex: 1; }
  .order-date { font-size: 12px; color: rgba(255,255,255,0.28); min-width: 90px; text-align: right; }
  .order-status-col { min-width: 110px; text-align: right; }

  /* ===== BADGES ===== */
  .badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Oswald', sans-serif;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  .badge-success    { background: rgba(34,197,94,0.15);  color: #4ade80; border: 1px solid rgba(34,197,94,0.2); }
  .badge-processing { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
  .badge-pending    { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.2); }
  .badge-gold       { background: rgba(240,192,64,0.15); color: #f0c040; border: 1px solid rgba(240,192,64,0.3); }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 768px) {
    .oemm-card-body { flex-direction: column; gap: 16px; }
    .oemm-vdivider { display: none; }
    .oemm-sn-number { font-size: 70px; }
    .cards-grid { display: none; }
    .order-date { display: none; }
    .countdown-grid { gap: 6px !important; }
    .two-col-grid { display: none !important; }
    .oemm-last-orders { display: none !important; }
    /* Ticket Mobile */
    .oemm-ticket-inner { flex-direction: column !important; }
    .oemm-ticket-left {
      padding-right: 0 !important; border-right: none !important;
      margin-right: 0 !important; border-right: none !important;
      padding-bottom: 14px !important;
      border-bottom: 2px dashed rgba(255,255,255,.12) !important;
      margin-bottom: 14px !important;
      flex-direction: row !important;
      align-items: center !important; gap: 16px !important;
      justify-content: center !important;
    }
  }
</style>

<!-- 1) TICKET CARD (Startnummer) -->
<div style="background:linear-gradient(135deg,#0f3460 0%,#1a1a2e 40%,#0d1b3e 100%);border:1px solid rgba(240,192,64,.25);border-radius:20px;overflow:hidden;margin-bottom:18px;box-shadow:0 12px 40px rgba(0,0,0,.45);position:relative;">
  <div style="height:4px;background:repeating-linear-gradient(90deg,#f0c040 0,#f0c040 12px,transparent 12px,transparent 20px);"></div>
  <div class="oemm-ticket-inner" style="padding:24px 28px;display:flex;align-items:stretch;gap:0;">
    <div class="oemm-ticket-left" style="flex-shrink:0;padding-right:24px;border-right:2px dashed rgba(255,255,255,.12);margin-right:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:110px;">
      <div style="font-family:'Oswald',sans-serif;font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.35);margin-bottom:2px;">Startnummer</div>
      <?php if ( $startnumber !== '&#8212;' && $startnumber !== '&mdash;' && $startnumber !== '—' && $startnumber !== '' ) : ?>
      <div style="font-family:'Oswald',sans-serif;font-size:96px;font-weight:700;color:#f0c040;line-height:.85;text-shadow:0 4px 30px rgba(240,192,64,.5);letter-spacing:-4px;"><?php echo esc_html($startnumber); ?></div>
      <?php else : ?>
      <div style="font-family:'Oswald',sans-serif;font-size:28px;font-weight:700;color:rgba(255,255,255,.2);">TBA</div>
      <?php endif; ?>
    </div>
    <div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:12px;">
      <div>
        <div style="font-family:'Oswald',sans-serif;font-size:9px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.3);margin-bottom:5px;">Dein Start-Ticket</div>
        <h2 style="font-family:'Oswald',sans-serif;font-size:18px;font-weight:700;color:#fff;margin:0 0 8px;line-height:1.15;">ÖTZTALER<br>MOPEDMARATHON XXVI</h2>
        <div style="font-size:12px;color:rgba(255,255,255,.45);line-height:1.8;">
          <span>📅</span> 26. - 27. Juni 2026
          &nbsp;<span style="opacity:.3">|</span>&nbsp;
          <span>📍</span> Sölden, Tirol<br>
          <span>👤</span> <?php echo esc_html($fullname); ?>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?php echo esc_url($app_url); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:7px;background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:9px 18px;border-radius:10px;text-decoration:none;box-shadow:0 4px 14px rgba(240,192,64,.3);">🏍 Zur ÖMM App</a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);font-family:'Oswald',sans-serif;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:9px 18px;border-radius:10px;text-decoration:none;">⬇ Downloads</a>
      </div>
    </div>
  </div>
  <div style="height:4px;background:repeating-linear-gradient(90deg,#f0c040 0,#f0c040 12px,transparent 12px,transparent 20px);"></div>
</div>

<!-- 2) STARTPAKET ABHOLEN (STATE 1: HA unterschrieben, Paket noch nicht abgeholt) -->
<?php if ( $ha_signed && !$has_checkin ) : ?>
<div style="background:linear-gradient(135deg,rgba(240,192,64,.08),rgba(240,192,64,.03));border:1px solid rgba(240,192,64,.25);border-radius:16px;padding:18px 20px;margin-bottom:18px;">
  <!-- Header -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
    <span style="font-size:24px;">📦</span>
    <div style="flex:1;">
      <div style="font-size:15px;font-weight:700;color:#f0c040;font-family:'Oswald',sans-serif;">Startpaket abholen</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-top:2px;">Do 25. oder Fr 26. Juni - Eventgelände Sölden</div>
    </div>
  </div>
  <div style="font-size:13px;color:rgba(255,255,255,0.65);margin-bottom:14px;line-height:1.6;">
    Dein Startpaket liegt bereit! Deinen QR-Code findest du in der <strong style="color:#fff;">ÖMM Besenwagen App</strong>.
  </div>

  <?php if ( $saved_slot ) : ?>
  <!-- Slot gespeichert - kompakte Bestätigung + Ändern-Link -->
  <?php
    $all_slots = [
      'do-nachmittag'=>'Do. 16:00 – 18:00 Uhr',
      'do-spaetnachmittag'=>'Do. 18:00 – 20:00 Uhr','do-fruehrabend'=>'Do. 20:00 – 22:00 Uhr',
      'fr-vormittag'=>'Fr. 10:00 – 13:00 Uhr','fr-mittag'=>'Fr. 13:00 – 16:00 Uhr',
      'fr-nachmittag'=>'Fr. 16:00 – 19:00 Uhr','fr-fruehrabend'=>'Fr. 19:00 – 22:00 Uhr',
    ];
    $slot_label = $all_slots[$saved_slot] ?? $saved_slot;
    $slot_cnt   = $slot_counts[$saved_slot] ?? 0;
    // Füllgrad berechnen: Ampel basierend auf Anzahl Anmeldungen
    $fill_pct = min(100, round($slot_cnt / $slot_capacity * 100));
    $fill_color = $fill_pct < 40 ? '#4ade80' : ($fill_pct < 70 ? '#f0c040' : '#f87171');
    $fill_label = $fill_pct < 40 ? 'Wenig los' : ($fill_pct < 70 ? 'Mäßig besucht' : 'Viel Andrang');
  ?>
  <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:12px 16px;margin-bottom:10px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <div style="font-size:13px;color:#fff;font-weight:600;">✅ <?php echo esc_html($slot_label); ?></div>
      <span style="font-size:11px;color:rgba(255,255,255,.4);">Deine Wahl</span>
    </div>
    <!-- Füllstandsbalken -->
    <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:4px;">Aktueller Andrang zu diesem Zeitfenster</div>
    <div style="background:rgba(255,255,255,.08);border-radius:6px;height:8px;overflow:hidden;margin-bottom:4px;">
      <div style="width:<?php echo $fill_pct; ?>%;height:100%;background:<?php echo $fill_color; ?>;border-radius:6px;transition:width .4s;"></div>
    </div>
    <div style="font-size:11px;color:<?php echo $fill_color; ?>;">&#9679; <?php echo $fill_label; ?><?php if ( current_user_can('manage_options') ) echo ' &mdash; ' . $slot_cnt . ' Anmeldung' . ($slot_cnt !== 1 ? 'en' : ''); ?></div>
  </div>
  <button onclick="document.getElementById('oemm-pickup-form').style.display='block';this.style.display='none';" 
    style="background:transparent;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.5);font-size:12px;padding:7px 14px;border-radius:8px;cursor:pointer;width:100%;">Zeit ändern</button>
  <div id="oemm-pickup-form" style="display:none;margin-top:12px;">
  <?php else : ?>
  <div id="oemm-pickup-form">
  <?php endif; ?>

  <!-- Formular -->
  <form method="post" action="">
    <?php wp_nonce_field( 'oemm_save_pickup_' . $user->ID, 'oemm_pickup_nonce' ); ?>
    <!-- Füllstandsanzeige alle Slots -->
    <div style="margin-bottom:14px;">
      <div style="font-size:11px;color:rgba(255,255,255,.35);font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Andrang pro Zeitfenster</div>
      <?php
      $slot_groups = [
        'Donnerstag, 25. Juni (16:00 – 22:00 Uhr)' => [
          'do-nachmittag'      => '16:00 – 18:00 Uhr',
          'do-spaetnachmittag' => '18:00 – 20:00 Uhr',
          'do-fruehrabend'     => '20:00 – 22:00 Uhr',
        ],
        'Freitag, 26. Juni (10:00 – 22:00 Uhr)' => [
          'fr-vormittag'   => '10:00 – 13:00 Uhr',
          'fr-mittag'      => '13:00 – 16:00 Uhr',
          'fr-nachmittag'  => '16:00 – 19:00 Uhr',
          'fr-fruehrabend' => '19:00 – 22:00 Uhr',
        ],
      ];
      foreach ( $slot_groups as $group_label => $slots ) :
      ?>
      <div style="font-size:11px;color:rgba(255,255,255,.3);font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:.8px;margin-top:10px;margin-bottom:4px;"><?php echo esc_html($group_label); ?></div>
      <?php foreach ( $slots as $val => $label ) :
        $sel = $saved_slot === $val;
        $cnt = $slot_counts[$val] ?? 0;
        $pct = min(100, round($cnt / $slot_capacity * 100));
        $col = $pct < 40 ? '#4ade80' : ($pct < 70 ? '#f0c040' : '#f87171');
      ?>
      <label style="display:block;padding:10px 14px;background:<?php echo $sel ? 'rgba(240,192,64,.1)' : 'rgba(255,255,255,.02)'; ?>;border:1px solid <?php echo $sel ? 'rgba(240,192,64,.3)' : 'rgba(255,255,255,.07)'; ?>;border-radius:10px;cursor:pointer;margin-bottom:5px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;">
          <input type="radio" name="pickup_slot" value="<?php echo esc_attr($val); ?>" <?php echo $sel ? 'checked' : ''; ?> style="accent-color:#f0c040;width:16px;height:16px;flex-shrink:0;">
          <span style="font-size:13px;color:<?php echo $sel ? '#f0c040' : 'rgba(255,255,255,.8)'; ?>;font-weight:<?php echo $sel ? '600' : '400'; ?>;"><?php echo esc_html($label); ?></span>
          <?php if ( current_user_can('manage_options') ) : ?>
          <span style="margin-left:auto;font-size:11px;color:<?php echo $col; ?>;"><?php echo $cnt; ?> Anm.</span>
          <?php endif; ?>
        </div>
        <div style="background:rgba(255,255,255,.07);border-radius:4px;height:4px;overflow:hidden;">
          <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $col; ?>;border-radius:4px;"></div>
        </div>
      </label>
      <?php endforeach; endforeach; ?>
    </div>
    <button type="submit" style="background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px;padding:10px 20px;border:none;border-radius:10px;cursor:pointer;width:100%;">
      Abholzeit speichern
    </button>
  </form>
  </div><!-- /oemm-pickup-form -->
</div>
<?php endif; ?>

<!-- (HA-Banner entfernt - Ticket steht oben, HA in Downloads) -->

<!-- REDESIGN-HINWEIS: nur wenn _oemm_ha_redesign_notice gesetzt -->
<?php
$ha_redesign = get_user_meta( $user->ID, '_oemm_ha_redesign_notice', true );
if ( $ha_redesign ) : ?>
<div id="oemm-redesign-notice" style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;background:rgba(240,192,64,0.08);border:1px solid rgba(240,192,64,0.3);border-radius:14px;margin-bottom:18px;">
  <span style="font-size:22px;flex-shrink:0;">✏️</span>
  <div style="flex:1;">
    <div style="font-size:13px;font-weight:600;color:#f0c040;margin-bottom:4px;">Kurze Info zu deinem Haftungsausschluss</div>
    <div style="font-size:12px;color:rgba(255,255,255,0.55);line-height:1.6;">Wir haben den Haftungsausschluss optisch überarbeitet &mdash; der Inhalt ist unverändert geblieben. Bitte einmal kurz neu unterzeichnen, danke! 🙏</div>
  </div>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('haftungsausschluss') ); ?>"
     style="flex-shrink:0;background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;padding:8px 16px;border-radius:8px;text-decoration:none;white-space:nowrap;align-self:center;">Jetzt unterzeichnen →</a>
</div>
<?php endif; ?>

<!-- TIMETABLE (vor Quick-Cards, gemäß Wunsch) -->
<!-- TIMETABLE -->
<?php if ( !empty($timetable) ) : ?>
<div class="glass-card" style="margin-bottom:18px;">
  <div class="glass-card-header">
    <h3>📅 Programm &amp; Timetable</h3>
    <?php if ($has_checkin) : ?>
    <span style="font-size:12px;color:#4ade80;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.2);padding:3px 10px;border-radius:20px;">✅ Startpaket abgeholt</span>
    <?php endif; ?>
  </div>
  <div style="padding:0 0 8px;">
  <?php
  $now_ts   = current_time( 'timestamp' );
  $prev_day = '';
  foreach ( $timetable as $item ) :
    // Tagesheader
    if ( $item->event_date !== $prev_day ) {
      $day_names = ['Mon'=>'Mo','Tue'=>'Di','Wed'=>'Mi','Thu'=>'Do','Fri'=>'Fr','Sat'=>'Sa','Sun'=>'So'];
      $day_label = date('D, d. M.', strtotime($item->event_date));
      foreach ($day_names as $en=>$de) $day_label = str_replace($en,$de,$day_label);
      $prev_day = $item->event_date;
      echo '<div style="padding:8px 18px 4px;font-size:11px;font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.35);border-top:1px solid rgba(255,255,255,.06);margin-top:4px;">' . esc_html($day_label) . '</div>';
    }
    // Ist dieser Eintrag gerade aktiv?
    $start_ts = strtotime($item->event_date . ' ' . $item->start_time);
    $end_ts   = $item->end_time ? strtotime($item->event_date . ' ' . $item->end_time) : $start_ts + 3600;
    $is_now   = $now_ts >= $start_ts && $now_ts <= $end_ts;
    $is_next  = !$is_now && $start_ts > $now_ts && $start_ts <= $now_ts + 7200; // nächste 2h

    $time_str = substr($item->start_time, 0, 5);
    if ( $item->end_time ) $time_str .= ' – ' . substr($item->end_time, 0, 5);

    $row_bg = $is_now ? 'rgba(240,192,64,.07)' : ($is_next ? 'rgba(255,255,255,.03)' : 'transparent');
    $row_border = $is_now ? 'border-left:3px solid #f0c040;' : ($is_next ? 'border-left:3px solid rgba(255,255,255,.15);' : 'border-left:3px solid transparent;');
  ?>
  <div style="display:flex;align-items:center;gap:12px;padding:10px 18px;background:<?php echo $row_bg; ?>;<?php echo $row_border; ?>">
    <span style="font-size:18px;flex-shrink:0;"><?php echo esc_html($item->icon); ?></span>
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;color:<?php echo $is_now ? '#f0c040' : 'rgba(255,255,255,.85)'; ?>;font-weight:<?php echo $is_now ? '700' : '500'; ?>;">
        <?php echo esc_html($item->title); ?>
        <?php if ($is_now) echo '<span style="font-size:10px;background:#f0c040;color:#1a1a2e;padding:1px 6px;border-radius:4px;margin-left:6px;font-weight:700;">JETZT</span>'; ?>
        <?php if ($is_next && !$is_now) echo '<span style="font-size:10px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.5);padding:1px 6px;border-radius:4px;margin-left:6px;">als nächstes</span>'; ?>
      </div>
      <?php if ($item->subtitle) echo '<div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:1px;">'.esc_html($item->subtitle).'</div>'; ?>
    </div>
    <div style="font-size:11px;color:rgba(255,255,255,.4);text-align:right;flex-shrink:0;font-family:Oswald,sans-serif;"><?php echo esc_html($time_str); ?></div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- QUICK CARDS -->
<div class="cards-grid">
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>" class="quick-card">
    <span class="quick-card-icon">📦</span>
    <div class="quick-card-label">Bestellungen</div>
    <div class="quick-card-value"><?php echo $order_count; ?> Bestellung<?php echo $order_count !== 1 ? 'en' : ''; ?></div>
    <div class="quick-card-sub">Zuletzt: <?php echo esc_html($last_order_date); ?></div>
  </a>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-adresse') ); ?>" class="quick-card">
    <span class="quick-card-icon">📍</span>
    <div class="quick-card-label">Adresse</div>
    <div class="quick-card-value">Rechnungsadresse</div>
    <div class="quick-card-sub">Verwalten →</div>
  </a>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-kontodetails') ); ?>" class="quick-card">
    <span class="quick-card-icon">⚙</span>
    <div class="quick-card-label">Konto</div>
    <div class="quick-card-value">Kontodetails</div>
    <div class="quick-card-sub">Passwort ändern →</div>
  </a>
</div>

<!-- COUNTDOWN + SCHNELLZUGRIFF -->
<div class="two-col-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
  <div class="glass-card" style="margin-bottom:0">
    <div class="glass-card-header"><h3>Event Countdown</h3></div>
    <div style="padding:20px 22px;text-align:center;">
      <div style="font-size:11px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.3);margin-bottom:10px;">ÖMM 2026 — 26. Juni</div>
      <div class="countdown-grid" style="display:flex;justify-content:center;gap:12px;align-items:baseline;">
        <div style="text-align:center">
          <span style="font-family:'Oswald',sans-serif;font-size:28px;font-weight:700;color:rgba(255,255,255,0.4);line-height:1">-</span><span id="cd-days" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:#f0c040;display:inline;line-height:1">--</span>
          <span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px;display:block">Tage</span>
        </div>
        <span style="font-family:'Oswald',sans-serif;font-size:28px;color:rgba(255,255,255,0.2);margin-bottom:14px">:</span>
        <div style="text-align:center">
          <span id="cd-hours" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:rgba(255,255,255,0.7);display:block;line-height:1">--</span>
          <span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Std</span>
        </div>
        <span style="font-family:'Oswald',sans-serif;font-size:28px;color:rgba(255,255,255,0.2);margin-bottom:14px">:</span>
        <div style="text-align:center">
          <span id="cd-mins" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:rgba(255,255,255,0.5);display:block;line-height:1">--</span>
          <span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Min</span>
        </div>
        <span style="font-family:'Oswald',sans-serif;font-size:28px;color:rgba(255,255,255,0.2);margin-bottom:14px">:</span>
        <div style="text-align:center">
          <span id="cd-secs" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:rgba(255,255,255,0.3);display:block;line-height:1">--</span>
          <span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Sek</span>
        </div>
      </div>
    </div>
  </div>
  <div class="glass-card" style="margin-bottom:0">
    <div class="glass-card-header"><h3>Schnellzugriff</h3></div>
    <div style="padding:8px 0;">
      <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
        <span style="font-size:20px">⬇</span>
        <div><div style="font-size:13px;color:rgba(255,255,255,0.75);font-weight:500">Downloads</div><div style="font-size:11px;color:rgba(255,255,255,0.3)">Starterpaket & Dokumente</div></div>
        <span style="margin-left:auto;color:rgba(255,255,255,0.2)">›</span>
      </a>
      <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;border-top:1px solid rgba(255,255,255,0.05);" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
        <span style="font-size:20px">🏆</span>
        <div><div style="font-size:13px;color:rgba(255,255,255,0.75);font-weight:500">Meine Bestellungen</div><div style="font-size:11px;color:rgba(255,255,255,0.3)">Bestellhistorie ansehen</div></div>
        <span style="margin-left:auto;color:rgba(255,255,255,0.2)">›</span>
      </a>
      <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-kontodetails') ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;border-top:1px solid rgba(255,255,255,0.05);" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
        <span style="font-size:20px">⚙</span>
        <div><div style="font-size:13px;color:rgba(255,255,255,0.75);font-weight:500">Kontodetails</div><div style="font-size:11px;color:rgba(255,255,255,0.3)">Passwort & E-Mail</div></div>
        <span style="margin-left:auto;color:rgba(255,255,255,0.2)">›</span>
      </a>
    </div>
  </div>
</div>

<!-- LETZTE BESTELLUNGEN -->
<div class="glass-card oemm-last-orders">
  <div class="glass-card-header">
    <h3>Letzte Bestellungen</h3>
    <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>">Alle anzeigen →</a>
  </div>
  <?php if ( ! empty( $orders ) ) : ?>
    <?php foreach ( $orders as $order ) : ?>
    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="order-row">
      <span class="order-id">#<?php echo esc_html( $order->get_order_number() ); ?></span>
      <span class="order-name"><?php
        $items = $order->get_items();
        $names = array_map( fn($i) => $i->get_name(), array_slice($items, 0, 2) );
        echo esc_html( implode(', ', $names) ?: 'Bestellung' );
      ?></span>
      <span class="order-date"><?php echo esc_html( $order->get_date_created()->date_i18n('d.m.Y') ); ?></span>
      <span class="order-status-col"><?php echo oemm_status_badge( $order->get_status() ); ?></span>
    </a>
    <?php endforeach; ?>
  <?php else : ?>
    <div style="padding:24px 22px;text-align:center;color:rgba(255,255,255,0.3);font-size:13px;">Noch keine Bestellungen vorhanden.</div>
  <?php endif; ?>
</div>

<script>
(function(){
  function updateCountdown() {
    var diff = new Date('2026-06-26T08:00:00') - new Date();
    if (diff <= 0) { document.getElementById('cd-days').textContent='0'; return; }
    var d = Math.floor(diff/86400000);
    var h = String(Math.floor((diff%86400000)/3600000)).padStart(2,'0');
    var m = String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
    var s = String(Math.floor((diff%60000)/1000)).padStart(2,'0');
    // Desktop Countdown
    if(document.getElementById('cd-days'))  document.getElementById('cd-days').textContent  = d;
    if(document.getElementById('cd-hours')) document.getElementById('cd-hours').textContent = h;
    if(document.getElementById('cd-mins'))  document.getElementById('cd-mins').textContent  = m;
    if(document.getElementById('cd-secs'))  document.getElementById('cd-secs').textContent  = s;

  }
  updateCountdown(); setInterval(updateCountdown, 1000);
})();
</script>
