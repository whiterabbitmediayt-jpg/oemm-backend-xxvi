<?php defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$fullname = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$username = $user->user_login;
$initials = strtoupper( mb_substr( $fullname, 0, 1 ) );

// Startnummer aus User-Meta
$startnumber = get_user_meta( $user->ID, '_oemm_startnumber', true ) ?: '—';

// HA-Status
$ha_signed = get_user_meta( $user->ID, '_oemm_ha_signed_ts', true );

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
    .cards-grid { grid-template-columns: 1fr 1fr; }
    .order-date { display: none; }
    .countdown-grid { gap: 10px !important; }
    .two-col-grid { grid-template-columns: 1fr !important; }
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

<!-- HA-Banner ggf. hier einfügen, Page-Header kommt aus full-width.php -->

<!-- HA STATUS -->
<?php if ( $ha_signed ) : ?>
<div style="display:flex;align-items:center;gap:12px;padding:13px 18px;background:rgba(74,222,128,0.07);border:1px solid rgba(74,222,128,0.18);border-radius:12px;margin-bottom:18px;">
  <span style="font-size:18px">✅</span>
  <div style="flex:1;">
    <div style="font-size:13px;color:#4ade80;font-weight:500;">Haftungsausschluss unterzeichnet</div>
    <div style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;">Unterzeichnet am <?php echo esc_html($ha_signed); ?></div>
  </div>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('haftungsausschluss') ); ?>" style="font-size:11px;color:rgba(255,255,255,0.2);text-decoration:none;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:0.5px;">Ansehen</a>
</div>
<?php endif; ?>

<!-- TICKET CARD -->
<div style="background:linear-gradient(135deg,#0f3460 0%,#1a1a2e 40%,#0d1b3e 100%);border:1px solid rgba(240,192,64,.25);border-radius:20px;overflow:hidden;margin-bottom:18px;box-shadow:0 12px 40px rgba(0,0,0,.45);position:relative;">
  <div style="height:4px;background:repeating-linear-gradient(90deg,#f0c040 0,#f0c040 12px,transparent 12px,transparent 20px);"></div>
  <div class="oemm-ticket-inner" style="padding:24px 28px;display:flex;align-items:stretch;gap:0;">
    <!-- Startnummer links -->
    <div class="oemm-ticket-left" style="flex-shrink:0;padding-right:24px;border-right:2px dashed rgba(255,255,255,.12);margin-right:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:110px;">
      <div style="font-family:'Oswald',sans-serif;font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.35);margin-bottom:2px;">Startnummer</div>
      <?php if ( $startnumber !== '—' ) : ?>
      <div style="font-family:'Oswald',sans-serif;font-size:82px;font-weight:700;color:#f0c040;line-height:.9;text-shadow:0 4px 30px rgba(240,192,64,.4);letter-spacing:-3px;"><?php echo esc_html($startnumber); ?></div>
      <?php else : ?>
      <div style="font-family:'Oswald',sans-serif;font-size:28px;font-weight:700;color:rgba(255,255,255,.2);">TBA</div>
      <?php endif; ?>
      <div style="margin-top:8px;background:rgba(240,192,64,.15);border:1px solid rgba(240,192,64,.3);border-radius:20px;padding:2px 10px;font-family:'Oswald',sans-serif;font-size:9px;text-transform:uppercase;letter-spacing:.8px;color:#f0c040;">ÖMM 2026</div>
    </div>
    <!-- Event-Info rechts -->
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
        <a href="https://app.mopedmarathon.at" target="_blank" style="display:inline-flex;align-items:center;gap:7px;background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:9px 18px;border-radius:10px;text-decoration:none;box-shadow:0 4px 14px rgba(240,192,64,.3);">🏍 Zur ÖMM App</a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);font-family:'Oswald',sans-serif;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:9px 18px;border-radius:10px;text-decoration:none;">⬇ Downloads</a>
      </div>
    </div>
  </div>
  <div style="height:4px;background:repeating-linear-gradient(90deg,#f0c040 0,#f0c040 12px,transparent 12px,transparent 20px);"></div>
</div>

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
      <div class="countdown-grid" style="display:flex;justify-content:center;gap:16px;">
        <div style="text-align:center"><span id="cd-days" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:#f0c040;display:block;line-height:1">--</span><span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Tage</span></div>
        <div style="text-align:center"><span id="cd-hours" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:rgba(255,255,255,0.7);display:block;line-height:1">--</span><span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Std</span></div>
        <div style="text-align:center"><span id="cd-mins" style="font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:rgba(255,255,255,0.5);display:block;line-height:1">--</span><span style="font-size:10px;text-transform:uppercase;color:rgba(255,255,255,0.3);letter-spacing:0.8px">Min</span></div>
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
<div class="glass-card">
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
    document.getElementById('cd-days').textContent = Math.floor(diff/86400000);
    document.getElementById('cd-hours').textContent = String(Math.floor((diff%86400000)/3600000)).padStart(2,'0');
    document.getElementById('cd-mins').textContent = String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
  }
  updateCountdown(); setInterval(updateCountdown, 60000);
})();
</script>
