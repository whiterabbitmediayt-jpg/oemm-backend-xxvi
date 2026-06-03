<?php defined( 'ABSPATH' ) || exit;
$user   = wp_get_current_user();
$dl_url = get_user_meta( $user->ID, '_oemm_ha_dl_url', true );
$dl_ts  = get_user_meta( $user->ID, '_oemm_ha_signed_ts', true );
// WC Downloads
$downloads = WC()->customer ? WC()->customer->get_downloadable_products() : [];
?>
<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:18px">Downloads</h2>

<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden;margin-bottom:18px">
  <div style="padding:14px 22px;border-bottom:1px solid rgba(255,255,255,.07)">
    <h3 style="font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.65)">ÖMM Dokumente</h3>
  </div>
  <?php if ( $dl_url ) : ?>
  <a href="<?php echo esc_url($dl_url); ?>" style="display:flex;align-items:center;gap:14px;padding:16px 22px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.05);transition:background .12s" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''">
    <span style="font-size:22px">📄</span>
    <div style="flex:1">
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)">Haftungsausschluss ÖMM 2026</div>
      <div style="font-size:12px;color:rgba(255,255,255,.35);margin-top:2px"><?php echo $dl_ts ? 'Unterzeichnet am ' . esc_html($dl_ts) : 'Unterzeichnet'; ?></div>
    </div>
    <span style="background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-size:12px;font-weight:700;padding:6px 14px;border-radius:8px;text-transform:uppercase;letter-spacing:.3px">⬇ Download</span>
  </a>
  <?php else : ?>
  <div style="padding:20px 22px;text-align:center;color:rgba(255,255,255,.3);font-size:13px">Haftungsausschluss noch nicht unterschrieben.</div>
  <?php endif; ?>
</div>

<?php if ( $downloads ) : ?>
<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden">
  <div style="padding:14px 22px;border-bottom:1px solid rgba(255,255,255,.07)">
    <h3 style="font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.65)">Weitere Downloads</h3>
  </div>
  <?php foreach ( $downloads as $dl ) : ?>
  <a href="<?php echo esc_url( $dl['download_url'] ); ?>" style="display:flex;align-items:center;gap:14px;padding:14px 22px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.05);transition:background .12s" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''">
    <span style="font-size:20px">📁</span>
    <div style="flex:1">
      <div style="font-size:13px;font-weight:600;color:rgba(255,255,255,.8)"><?php echo esc_html($dl['download_name']); ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.35);margin-top:2px"><?php echo esc_html($dl['product_name']); ?></div>
    </div>
    <span style="font-size:11px;color:rgba(255,255,255,.3)">⬇</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<?php
// --- ÖMM Fotos ZIP ---
$event_year     = (int) get_option( 'oemm_event_year', date('Y') );
$zip_date_str   = get_option( 'oemm_zip_available_date', '' );
$zip_available  = $zip_date_str && ( strtotime( $zip_date_str ) <= time() );
$zip_date_human = $zip_date_str ? date_i18n( 'd.m.Y', strtotime( $zip_date_str ) ) : '';

// Anzahl Fotos des Users
global $wpdb;
$fotos_table = $wpdb->prefix . 'oemm_xxvi_fotos';
$foto_count  = 0;
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fotos_table ) ) === $fotos_table ) {
    $foto_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$fotos_table} WHERE user_id = %d AND event_year = %d",
        $user->ID, $event_year
    ) );
}

if ( $foto_count > 0 || $zip_available ) :
?>
<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden;margin-top:18px">
  <div style="padding:14px 22px;border-bottom:1px solid rgba(255,255,255,.07)">
    <h3 style="font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.65)">📷 Meine ÖMM Fotos <?php echo esc_html($event_year); ?></h3>
  </div>
  <div style="padding:20px 22px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <span style="font-size:28px">🗜️</span>
    <div style="flex:1;min-width:180px">
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)">
        Alle deine Fotos als ZIP
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:4px">
        <?php if ( $foto_count > 0 ) : ?>
          <?php echo $foto_count; ?> Foto<?php echo $foto_count !== 1 ? 's' : ''; ?> · ÖMM <?php echo esc_html($event_year); ?>
        <?php else : ?>
          ÖMM <?php echo esc_html($event_year); ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ( $zip_available ) : ?>
      <a href="<?php echo esc_url( add_query_arg([
          'oemm_zip'   => 1,
          'uid'        => $user->ID,
          'year'       => $event_year,
          'token'      => hash('sha256', AUTH_KEY . $user->ID . $event_year . 'zip'),
      ], home_url('/')) ); ?>"
         style="background:#f0c040;color:#1a1a2e;font-family:'Oswald',sans-serif;font-size:12px;font-weight:700;padding:10px 20px;border-radius:10px;text-decoration:none;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap">
        ⬇ ZIP Herunterladen
      </a>
    <?php else : ?>
      <div style="text-align:center">
        <div style="background:rgba(255,255,255,.06);color:rgba(255,255,255,.3);font-family:'Oswald',sans-serif;font-size:12px;font-weight:600;padding:10px 20px;border-radius:10px;text-transform:uppercase;letter-spacing:.4px;cursor:not-allowed;border:1px solid rgba(255,255,255,.08)">
          ⬇ ZIP Herunterladen
        </div>
        <div style="font-size:11px;color:rgba(240,192,64,.6);margin-top:6px">
          📅 Verfügbar ab <?php echo $zip_date_human ?: 'nach dem Event'; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
