<?php defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user();
?>
<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:18px">Kontodetails</h2>
<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:24px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.35);margin-bottom:5px">Vorname</div>
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)"><?php echo esc_html($user->first_name ?: '—'); ?></div>
    </div>
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.35);margin-bottom:5px">Nachname</div>
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)"><?php echo esc_html($user->last_name ?: '—'); ?></div>
    </div>
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.35);margin-bottom:5px">Benutzername</div>
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)">@<?php echo esc_html($user->user_login); ?></div>
    </div>
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.35);margin-bottom:5px">E-Mail</div>
      <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.85)"><?php echo esc_html($user->user_email); ?></div>
    </div>
  </div>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('edit-account') ); ?>" style="display:inline-block;background:rgba(255,255,255,.08);color:rgba(255,255,255,.8);font-family:'Oswald',sans-serif;font-weight:600;font-size:13px;padding:10px 20px;border-radius:10px;text-decoration:none;border:1px solid rgba(255,255,255,.12)">Daten &amp; Passwort ändern →</a>
</div>
