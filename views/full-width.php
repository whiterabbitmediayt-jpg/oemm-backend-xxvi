<?php
/**
 * Full-Width Template für ÖMM Account Pages
 */
defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$fullname   = trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name;
$initials   = strtoupper(mb_substr($fullname, 0, 1));
$uri        = $_SERVER['REQUEST_URI'] ?? '';
$avatar_url = get_user_meta($user->ID, '_oemm_avatar_url', true);

$nav = [
    'omm-dashboard'    => ['⊞',  'Dashboard'],
    'omm-bestellungen' => ['📦', 'Bestellungen'],
    'omm-packliste'    => ['✅', 'Packliste'],
    'omm-freundebuch'  => ['👥', 'Freundebuch'],
    'omm-ergebnisse'   => ['🏁', 'Ergebnisse'],
    'omm-fotos'        => ['📷', 'Meine Fotos'],
    'omm-album'        => ['🖼️', 'Öffentliches Album'],
    'omm-downloads'    => ['⬇',  'Downloads'],
    'omm-adresse'      => ['📍', 'Adresse'],
    'omm-kontodetails' => ['⚙',  'Kontodetails'],
];
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap');

html, body {
  background: radial-gradient(ellipse at 50% 20%, #1a3a6e 0%, #0d1b3e 50%, #060d1f 100%) !important;
  background-attachment: fixed !important;
  margin: 0; padding: 0; overflow-x: hidden;
}
* { box-sizing: border-box; }
.oemm-fw-page { min-height: 100vh; font-family: 'Inter', system-ui, sans-serif; }

/* Theme ausblenden */
#masthead,.site-header,header.site-header,.site-footer,footer.site-footer,#colophon,
.woocommerce-breadcrumb,.woocommerce-MyAccount-navigation,.wc-shortcodes-clearfix,
.woocommerce-notices-wrapper,#page,.site,.site-content,#primary,#secondary,
.entry-header,.entry-footer,.page-header,.main-navigation,nav.navigation,.nav-menu,
.woocommerce-account .woocommerce,
.woocommerce-account .woocommerce-MyAccount-content { display:none !important; }
.site-main,main#main,#content,.content-area,.woocommerce-page,.woocommerce {
  padding:0!important;margin:0!important;background:transparent!important;
  border:none!important;max-width:100%!important;width:100%!important;
}
body.oemm-account-page { padding-top:0!important; }

/* LAYOUT */
.oemm-layout {
  max-width: 1140px;
  margin: 0 auto;
  padding: 28px 24px 60px;
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 28px;
  align-items: start;
}
#oemm-main-content {
  overflow: visible;
  min-width: 0;
}

/* LOGO BLOCK */
.oemm-logo-block {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 18px;
  text-decoration: none;
  padding: 6px 4px;
}
.oemm-logo-block img {
  width: 88px; height: 88px;
  object-fit: contain;
  filter: brightness(0) invert(1);
  opacity: .95;
  flex-shrink: 0;
  drop-shadow: 0 2px 12px rgba(0,0,0,.4);
}
.oemm-logo-text {
  font-family: 'Oswald', sans-serif;
  font-weight: 700;
  font-size: 21px;
  text-transform: uppercase;
  line-height: 1.25;
  color: #fff;
  letter-spacing: .5px;
}
/* subzeile ausgeblendet */

/* SIDEBAR CARD */
.oemm-sidebar-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 18px;
  overflow: hidden;
}

/* PROFIL */
.oemm-profile {
  padding: 20px 18px;
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex;
  align-items: flex-start;
  gap: 14px;
}
.oemm-avatar-wrap { position: relative; flex-shrink: 0; }
.oemm-avatar-img {
  width: 110px; height: 110px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255,255,255,.22);
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
}
.oemm-avatar-circle {
  width: 110px; height: 110px;
  border-radius: 50%;
  background: linear-gradient(135deg,#2563eb,#1e40af);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Oswald',sans-serif;
  font-size: 44px; font-weight: 700; color: #fff;
  border: 3px solid rgba(255,255,255,.18);
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
}
.oemm-avatar-edit {
  position: absolute; bottom: 0; right: 0;
  width: 22px; height: 22px;
  background: #f0c040; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 11px;
  border: 2px solid #060d1f;
}
.oemm-profile-info { min-width: 0; padding-top: 2px; }
.oemm-profile-name {
  font-size: 15px; font-weight: 700; color: #fff;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  line-height: 1.2;
}
.oemm-profile-username {
  font-size: 12px; color: rgba(255,255,255,.4);
  margin-top: 4px;
}
.oemm-profile-badge {
  display: inline-block;
  font-family: 'Oswald',sans-serif;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #f0c040;
  background: rgba(240,192,64,.12);
  border: 1px solid rgba(240,192,64,.25);
  border-radius: 20px;
  padding: 2px 9px;
  margin-top: 6px;
}

/* NAV */
.oemm-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px 12px 16px;
  text-decoration: none;
  border-bottom: 1px solid rgba(255,255,255,.04);
  border-left: 3px solid transparent;
  transition: all .12s;
}
.oemm-nav-item:hover { background: rgba(255,255,255,.06); }
.oemm-nav-item.active {
  background: rgba(240,192,64,.1);
  border-left-color: #f0c040;
}
.oemm-nav-icon {
  font-size: 16px; width: 22px; text-align: center;
  color: rgba(255,255,255,.45);
  flex-shrink: 0;
}
.oemm-nav-item.active .oemm-nav-icon { color: #f0c040; }
.oemm-nav-label {
  font-size: 13px; font-weight: 500;
  color: rgba(255,255,255,.65);
}
.oemm-nav-item.active .oemm-nav-label { color: #f0c040; font-weight: 700; }
.oemm-nav-logout {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 18px;
  text-decoration: none;
  border-top: 1px solid rgba(255,255,255,.07);
  transition: background .12s;
}
.oemm-nav-logout:hover { background: rgba(255,80,80,.07); }

/* DESKTOP COUNTDOWN (rechts, auf Höhe des Page-Titles) */
.oemm-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
}
.oemm-page-title h1 {
  font-family: 'Oswald',sans-serif;
  font-size: 26px; font-weight: 700; color: #fff;
  letter-spacing: -.2px; margin: 0 0 4px;
}
.oemm-page-title p {
  font-size: 13px; color: rgba(255,255,255,.4); margin: 0;
}
.oemm-countdown-inline {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 14px;
  padding: 10px 18px;
  flex-shrink: 0;
}
.cd-label {
  font-family: 'Oswald',sans-serif;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: rgba(255,255,255,.3);
  text-align: center;
  display: block;
  margin-top: 2px;
}
.cd-val {
  font-family: 'Oswald',sans-serif;
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
  color: #f0c040;
  display: block;
}
.cd-sep {
  font-family: 'Oswald',sans-serif;
  font-size: 24px;
  color: rgba(255,255,255,.2);
  margin-bottom: 10px;
}

/* RESPONSIVE */
@media (max-width: 820px) {
  .oemm-layout {
    grid-template-columns: 1fr;
    padding: 14px 12px 40px;
    gap: 14px;
  }
  .oemm-sidebar { position: static !important; }
  .oemm-logo-block img { width: 70px; height: 70px; }
  .oemm-logo-text { font-size: 18px; }

  /* Mobile Nav: 3-Spalten-Grid, kein Scrollen */
  .oemm-nav-scroll {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
  }
  .oemm-nav-item {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 11px 8px;
    border-bottom: none !important;
    border-left: none !important;
    border-bottom: 3px solid transparent !important;
    border-right: 1px solid rgba(255,255,255,.04) !important;
  }
  .oemm-nav-item.active {
    background: rgba(240,192,64,.1);
    border-bottom-color: #f0c040 !important;
  }
  .oemm-nav-icon { font-size: 18px; width: auto; }
  .oemm-nav-label { font-size: 10px; white-space: nowrap; text-align: center; }
  .oemm-nav-logout { display: none; }

  /* Header Row auf Mobile: Countdown unter Titel, volle Breite */
  .oemm-header-row { flex-direction: column; align-items: flex-start; gap: 12px; }
  .oemm-countdown-inline {
    width: 100%;
    justify-content: center;
    flex-wrap: nowrap;
    overflow: hidden;
  }
  .cd-val { font-size: 26px; }
  .cd-sep { font-size: 20px; }
  /* Rechts-Info im Countdown auf Mobile ausblenden */
  .oemm-countdown-inline > div:last-child { display: none; }
}
</style>
</head>
<body <?php body_class('oemm-account-page'); ?>>
<?php wp_body_open(); ?>

<div class="oemm-fw-page">
  <div class="oemm-layout">

    <!-- ===== SIDEBAR ===== -->
    <div class="oemm-sidebar" style="position:sticky;top:20px">

      <!-- LOGO -->
      <a href="<?php echo esc_url(wc_get_account_endpoint_url('omm-dashboard')); ?>" class="oemm-logo-block">
        <img src="https://www.mopedmarathon.at/wp-content/uploads/2023/10/cropped-rocky-512x512-1.png" alt="Rocky">
        <div class="oemm-logo-text">ÖTZTALER<br>MOPED<br>VEREIN</div>
      </a>

      <!-- CARD: PROFIL + NAV -->
      <div class="oemm-sidebar-card">

        <!-- PROFIL -->
        <div class="oemm-profile">
          <div class="oemm-avatar-wrap">
            <?php if ( $avatar_url ) : ?>
              <img src="<?php echo esc_url($avatar_url); ?>" class="oemm-avatar-img" alt="">
            <?php else : ?>
              <div class="oemm-avatar-circle"><?php echo esc_html($initials); ?></div>
            <?php endif; ?>
            <label for="oemm-avatar-upload" class="oemm-avatar-edit" title="Foto ändern">✎</label>
            <input type="file" id="oemm-avatar-upload" accept="image/*" style="display:none" onchange="ommUploadAvatar(this)">
          </div>
          <div class="oemm-profile-info">
            <div class="oemm-profile-name"><?php echo esc_html($fullname); ?></div>
            <div class="oemm-profile-username">@<?php echo esc_html($user->user_login); ?></div>
            <span class="oemm-profile-badge">Teilnehmer 2026</span>
          </div>
        </div>

        <!-- NAV ITEMS -->
        <div class="oemm-nav-scroll">
          <?php foreach ( $nav as $endpoint => $item ) :
            $active = strpos($uri, $endpoint) !== false;
            $url    = wc_get_account_endpoint_url($endpoint);
          ?>
          <a href="<?php echo esc_url($url); ?>" class="oemm-nav-item<?php echo $active ? ' active' : ''; ?>">
            <span class="oemm-nav-icon"><?php echo $item[0]; ?></span>
            <span class="oemm-nav-label"><?php echo esc_html($item[1]); ?></span>
          </a>
          <?php endforeach; ?>
        </div>

        <!-- LOGOUT -->
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="oemm-nav-logout">
          <span style="font-size:16px;width:22px;text-align:center;color:rgba(255,80,80,.6)">→</span>
          <span style="font-size:13px;color:rgba(255,80,80,.7)">Abmelden</span>
        </a>

      </div><!-- /sidebar-card -->

      <!-- Mobile Logout (sichtbar unter Nav auf Mobile) -->
      <div style="display:none" class="oemm-mobile-logout">
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
           style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:11px;background:rgba(255,80,80,.08);border:1px solid rgba(255,80,80,.15);border-radius:12px;text-decoration:none;">
          <span style="color:rgba(255,80,80,.7);font-size:14px">→ Abmelden</span>
        </a>
      </div>

    </div><!-- /sidebar -->

    <!-- ===== MAIN CONTENT ===== -->
    <div id="oemm-main-content">

      <!-- PAGE HEADER mit Countdown rechts -->
      <div class="oemm-header-row">
        <div class="oemm-page-title">
          <?php
          $is_view_order = strpos($uri, 'view-order') !== false;
          if ( $is_view_order ) :
            // Bestellnummer aus URL holen
            preg_match('/view-order\/([0-9]+)/', $uri, $m);
            $vorder_id = $m[1] ?? '';
            ?>
            <?php
            // Bestellnummer für Titel aus Order-Objekt holen (sicherer)
            $vo_order = $vorder_id ? wc_get_order((int)$vorder_id) : null;
            $vo_num   = $vo_order ? $vo_order->get_order_number() : $vorder_id;
            ?>
            <h1>Bestell-Historie</h1>
            <p style="font-size:13px;color:rgba(255,255,255,.4);margin:0">
              Bestellung #<?php echo esc_html($vo_num); ?> &nbsp;·&nbsp;
              <a href="<?php echo esc_url(wc_get_account_endpoint_url('omm-bestellungen')); ?>" style="color:rgba(255,255,255,.35);text-decoration:none">← Zurück zu Bestellungen</a>
            </p>
          <?php else : ?>
          <h1>Mein ÖMV Account</h1>
          <p>Willkommen zurück, <strong style="color:rgba(255,255,255,.65)"><?php echo esc_html($user->first_name ?: $fullname); ?></strong></p>
          <?php endif; ?>
        </div>
        <!-- COUNTDOWN (Desktop rechts, Mobile volle Breite) -->
        <div class="oemm-countdown-inline">
          <div style="text-align:center">
            <span class="cd-val" id="cd-days2">--</span>
            <span class="cd-label">Tage</span>
          </div>
          <span class="cd-sep">:</span>
          <div style="text-align:center">
            <span class="cd-val" id="cd-hours2" style="color:rgba(255,255,255,.7)">--</span>
            <span class="cd-label">Std</span>
          </div>
          <span class="cd-sep">:</span>
          <div style="text-align:center">
            <span class="cd-val" id="cd-mins2" style="color:rgba(255,255,255,.5)">--</span>
            <span class="cd-label">Min</span>
          </div>
          <div style="margin-left:6px;border-left:1px solid rgba(255,255,255,.1);padding-left:12px;">
            <div style="font-family:'Oswald',sans-serif;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.3)">ÖMM 2026</div>
            <div style="font-family:'Oswald',sans-serif;font-size:12px;font-weight:600;color:rgba(255,255,255,.5)">26. Juni</div>
          </div>
        </div>
      </div>

      <?php
      // Endpoint-Content ausgeben
      the_post();
      if      ( strpos($uri, 'haftungsausschluss') !== false ) do_action('woocommerce_account_haftungsausschluss_endpoint');
      elseif  ( strpos($uri, 'omm-dashboard')      !== false ) do_action('woocommerce_account_omm-dashboard_endpoint');
      elseif  ( strpos($uri, 'omm-bestellungen')   !== false ) do_action('woocommerce_account_omm-bestellungen_endpoint');
      elseif  ( strpos($uri, 'omm-downloads')      !== false ) do_action('woocommerce_account_omm-downloads_endpoint');
      elseif  ( strpos($uri, 'omm-adresse')        !== false ) do_action('woocommerce_account_omm-adresse_endpoint');
      elseif  ( strpos($uri, 'omm-kontodetails')   !== false ) do_action('woocommerce_account_omm-kontodetails_endpoint');
      elseif  ( strpos($uri, 'omm-packliste')      !== false ) do_action('woocommerce_account_omm-packliste_endpoint');
      elseif  ( strpos($uri, 'omm-freundebuch')    !== false ) do_action('woocommerce_account_omm-freundebuch_endpoint');
      elseif  ( strpos($uri, 'omm-ergebnisse')     !== false ) do_action('woocommerce_account_omm-ergebnisse_endpoint');
      elseif  ( strpos($uri, 'omm-fotos')          !== false ) do_action('woocommerce_account_omm-fotos_endpoint');
      elseif  ( strpos($uri, 'view-order')         !== false ) { include OEMM_XXVI_PATH . 'views/view-order.php'; }
      ?>
    </div>

  </div><!-- /layout -->
</div><!-- /fw-page -->

<script>
// Countdown
(function(){
  function update() {
    var diff = new Date('2026-06-26T08:00:00') - new Date();
    if (diff <= 0) { ['cd-days2','cd-hours2','cd-mins2'].forEach(function(id){document.getElementById(id).textContent='0';}); return; }
    document.getElementById('cd-days2').textContent  = Math.floor(diff/86400000);
    document.getElementById('cd-hours2').textContent = String(Math.floor((diff%86400000)/3600000)).padStart(2,'0');
    document.getElementById('cd-mins2').textContent  = String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
  }
  update(); setInterval(update, 30000);
})();

// Avatar Upload
function ommUploadAvatar(input) {
  if (!input.files || !input.files[0]) return;
  var fd = new FormData();
  fd.append('action', 'oemm_upload_avatar');
  fd.append('nonce', '<?php echo wp_create_nonce("oemm_avatar"); ?>');
  fd.append('avatar', input.files[0]);
  fetch('<?php echo admin_url("admin-ajax.php"); ?>', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => { if(d.success && d.data.url) location.reload(); });
}

// Mobile Logout anzeigen
(function(){
  if (window.innerWidth <= 820) {
    var el = document.querySelector('.oemm-mobile-logout');
    if (el) el.style.display = 'block';
  }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
