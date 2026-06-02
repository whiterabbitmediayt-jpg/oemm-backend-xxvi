<?php defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$fullname = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$username = $user->user_login;
$initials = strtoupper( mb_substr( $fullname, 0, 1 ) );

// WooCommerce Daten
$orders = wc_get_orders( [ 'customer' => $user->ID, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC' ] );
$all_orders = wc_get_orders( [ 'customer' => $user->ID, 'limit' => -1 ] );
$order_count = count( $all_orders );

// Startnummer aus Bestellungen/User-Meta holen
$startnummer = get_user_meta( $user->ID, '_oemm_startnumber', true );
if ( ! $startnummer ) {
    foreach ( $all_orders as $o ) {
        foreach ( $o->get_items() as $item ) {
            $sn = $item->get_meta( '_startnumber' );
            if ( ! $sn ) $sn = $o->get_meta( '_oemm_startnumber' );
            if ( $sn ) { $startnummer = $sn; break 2; }
        }
    }
}

// Haftungsausschluss Datum
$signed_ts = get_user_meta( $user->ID, '_oemm_ha_signed_ts', true );
$dl_url    = get_user_meta( $user->ID, '_oemm_ha_dl_url', true );

// WC Status-Labels
function oemm_status_badge( $status ) {
    $map = [
        'completed'  => ['badge-success',    'Abgeschlossen'],
        'processing' => ['badge-processing', 'In Bearbeitung'],
        'pending'    => ['badge-pending',    'Ausstehend'],
        'on-hold'    => ['badge-pending',    'Wartend'],
        'cancelled'  => ['',                 'Storniert'],
        'refunded'   => ['',                 'Erstattet'],
    ];
    $s = $status;
    if ( strpos($s,'wc-') === 0 ) $s = substr($s,3);
    $d = $map[$s] ?? ['badge-processing', ucfirst($s)];
    return '<span class="badge ' . $d[0] . '">' . $d[1] . '</span>';
}
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap');
.oemm-db{font-family:'Inter','Segoe UI',system-ui,sans-serif;color:#fff}
.oemm-db *{box-sizing:border-box}
/* OEMM EVENT CARD */
.oemm-event-card{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden;margin-bottom:18px;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.oec-header{padding:15px 24px;background:rgba(255,255,255,.07);display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.07)}
.oec-header h3{font-family:'Oswald',sans-serif;font-size:15px;font-weight:600;color:#fff}
.oec-body{padding:24px;display:flex;align-items:center;gap:28px}
.oec-sn{text-align:center;flex-shrink:0}
.oec-sn-label{display:block;font-family:'Oswald',sans-serif;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);margin-bottom:4px}
.oec-sn-num{display:block;font-family:'Oswald',sans-serif;font-size:80px;font-weight:700;color:#f0c040;line-height:1;text-shadow:0 4px 24px rgba(240,192,64,.35);letter-spacing:-3px}
.oec-sn-none{display:block;font-family:'Oswald',sans-serif;font-size:32px;font-weight:700;color:rgba(255,255,255,.2);line-height:1}
.oec-divider{width:1px;height:80px;background:rgba(255,255,255,.1);flex-shrink:0}
.oec-info{flex:1}
.oec-info h4{font-family:'Oswald',sans-serif;font-size:20px;font-weight:700;color:#fff;margin-bottom:6px}
.oec-info p{font-size:13px;color:rgba(255,255,255,.45);margin-bottom:16px;line-height:1.55}
/* QUICK CARDS */
.oemm-quick-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.oemm-qcard{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:18px;text-decoration:none;display:block;transition:all .18s}
.oemm-qcard:hover{background:rgba(255,255,255,.09);border-color:rgba(255,255,255,.18);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.2)}
.oemm-qcard-icon{font-size:22px;margin-bottom:10px;display:block}
.oemm-qcard-label{font-family:'Oswald',sans-serif;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.35);margin-bottom:3px}
.oemm-qcard-value{font-size:14px;font-weight:600;color:rgba(255,255,255,.85)}
.oemm-qcard-sub{font-size:12px;color:rgba(255,255,255,.3);margin-top:3px}
/* GLASS CARD */
.oemm-glass{background:rgba(255,255,255,.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden;margin-bottom:18px}
.oemm-glass-header{padding:14px 22px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between}
.oemm-glass-header h3{font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.65)}
.oemm-glass-header a{font-size:12px;color:#4a90d9;text-decoration:none;font-weight:500}
.oemm-glass-header a:hover{text-decoration:underline}
/* ORDER ROW */
.oemm-order-row{display:flex;align-items:center;padding:12px 22px;gap:14px;border-bottom:1px solid rgba(255,255,255,.05);text-decoration:none;transition:background .12s}
.oemm-order-row:last-child{border-bottom:none}
.oemm-order-row:hover{background:rgba(255,255,255,.04)}
.oemm-oid{font-size:13px;font-weight:600;color:rgba(255,255,255,.7);min-width:70px}
.oemm-oname{font-size:13px;color:rgba(255,255,255,.5);flex:1}
.oemm-odate{font-size:12px;color:rgba(255,255,255,.28);min-width:90px;text-align:right}
.oemm-ostatus{min-width:120px;text-align:right}
/* BADGE */
.badge{display:inline-block;font-size:11px;font-weight:600;font-family:'Oswald',sans-serif;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.3px}
.badge-success{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
.badge-processing{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.2)}
.badge-pending{background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.2)}
.badge-gold{background:rgba(240,192,64,.15);color:#f0c040;border:1px solid rgba(240,192,64,.3)}
/* BTN */
.oemm-btn{display:inline-flex;align-items:center;gap:8px;background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-weight:600;font-size:14px;padding:11px 22px;border-radius:10px;text-decoration:none;border:none;cursor:pointer;transition:all .15s;letter-spacing:.3px}
.oemm-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(240,192,64,.4)}
/* COUNTDOWN */
.oemm-cd-grid{display:flex;justify-content:center;gap:16px}
.oemm-cd-item{text-align:center}
.oemm-cd-num{font-family:'Oswald',sans-serif;font-size:36px;font-weight:700;color:#f0c040;display:block;line-height:1}
.oemm-cd-label{font-size:10px;text-transform:uppercase;color:rgba(255,255,255,.3);letter-spacing:.8px}
@media(max-width:768px){
  .oec-body{flex-direction:column;gap:16px}
  .oec-divider{display:none}
  .oec-sn-num{font-size:60px}
  .oemm-quick-grid{grid-template-columns:1fr 1fr}
  .oemm-odate{display:none}
}
</style>

<div class="oemm-db">

<?php if ( $signed_ts ) : ?>
  <div style="display:flex;align-items:center;gap:12px;padding:13px 18px;background:rgba(74,222,128,.07);border:1px solid rgba(74,222,128,.18);border-radius:12px;margin-bottom:18px">
    <span style="font-size:18px">✅</span>
    <div style="flex:1">
      <div style="font-size:13px;color:#4ade80;font-weight:500">Haftungsausschluss unterzeichnet</div>
      <div style="font-size:11px;color:rgba(255,255,255,.3);margin-top:2px">Unterzeichnet am <?php echo esc_html($signed_ts); ?></div>
    </div>
    <?php if ( $dl_url ) : ?><a href="<?php echo esc_url($dl_url); ?>" style="font-size:11px;color:rgba(255,255,255,.3);text-decoration:none;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:.5px">⬇ Download</a><?php endif; ?>
  </div>
<?php endif; ?>

  <!-- EVENT CARD -->
  <div class="oemm-event-card">
    <div class="oec-header">
      <span style="font-size:20px">🏍</span>
      <h3>Ötztaler Moped Marathon 2026</h3>
      <span class="badge badge-gold" style="margin-left:auto">XXVI</span>
    </div>
    <div class="oec-body">
      <div class="oec-sn">
        <span class="oec-sn-label">Startnummer</span>
        <?php if ( $startnummer ) : ?>
          <span class="oec-sn-num"><?php echo esc_html($startnummer); ?></span>
        <?php else : ?>
          <span class="oec-sn-none">—</span>
        <?php endif; ?>
      </div>
      <div class="oec-divider"></div>
      <div class="oec-info">
        <h4>Du bist dabei!</h4>
        <p>26. - 27. Juni 2026 · Sölden, Tirol<br>Willkommen zurück, <strong style="color:rgba(255,255,255,.7)"><?php echo esc_html($fullname); ?></strong></p>
        <a href="https://moped-tracker.web.app" class="oemm-btn" target="_blank">📱 Zur ÖMM App</a>
      </div>
    </div>
  </div>

  <!-- QUICK CARDS -->
  <div class="oemm-quick-grid">
    <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>" class="oemm-qcard">
      <span class="oemm-qcard-icon">📦</span>
      <div class="oemm-qcard-label">Bestellungen</div>
      <div class="oemm-qcard-value"><?php echo $order_count; ?> Bestellung<?php echo $order_count !== 1 ? 'en' : ''; ?></div>
      <?php if ( $orders ) : $last = reset($orders); ?>
      <div class="oemm-qcard-sub">Zuletzt: <?php echo esc_html( $last->get_date_created()->date('d.m.Y') ); ?></div>
      <?php endif; ?>
    </a>
    <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" class="oemm-qcard">
      <span class="oemm-qcard-icon">⬇</span>
      <div class="oemm-qcard-label">Downloads</div>
      <div class="oemm-qcard-value">Downloads</div>
      <div class="oemm-qcard-sub">HA & Dokumente</div>
    </a>
    <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-kontodetails') ); ?>" class="oemm-qcard">
      <span class="oemm-qcard-icon">⚙</span>
      <div class="oemm-qcard-label">Konto</div>
      <div class="oemm-qcard-value">Kontodetails</div>
      <div class="oemm-qcard-sub">Passwort ändern</div>
    </a>
  </div>

  <!-- COUNTDOWN + QUICKLINKS -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px">
    <div class="oemm-glass" style="margin-bottom:0">
      <div class="oemm-glass-header"><h3>Event Countdown</h3></div>
      <div style="padding:20px 22px;text-align:center">
        <div style="font-size:11px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.3);margin-bottom:10px">ÖMM 2026 — 26. Juni 2026</div>
        <div class="oemm-cd-grid">
          <div class="oemm-cd-item"><span class="oemm-cd-num" id="ocd-days">--</span><span class="oemm-cd-label">Tage</span></div>
          <div class="oemm-cd-item"><span class="oemm-cd-num" style="color:rgba(255,255,255,.7)" id="ocd-hours">--</span><span class="oemm-cd-label">Std</span></div>
          <div class="oemm-cd-item"><span class="oemm-cd-num" style="color:rgba(255,255,255,.5)" id="ocd-mins">--</span><span class="oemm-cd-label">Min</span></div>
        </div>
      </div>
    </div>
    <div class="oemm-glass" style="margin-bottom:0">
      <div class="oemm-glass-header"><h3>Schnellzugriff</h3></div>
      <div style="padding:4px 0">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;transition:background .12s" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''">
          <span style="font-size:18px">🎫</span>
          <div><div style="font-size:13px;color:rgba(255,255,255,.75);font-weight:500">Starterpaket</div><div style="font-size:11px;color:rgba(255,255,255,.3)">Für den Eventtag</div></div>
          <span style="margin-left:auto;color:rgba(255,255,255,.2)">›</span>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;border-top:1px solid rgba(255,255,255,.05);transition:background .12s" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''">
          <span style="font-size:18px">⬇</span>
          <div><div style="font-size:13px;color:rgba(255,255,255,.75);font-weight:500">Haftungsausschluss</div><div style="font-size:11px;color:rgba(255,255,255,.3)">Download PDF</div></div>
          <span style="margin-left:auto;color:rgba(255,255,255,.2)">›</span>
        </a>
        <a href="https://mopedmarathon.at" style="display:flex;align-items:center;gap:12px;padding:10px 22px;text-decoration:none;border-top:1px solid rgba(255,255,255,.05);transition:background .12s" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''">
          <span style="font-size:18px">🏍</span>
          <div><div style="font-size:13px;color:rgba(255,255,255,.75);font-weight:500">mopedmarathon.at</div><div style="font-size:11px;color:rgba(255,255,255,.3)">Zur Website</div></div>
          <span style="margin-left:auto;color:rgba(255,255,255,.2)">›</span>
        </a>
      </div>
    </div>
  </div>

  <!-- LETZTE BESTELLUNGEN -->
  <div class="oemm-glass">
    <div class="oemm-glass-header">
      <h3>Letzte Bestellungen</h3>
      <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>">Alle anzeigen →</a>
    </div>
    <?php if ( $orders ) : foreach ( $orders as $order ) :
      $items = $order->get_items();
      $first_item = reset($items);
      $item_name = $first_item ? $first_item->get_name() : 'Bestellung';
      if ( count($items) > 1 ) $item_name .= ' +' . (count($items)-1);
    ?>
    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="oemm-order-row">
      <span class="oemm-oid">#<?php echo esc_html( $order->get_order_number() ); ?></span>
      <span class="oemm-oname"><?php echo esc_html($item_name); ?></span>
      <span class="oemm-odate"><?php echo esc_html( $order->get_date_created()->date('d.m.Y') ); ?></span>
      <span class="oemm-ostatus"><?php echo oemm_status_badge( $order->get_status() ); ?></span>
    </a>
    <?php endforeach; else : ?>
    <div style="padding:20px 22px;text-align:center;color:rgba(255,255,255,.3);font-size:13px">Noch keine Bestellungen</div>
    <?php endif; ?>
  </div>

</div>

<script>
(function(){
  function ocd(){
    var diff=new Date('2026-06-26T08:00:00')-new Date();
    if(diff<=0)return;
    document.getElementById('ocd-days').textContent=Math.floor(diff/86400000);
    document.getElementById('ocd-hours').textContent=String(Math.floor((diff%86400000)/3600000)).padStart(2,'0');
    document.getElementById('ocd-mins').textContent=String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
  }
  ocd(); setInterval(ocd,60000);
})();
</script>
