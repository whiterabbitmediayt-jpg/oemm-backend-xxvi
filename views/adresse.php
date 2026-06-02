<?php defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user();
$b = [
    'first_name' => get_user_meta($user->ID,'billing_first_name',true),
    'last_name'  => get_user_meta($user->ID,'billing_last_name',true),
    'company'    => get_user_meta($user->ID,'billing_company',true),
    'address_1'  => get_user_meta($user->ID,'billing_address_1',true),
    'address_2'  => get_user_meta($user->ID,'billing_address_2',true),
    'postcode'   => get_user_meta($user->ID,'billing_postcode',true),
    'city'       => get_user_meta($user->ID,'billing_city',true),
    'country'    => get_user_meta($user->ID,'billing_country',true),
    'phone'      => get_user_meta($user->ID,'billing_phone',true),
    'email'      => get_user_meta($user->ID,'billing_email',true) ?: $user->user_email,
];
?>
<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:18px">Adresse</h2>
<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:24px">
  <h3 style="font-family:'Oswald',sans-serif;font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.5);margin-bottom:16px">Rechnungsadresse</h3>
  <?php if ( $b['address_1'] ) : ?>
  <div style="font-size:14px;color:rgba(255,255,255,.85);line-height:1.8">
    <?php if($b['first_name']||$b['last_name']) echo '<strong>'.esc_html(trim($b['first_name'].' '.$b['last_name'])).'</strong><br>'; ?>
    <?php if($b['company']) echo esc_html($b['company']).'<br>'; ?>
    <?php echo esc_html($b['address_1']); ?><br>
    <?php if($b['address_2']) echo esc_html($b['address_2']).'<br>'; ?>
    <?php echo esc_html($b['postcode'].' '.$b['city']); ?><br>
    <?php if($b['country']) echo esc_html(WC()->countries->get_countries()[$b['country']] ?? $b['country']); ?>
  </div>
  <?php if($b['phone']||$b['email']) : ?>
  <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.07);font-size:13px;color:rgba(255,255,255,.4)">
    <?php if($b['phone']) echo '📞 '.esc_html($b['phone']).'<br>'; ?>
    <?php if($b['email']) echo '✉ '.esc_html($b['email']); ?>
  </div>
  <?php endif; ?>
  <?php else : ?>
  <p style="color:rgba(255,255,255,.3);font-size:13px">Noch keine Adresse hinterlegt.</p>
  <?php endif; ?>
  <div style="margin-top:20px">
    <a href="<?php echo esc_url( wc_get_account_endpoint_url('edit-address') ); ?>" style="display:inline-block;background:rgba(255,255,255,.08);color:rgba(255,255,255,.8);font-family:'Oswald',sans-serif;font-weight:600;font-size:13px;padding:10px 20px;border-radius:10px;text-decoration:none;border:1px solid rgba(255,255,255,.12)">Adresse bearbeiten →</a>
  </div>
</div>
