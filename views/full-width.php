<?php
/**
 * Full-Width Template für ÖMM Account Pages
 * Lädt das Theme-Header/Footer aber überschreibt den Content-Bereich komplett
 */
defined( 'ABSPATH' ) || exit;

// WP Head
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap');
/* ÖMM Full-Width Override */
html, body {
  background: radial-gradient(ellipse at 50% 20%, #1a3a6e 0%, #0d1b3e 50%, #060d1f 100%) !important;
  background-attachment: fixed !important;
  margin: 0; padding: 0;
  overflow-x: hidden;
}
* { box-sizing: border-box; }
.oemm-fw-page {
  min-height: 100vh;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ALLES vom Theme ausblenden */
#masthead, .site-header, header.site-header,
.site-footer, footer.site-footer, #colophon,
.woocommerce-breadcrumb, .woocommerce-MyAccount-navigation,
.wc-shortcodes-clearfix, .woocommerce-notices-wrapper,
#page, .site, .site-content, #primary, #secondary,
.entry-header, .entry-footer, .page-header,
.main-navigation, nav.navigation, .nav-menu,
.woocommerce-account .woocommerce,
.woocommerce-account .woocommerce-MyAccount-content { 
    display: none !important;
}
/* Wrapper transparent */
.site-main, main#main, #content, .content-area,
.woocommerce-page, .woocommerce {
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
    border: none !important;
    max-width: 100% !important;
    width: 100% !important;
}
body.oemm-account-page { padding-top: 0 !important; }

/* LAYOUT */
.oemm-layout {
  max-width: 1100px;
  margin: 0 auto;
  padding: 28px 20px 60px;
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 28px;
  align-items: start;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .oemm-layout {
    grid-template-columns: 1fr;
    padding: 16px 14px 40px;
    gap: 16px;
  }
  .oemm-sidebar {
    position: static !important;
    width: 100% !important;
  }
  /* Sidebar Nav horizontal scrollen auf Mobile */
  .oemm-nav-list {
    display: flex;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    gap: 0;
    border-radius: 0 0 16px 16px;
  }
  .oemm-nav-list a {
    white-space: nowrap;
    flex-shrink: 0;
    border-bottom: none !important;
    border-right: 1px solid rgba(255,255,255,.05) !important;
    padding: 10px 14px !important;
    font-size: 12px !important;
  }
}
</style>
</head>
<body <?php body_class('oemm-account-page'); ?>>
<?php wp_body_open(); ?>

<div class="oemm-fw-page">
  <div class="oemm-layout">

    <!-- SIDEBAR NAVIGATION -->
    <div class="oemm-sidebar" style="position:sticky;top:24px">
      <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden">
        
        <!-- User Info -->
        <?php $user = wp_get_current_user();
        $fullname = trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name;
        $initials = strtoupper(mb_substr($fullname, 0, 1));
        ?>
        <div style="padding:20px 18px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:12px">
          <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#f0c040,#e8a800);display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:18px;font-weight:700;color:#1a1a2e;flex-shrink:0"><?php echo esc_html($initials); ?></div>
          <div style="min-width:0">
            <div style="font-size:13px;font-weight:600;color:rgba(255,255,255,.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html($fullname); ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.35)">@<?php echo esc_html($user->user_login); ?></div>
          </div>
        </div>

        <!-- Nav Items -->
        <?php
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $nav = [
            'omm-dashboard'    => ['⊞', 'Dashboard'],
            'omm-bestellungen' => ['📦', 'Bestellungen'],
            'omm-downloads'    => ['⬇', 'Downloads'],
            'omm-adresse'      => ['📍', 'Adresse'],
            'omm-kontodetails' => ['⚙', 'Kontodetails'],
        ];
        foreach ( $nav as $endpoint => $item ) :
            $active = strpos($uri, $endpoint) !== false;
            $url = wc_get_account_endpoint_url($endpoint);
        ?>
        <a href="<?php echo esc_url($url); ?>" style="display:flex;align-items:center;gap:10px;padding:12px 18px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.05);transition:all .12s;<?php echo $active ? 'background:rgba(240,192,64,.1);border-left:3px solid #f0c040;' : ''; ?>" onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background='<?php echo $active ? 'rgba(240,192,64,.1)' : ''; ?>'">
          <span style="font-size:16px;width:20px;text-align:center"><?php echo $item[0]; ?></span>
          <span style="font-size:13px;font-weight:<?php echo $active ? '700' : '500'; ?>;color:<?php echo $active ? '#f0c040' : 'rgba(255,255,255,.65)'; ?>"><?php echo esc_html($item[1]); ?></span>
        </a>
        <?php endforeach; // end nav items ?>

        <!-- Logout -->
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" style="display:flex;align-items:center;gap:10px;padding:12px 18px;text-decoration:none;border-top:1px solid rgba(255,255,255,.07);transition:background .12s" onmouseover="this.style.background='rgba(255,100,100,.08)'" onmouseout="this.style.background=''">
          <span style="font-size:16px;width:20px;text-align:center">→</span>
          <span style="font-size:13px;color:rgba(255,100,100,.7)">Abmelden</span>
        </a>
      </div>

      <!-- ÖMV Logo -->
      <div style="text-align:center;margin-top:20px;opacity:.3">
        <img src="https://www.mopedmarathon.at/wp-content/uploads/2023/10/cropped-rocky-512x512-1.png" style="width:36px;filter:brightness(0) invert(1)" alt="Rocky">
        <div style="font-size:10px;color:rgba(255,255,255,.5);margin-top:4px;font-family:'Oswald',sans-serif;text-transform:uppercase;letter-spacing:1px">ÖMV 2026</div>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div>
      <?php
      // WooCommerce endpoint content rendern
      global $wp_query;
      the_post();
      
      // Direkt den passenden Endpoint-Content ausgeben
      $uri = $_SERVER['REQUEST_URI'] ?? '';
      if ( strpos($uri, 'haftungsausschluss') !== false ) {
          do_action('woocommerce_account_haftungsausschluss_endpoint');
      } elseif ( strpos($uri, 'omm-dashboard') !== false ) {
          do_action('woocommerce_account_omm-dashboard_endpoint');
      } elseif ( strpos($uri, 'omm-bestellungen') !== false ) {
          do_action('woocommerce_account_omm-bestellungen_endpoint');
      } elseif ( strpos($uri, 'omm-downloads') !== false ) {
          do_action('woocommerce_account_omm-downloads_endpoint');
      } elseif ( strpos($uri, 'omm-adresse') !== false ) {
          do_action('woocommerce_account_omm-adresse_endpoint');
      } elseif ( strpos($uri, 'omm-kontodetails') !== false ) {
          do_action('woocommerce_account_omm-kontodetails_endpoint');
      }
      ?>
    </div>

  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
