<?php
/**
 * ÖMM XXVI — Bestelldetail-Ansicht (view-order)
 * Ersetzt das Standard-WooCommerce-Template im ÖMM-Dark-Design
 */
defined( 'ABSPATH' ) || exit;

// WC-Standard-View-Order-Hooks entfernen damit kein doppelter Content entsteht
remove_action( 'woocommerce_view_order', 'woocommerce_view_order', 10 );
// Alle WC-Account-Navigations-Buttons entfernen (z.B. "Anzeigen")
remove_action( 'woocommerce_account_orders_column_order-actions', 'woocommerce_account_orders_column_order-actions', 10 );
// Shipping-Adresse leer lassen (kein "Anzeigen"-Button für Lieferadresse)
add_filter( 'woocommerce_order_get_shipping_address', '__return_empty_array', 99 );
// WC "Bestellung ansehen" Forward-Button entfernen
add_filter( 'woocommerce_my_account_my_orders_actions', function( $actions ) {
    unset( $actions['view'] );
    return $actions;
}, 99 );

// Order-ID aus URL
preg_match( '/view-order\/([0-9]+)/', $_SERVER['REQUEST_URI'] ?? '', $m );
$order_id = absint( $m[1] ?? 0 );
$order    = $order_id ? wc_get_order( $order_id ) : null;

// Sicherheit: nur eigene Bestellungen
if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) : ?>
<div style="padding:40px;text-align:center;color:rgba(255,255,255,.4)">
  <div style="font-size:40px;margin-bottom:16px">🔒</div>
  <div style="font-size:16px;font-weight:600;color:#fff;margin-bottom:8px">Bestellung nicht gefunden</div>
  <div style="font-size:13px">Diese Bestellung existiert nicht oder gehört dir nicht.</div>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-bestellungen') ); ?>"
     style="display:inline-block;margin-top:20px;padding:10px 22px;background:rgba(240,192,64,.15);border:1px solid rgba(240,192,64,.3);border-radius:10px;color:#f0c040;text-decoration:none;font-size:13px">
    ← Zurück zu Bestellungen
  </a>
</div>
<?php return; endif;

// Daten sammeln
$status      = $order->get_status();
$items       = $order->get_items();
$date        = $order->get_date_created();
$total       = $order->get_formatted_order_total();
$subtotal    = wc_price( $order->get_subtotal() );
$shipping    = $order->get_shipping_total();
$discount    = $order->get_discount_total();
$payment_method = $order->get_payment_method_title();
$billing     = $order->get_address( 'billing' );
$notes       = $order->get_customer_note();

// Status-Badge
$status_map = [
    'completed'  => ['#4ade80', 'rgba(34,197,94,.15)', 'rgba(34,197,94,.25)', '✓', 'Abgeschlossen'],
    'processing' => ['#60a5fa', 'rgba(59,130,246,.15)', 'rgba(59,130,246,.25)', '⟳', 'In Bearbeitung'],
    'pending'    => ['#fbbf24', 'rgba(251,191,36,.15)', 'rgba(251,191,36,.25)', '⏳', 'Ausstehend'],
    'on-hold'    => ['#fbbf24', 'rgba(251,191,36,.15)', 'rgba(251,191,36,.25)', '⏸', 'Wartend'],
    'cancelled'  => ['rgba(255,255,255,.35)', 'rgba(255,255,255,.06)', 'rgba(255,255,255,.12)', '✕', 'Storniert'],
    'refunded'   => ['#f87171', 'rgba(239,68,68,.12)', 'rgba(239,68,68,.25)', '↩', 'Erstattet'],
    'failed'     => ['#f87171', 'rgba(239,68,68,.12)', 'rgba(239,68,68,.25)', '!', 'Fehlgeschlagen'],
];
$sd = $status_map[$status] ?? ['rgba(255,255,255,.5)', 'rgba(255,255,255,.06)', 'rgba(255,255,255,.12)', '?', ucfirst($status)];
?>

<style>
/* WooCommerce-Buttons die wir nicht brauchen - aggressiv ausblenden */
.woocommerce-order-details + .woocommerce-customer-details .edit,
a.button.wc-forward,
.woocommerce-column__title + a.button,
.woocommerce-Address a.button,
p.woocommerce-notice + a,
form.woocommerce-address-fields ~ a.button,
.woocommerce-customer-details a.button,
.woocommerce-customer-details .woocommerce-button,
.woocommerce-MyAccount-content a.button,
a[href*="view-order"].button,
.wc-item-meta ~ a.button,
section.woocommerce-customer-details a,
.woocommerce-order-customer-information a.button { display: none !important; }
.vo-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 16px;
}
.vo-card-head {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.vo-card-title {
  font-family: 'Oswald', sans-serif;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: rgba(255,255,255,.5);
}
.vo-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: 'Oswald', sans-serif;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  padding: 4px 12px;
  border-radius: 20px;
}
.vo-item-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255,255,255,.05);
}
.vo-item-row:last-child { border-bottom: none; }
.vo-item-img {
  width: 52px; height: 52px;
  border-radius: 10px;
  object-fit: cover;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  flex-shrink: 0;
}
.vo-item-img-placeholder {
  width: 52px; height: 52px;
  border-radius: 10px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.08);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.vo-item-name {
  flex: 1;
  font-size: 14px;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
}
.vo-item-meta {
  font-size: 12px;
  color: rgba(255,255,255,.35);
  margin-top: 3px;
}
.vo-item-price {
  font-size: 14px;
  font-weight: 700;
  color: #f0c040;
  text-align: right;
  white-space: nowrap;
}
.vo-sum-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 9px 20px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  font-size: 13px;
  color: rgba(255,255,255,.55);
}
.vo-sum-row:last-child { border-bottom: none; }
.vo-sum-row.total {
  padding: 13px 20px;
  border-top: 1px solid rgba(255,255,255,.1);
  margin-top: 4px;
}
.vo-sum-row.total .vo-sum-label {
  font-family: 'Oswald', sans-serif;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.vo-sum-row.total .vo-sum-val {
  font-family: 'Oswald', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: #f0c040;
}
.vo-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}
.vo-info-cell {
  padding: 14px 20px;
  border-right: 1px solid rgba(255,255,255,.06);
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.vo-info-cell:nth-child(even) { border-right: none; }
.vo-info-cell:nth-last-child(-n+2) { border-bottom: none; }
.vo-info-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: rgba(255,255,255,.3);
  margin-bottom: 5px;
  font-family: 'Oswald', sans-serif;
}
.vo-info-value {
  font-size: 13px;
  color: rgba(255,255,255,.8);
  font-weight: 500;
  line-height: 1.4;
}
.vo-dl-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: rgba(240,192,64,.1);
  border: 1px solid rgba(240,192,64,.2);
  border-radius: 12px;
  text-decoration: none;
  transition: all .15s;
  color: #f0c040;
  font-size: 13px;
  font-weight: 600;
}
.vo-dl-btn:hover {
  background: rgba(240,192,64,.18);
  border-color: rgba(240,192,64,.35);
}
@media (max-width: 600px) {
  .vo-info-grid { grid-template-columns: 1fr; }
  .vo-info-cell { border-right: none; }
  .vo-info-cell:nth-last-child(-n+2) { border-bottom: 1px solid rgba(255,255,255,.06); }
  .vo-info-cell:last-child { border-bottom: none; }
}
</style>

<!-- HEADER: Bestellhistory-Label + Status + Datum -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div>
    <div style="font-size:12px;color:rgba(255,255,255,.35);margin-top:2px">
      🗓 <?php echo $date ? esc_html( $date->date_i18n('d. F Y, H:i') ) . ' Uhr' : ''; ?>
    </div>
  </div>
  <span class="vo-status-badge" style="color:<?php echo $sd[0]; ?>;background:<?php echo $sd[1]; ?>;border:1px solid <?php echo $sd[2]; ?>">
    <?php echo $sd[3]; ?> <?php echo $sd[4]; ?>
  </span>
</div>

<!-- PRODUKTE -->
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Bestellte Artikel</span>
    <span style="font-size:12px;color:rgba(255,255,255,.3)"><?php echo count($items); ?> Artikel</span>
  </div>
  <?php foreach ( $items as $item_id => $item ) :
    $product   = $item->get_product();
    $img_url   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
    $item_total = $order->get_formatted_line_subtotal( $item );
    $variation  = '';
    if ( $item->get_meta_data() ) {
        foreach ( $item->get_meta_data() as $meta ) {
            $key = $meta->key;
            if ( substr($key, 0, 1) === '_' ) continue;
            $variation .= esc_html($key) . ': ' . esc_html($meta->value) . ' · ';
        }
        $variation = rtrim($variation, ' · ');
    }
  ?>
  <div class="vo-item-row">
    <?php if ( $img_url ) : ?>
      <img src="<?php echo esc_url($img_url); ?>" class="vo-item-img" alt="">
    <?php else : ?>
      <div class="vo-item-img-placeholder">📦</div>
    <?php endif; ?>
    <div style="flex:1;min-width:0">
      <div class="vo-item-name"><?php echo esc_html( $item->get_name() ); ?></div>
      <?php if ( $variation ) : ?>
      <div class="vo-item-meta"><?php echo $variation; ?></div>
      <?php endif; ?>
      <div class="vo-item-meta">Menge: <?php echo esc_html( $item->get_quantity() ); ?></div>
    </div>
    <div class="vo-item-price"><?php echo $item_total; ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- SUMMEN -->
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Zusammenfassung</span>
  </div>
  <div class="vo-sum-row">
    <span class="vo-sum-label">Zwischensumme</span>
    <span class="vo-sum-val"><?php echo $subtotal; ?></span>
  </div>
  <?php if ( $discount > 0 ) : ?>
  <div class="vo-sum-row">
    <span class="vo-sum-label">Rabatt</span>
    <span class="vo-sum-val" style="color:#4ade80">- <?php echo wc_price($discount); ?></span>
  </div>
  <?php endif; ?>
  <?php if ( $shipping > 0 ) : ?>
  <div class="vo-sum-row">
    <span class="vo-sum-label">Versand</span>
    <span class="vo-sum-val"><?php echo wc_price($shipping); ?></span>
  </div>
  <?php endif; ?>
  <?php
  // Fees (z.B. Gebühren)
  foreach ( $order->get_fees() as $fee ) :
  ?>
  <div class="vo-sum-row">
    <span class="vo-sum-label"><?php echo esc_html($fee->get_name()); ?></span>
    <span class="vo-sum-val"><?php echo wc_price($fee->get_total()); ?></span>
  </div>
  <?php endforeach; ?>
  <?php
  // Tax (falls sichtbar)
  $taxes = $order->get_tax_totals();
  foreach ( $taxes as $tax ) :
  ?>
  <div class="vo-sum-row">
    <span class="vo-sum-label"><?php echo esc_html($tax->label); ?></span>
    <span class="vo-sum-val"><?php echo $tax->formatted_amount; ?></span>
  </div>
  <?php endforeach; ?>
  <div class="vo-sum-row total">
    <span class="vo-sum-label">Gesamt</span>
    <span class="vo-sum-val"><?php echo $total; ?></span>
  </div>
</div>

<!-- ZAHLUNGS- & BESTELLINFOS -->
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Bestelldetails</span>
  </div>
  <div class="vo-info-grid">
    <div class="vo-info-cell">
      <div class="vo-info-label">Bestellnummer</div>
      <div class="vo-info-value">#<?php echo esc_html($order->get_order_number()); ?></div>
    </div>
    <div class="vo-info-cell">
      <div class="vo-info-label">Datum</div>
      <div class="vo-info-value"><?php echo $date ? esc_html($date->date_i18n('d.m.Y')) : '—'; ?></div>
    </div>
    <div class="vo-info-cell">
      <div class="vo-info-label">Zahlungsart</div>
      <div class="vo-info-value"><?php echo esc_html($payment_method ?: '—'); ?></div>
    </div>
    <div class="vo-info-cell">
      <div class="vo-info-label">Status</div>
      <div class="vo-info-value"><?php echo $sd[4]; ?></div>
    </div>
    <?php if ( ! empty($billing['email']) ) : ?>
    <div class="vo-info-cell">
      <div class="vo-info-label">E-Mail</div>
      <div class="vo-info-value" style="word-break:break-word"><?php echo esc_html($billing['email']); ?></div>
    </div>
    <?php endif; ?>
    <?php if ( ! empty($billing['phone']) ) : ?>
    <div class="vo-info-cell">
      <div class="vo-info-label">Telefon</div>
      <div class="vo-info-value"><?php echo esc_html($billing['phone']); ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- RECHNUNGSADRESSE -->
<?php
$addr_parts = array_filter([
    trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
    $billing['company'] ?? '',
    $billing['address_1'] ?? '',
    $billing['address_2'] ?? '',
    trim(($billing['postcode'] ?? '') . ' ' . ($billing['city'] ?? '')),
    $billing['country'] ?? '',
]);
if ( ! empty($addr_parts) ) :
?>
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Rechnungsadresse</span>
  </div>
  <div style="padding:16px 20px;font-size:13px;color:rgba(255,255,255,.7);line-height:1.7">
    <?php foreach ($addr_parts as $part) echo esc_html($part) . '<br>'; ?>
  </div>
</div>
<?php endif; ?>

<!-- BESTELLNOTIZ -->
<?php if ( $notes ) : ?>
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Deine Bestellnotiz</span>
  </div>
  <div style="padding:16px 20px;font-size:13px;color:rgba(255,255,255,.65);line-height:1.6">
    <?php echo esc_html($notes); ?>
  </div>
</div>
<?php endif; ?>

<!-- DOWNLOADABLE FILES -->
<?php
$downloads = $order->get_downloadable_items();
if ( $downloads && $order->is_download_permitted() ) :
?>
<div class="vo-card">
  <div class="vo-card-head">
    <span class="vo-card-title">Downloads</span>
  </div>
  <div style="padding:14px 18px;display:flex;flex-direction:column;gap:10px">
    <?php foreach ( $downloads as $dl ) : ?>
    <a href="<?php echo esc_url($dl['download_url']); ?>" class="vo-dl-btn">
      <span style="font-size:18px">⬇</span>
      <div>
        <div><?php echo esc_html($dl['download_name']); ?></div>
        <?php if ( ! empty($dl['downloads_remaining']) && $dl['downloads_remaining'] !== '' ) : ?>
        <div style="font-size:11px;color:rgba(240,192,64,.5);margin-top:1px"><?php echo esc_html($dl['downloads_remaining']); ?> Downloads verbleibend</div>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- AKTIONEN (Wiederholen, Stornieren etc.) -->
<?php
$actions = wc_get_account_orders_actions( $order );
if ( ! empty($actions) ) :
?>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px">
  <?php foreach ( $actions as $key => $action ) :
    $is_cancel = strpos($key, 'cancel') !== false;
    $btn_style = $is_cancel
        ? 'background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#f87171;'
        : 'background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);';
  ?>
  <a href="<?php echo esc_url($action['url']); ?>"
     class="<?php echo sanitize_html_class( 'woocommerce-button button ' . $key ); ?>"
     style="<?php echo $btn_style; ?>display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;transition:all .15s">
    <?php echo esc_html($action['name']); ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
