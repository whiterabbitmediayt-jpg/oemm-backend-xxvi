<?php defined( 'ABSPATH' ) || exit;
$user   = wp_get_current_user();
$orders = wc_get_orders( [ 'customer' => $user->ID, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC' ] );
function oemm_badge( $status ) {
    $s = strpos($status,'wc-')===0 ? substr($status,3) : $status;
    $m = ['completed'=>['badge-success','Abgeschlossen'],'processing'=>['badge-processing','In Bearbeitung'],'pending'=>['badge-pending','Ausstehend'],'on-hold'=>['badge-pending','Wartend']];
    $d = $m[$s] ?? ['badge-processing', ucfirst($s)];
    return '<span class="badge '.$d[0].'">'.$d[1].'</span>';
}
?>
<style>
.badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.3px;font-family:'Oswald',sans-serif}
.badge-success{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
.badge-processing{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.2)}
.badge-pending{background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.2)}
.oemm-order-row{display:flex;align-items:center;padding:13px 22px;gap:14px;border-bottom:1px solid rgba(255,255,255,.05);text-decoration:none;transition:background .12s;color:inherit}
.oemm-order-row:last-child{border-bottom:none}
.oemm-order-row:hover{background:rgba(255,255,255,.04)}
</style>
<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:18px">Bestellungen</h2>
<div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;overflow:hidden">
<?php if ( $orders ) : foreach ( $orders as $order ) :
    $items = $order->get_items(); $first = reset($items);
    $name  = $first ? $first->get_name() : 'Bestellung';
    if(count($items)>1) $name .= ' +' . (count($items)-1);
?>
<a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="oemm-order-row">
  <span style="font-size:13px;font-weight:600;color:rgba(255,255,255,.7);min-width:70px">#<?php echo esc_html($order->get_order_number()); ?></span>
  <span style="font-size:13px;color:rgba(255,255,255,.5);flex:1"><?php echo esc_html($name); ?></span>
  <span style="font-size:12px;color:rgba(255,255,255,.28);min-width:90px;text-align:right"><?php echo esc_html($order->get_date_created()->date('d.m.Y')); ?></span>
  <span style="min-width:130px;text-align:right"><?php echo oemm_badge($order->get_status()); ?></span>
</a>
<?php endforeach; else : ?>
<div style="padding:30px;text-align:center;color:rgba(255,255,255,.3);font-size:14px">Noch keine Bestellungen vorhanden.</div>
<?php endif; ?>
</div>
