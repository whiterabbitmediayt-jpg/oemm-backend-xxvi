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
