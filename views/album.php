<?php defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$event_year = (int) get_option( 'oemm_event_year', date( 'Y' ) );

global $wpdb;

// Zugriffskontrolle: User muss Teilnehmer dieses event_year sein
$parts_table = $wpdb->prefix . 'oemm_participants';
$is_participant = $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM {$parts_table} WHERE customer_id = %d AND event_year = %d LIMIT 1",
    $user->ID, $event_year
) );

if ( ! $is_participant ) {
    echo '<div style="padding:40px;text-align:center;color:rgba(255,255,255,.4);font-size:14px">';
    echo '🔒 Nur Teilnehmer des ÖMM ' . esc_html( $event_year ) . ' haben Zugriff auf dieses Album.';
    echo '</div>';
    return;
}

// Album aktiv?
$album_active = (bool) get_option( 'oemm_album_active', 0 );
if ( ! $album_active ) {
    echo '<div style="padding:60px 20px;text-align:center">';
    echo '<div style="font-size:48px;margin-bottom:16px">🖼️</div>';
    echo '<div style="font-family:\'Oswald\',sans-serif;font-size:20px;font-weight:700;color:rgba(255,255,255,.5);margin-bottom:8px">Öffentliches Album</div>';
    echo '<div style="font-size:13px;color:rgba(255,255,255,.25)">Das Album wird nach dem Event freigeschaltet.</div>';
    echo '</div>';
    return;
}

// Öffentliche Fotos laden (alle Teilnehmer, dieses Jahr, public=1)
$fotos_table = $wpdb->prefix . 'oemm_xxvi_fotos';
$likes_table = $wpdb->prefix . 'oemm_xxvi_foto_likes';

$fotos = [];
$fotos_table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$fotos_table}'" ) === $fotos_table );
$likes_table_exists  = ( $wpdb->get_var( "SHOW TABLES LIKE '{$likes_table}'" ) === $likes_table );

if ( $fotos_table_exists ) {
    if ( $likes_table_exists ) {
        $fotos = $wpdb->get_results( $wpdb->prepare(
            "SELECT f.*,
                    u.display_name AS owner_name,
                    (SELECT COUNT(*) FROM {$likes_table} l WHERE l.foto_id = f.id) AS like_count,
                    (SELECT COUNT(*) FROM {$likes_table} l WHERE l.foto_id = f.id AND l.liker_user_id = %d) AS user_liked
             FROM {$fotos_table} f
             LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
             WHERE f.is_public = 1 AND f.event_year = %d
             ORDER BY (SELECT COUNT(*) FROM {$likes_table} l2 WHERE l2.foto_id = f.id) DESC, f.shot_at ASC",
            $user->ID, $event_year
        ) );
    } else {
        $fotos = $wpdb->get_results( $wpdb->prepare(
            "SELECT f.*, u.display_name AS owner_name, 0 AS like_count, 0 AS user_liked
             FROM {$fotos_table} f
             LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
             WHERE f.is_public = 1 AND f.event_year = %d
             ORDER BY f.shot_at ASC",
            $event_year
        ) );
    }
}

$rest_url  = rest_url( 'oemm-xxvi/v1' );
$nonce_val = wp_create_nonce( 'wp_rest' );
?>
<style>
.omm-album-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:20px}
.omm-album-card{position:relative;border-radius:12px;overflow:hidden;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);aspect-ratio:4/3;cursor:pointer;transition:transform .15s,border-color .15s}
.omm-album-card:hover{transform:translateY(-2px);border-color:rgba(240,192,64,.35)}
.omm-album-card img{width:100%;height:100%;object-fit:cover;display:block}
.omm-album-overlay{position:absolute;bottom:0;left:0;right:0;padding:8px 12px;background:linear-gradient(transparent,rgba(0,0,0,.8));display:flex;align-items:center;justify-content:space-between;gap:8px}
.omm-album-owner{font-size:11px;color:rgba(255,255,255,.55);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px}
.omm-album-like{background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:4px;font-size:13px;padding:4px 8px;border-radius:6px;transition:background .12s;color:rgba(255,255,255,.6)}
.omm-album-like:hover{background:rgba(239,68,68,.2);color:#f87171}
.omm-album-like.liked{color:#ff6b6b}
.omm-album-like .like-heart{transition:transform .15s}
.omm-album-like.liked .like-heart{transform:scale(1.25)}
.omm-album-empty{text-align:center;padding:60px 20px;color:rgba(255,255,255,.3)}
.omm-top-badge{position:absolute;top:8px;left:8px;background:rgba(240,192,64,.85);color:#1a1a1a;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;font-family:'Oswald',sans-serif;letter-spacing:.5px}
</style>

<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:6px">🖼️ Öffentliches Album</h2>
<p style="font-size:13px;color:rgba(255,255,255,.4);margin-bottom:18px">Alle öffentlichen Fotos vom ÖMM <?php echo esc_html($event_year); ?> — geteilt von der Community</p>

<?php if ( empty( $fotos ) ) : ?>
<div class="omm-album-empty">
    <div style="font-size:48px;margin-bottom:12px">🖼️</div>
    <div style="font-family:'Oswald',sans-serif;font-size:18px;font-weight:600;color:rgba(255,255,255,.4);margin-bottom:8px">Noch keine öffentlichen Fotos</div>
    <div style="font-size:13px;color:rgba(255,255,255,.22)">Wenn Teilnehmer ihre Fotos auf "Public" stellen, erscheinen sie hier.</div>
    <div style="margin-top:16px;font-size:13px;color:rgba(255,255,255,.22)">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('omm-fotos')); ?>" style="color:#f0c040;text-decoration:underline">→ Zu deinen Fotos</a>
    </div>
</div>
<?php else : ?>

<div style="font-size:13px;color:rgba(255,255,255,.35);margin-bottom:14px">
    <?php echo count($fotos); ?> Foto<?php echo count($fotos) !== 1 ? 's' : ''; ?> &nbsp;·&nbsp;
    <?php echo array_sum(array_column((array)$fotos,'like_count')); ?> Likes gesamt
</div>

<div class="omm-album-grid" id="omm-album-grid">
<?php foreach ( $fotos as $i => $foto ) :
    $serve_url  = oemm_xxvi_fotos_get_serve_url( (int)$foto->id, (int)$foto->user_id );
    $like_count = (int)$foto->like_count;
    $user_liked = (int)$foto->user_liked;
    $owner      = $foto->owner_name ?: 'Teilnehmer';
    $shot_label = $foto->shot_at ? date_i18n('d.m.Y H:i', strtotime($foto->shot_at)) : '';
?>
<div class="omm-album-card" data-foto-id="<?php echo (int)$foto->id; ?>">
    <img src="<?php echo esc_url($serve_url); ?>"
         alt="ÖMM <?php echo esc_attr($event_year); ?> Foto"
         loading="lazy"
         onclick="ommAlbumLightbox('<?php echo esc_js($serve_url); ?>','<?php echo esc_js($owner); ?>','<?php echo esc_js($shot_label); ?>')" />

    <?php if ( $i < 3 && $like_count > 0 ) : ?>
    <div class="omm-top-badge"><?php echo $i===0 ? '🥇' : ($i===1 ? '🥈' : '🥉'); ?> TOP</div>
    <?php endif; ?>

    <div class="omm-album-overlay">
        <span class="omm-album-owner">📸 <?php echo esc_html($owner); ?></span>
        <button class="omm-album-like omm-toggle-album-like <?php echo $user_liked ? 'liked' : ''; ?>"
                data-foto-id="<?php echo (int)$foto->id; ?>"
                title="Like">
            <span class="like-heart">❤️</span>
            <span class="like-count"><?php echo $like_count; ?></span>
        </button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div id="ommAlbumLightbox" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.93);align-items:center;justify-content:center;padding:20px" onclick="ommAlbumLightboxClose(event)">
    <span style="position:absolute;top:20px;right:24px;color:#fff;font-size:32px;cursor:pointer;line-height:1;opacity:.7" onclick="ommAlbumLightboxClose()">&times;</span>
    <div style="text-align:center">
        <img id="ommAlbumLbImg" src="" alt="" style="max-width:90vw;max-height:80vh;border-radius:8px;object-fit:contain" />
        <div id="ommAlbumLbInfo" style="color:rgba(255,255,255,.45);font-size:13px;margin-top:10px"></div>
    </div>
</div>

<script>
(function(){
    const REST  = <?php echo json_encode(rtrim($rest_url,'/')); ?>;
    const NONCE = <?php echo json_encode($nonce_val); ?>;

    document.querySelectorAll('.omm-toggle-album-like').forEach(btn => {
        btn.addEventListener('click', async function(e){
            e.stopPropagation();
            const fotoId  = parseInt(this.dataset.fotoId);
            this.disabled = true;
            try {
                const r = await fetch(REST + '/foto/like', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-WP-Nonce':NONCE},
                    body: JSON.stringify({foto_id: fotoId})
                });
                const d = await r.json();
                if (d.success) {
                    this.querySelector('.like-count').textContent = d.like_count;
                    this.classList.toggle('liked', d.liked);
                }
            } catch(err) { console.error('Like failed',err); }
            this.disabled = false;
        });
    });

    window.ommAlbumLightbox = function(url, owner, date) {
        const lb = document.getElementById('ommAlbumLightbox');
        document.getElementById('ommAlbumLbImg').src = url;
        document.getElementById('ommAlbumLbInfo').textContent = owner + (date ? ' · ' + date : '');
        lb.style.display = 'flex';
    };
    window.ommAlbumLightboxClose = function(e) {
        const lb = document.getElementById('ommAlbumLightbox');
        if (!e || e.target === lb || e.target.classList.contains('omm-album-close')) {
            lb.style.display = 'none';
        }
    };
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.getElementById('ommAlbumLightbox').style.display = 'none';
    });
})();
</script>
