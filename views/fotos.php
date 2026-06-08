<?php defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$event_year = (int) get_option( 'oemm_event_year', date( 'Y' ) );

global $wpdb;
$fotos_table = $wpdb->prefix . 'oemm_xxvi_fotos';
$likes_table = $wpdb->prefix . 'oemm_xxvi_foto_likes';

$fotos = [];
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fotos_table}'" ) === $fotos_table ) {
    $fotos = $wpdb->get_results( $wpdb->prepare(
        "SELECT f.*,
                (SELECT COUNT(*) FROM {$likes_table} l WHERE l.foto_id = f.id) AS like_count,
                (SELECT COUNT(*) FROM {$likes_table} l WHERE l.foto_id = f.id AND l.liker_user_id = %d) AS user_liked
         FROM {$fotos_table} f
         WHERE f.user_id = %d AND f.event_year = %d
         ORDER BY f.shot_at DESC, f.uploaded_at DESC",
        $user->ID, $user->ID, $event_year
    ) );
}

$zip_date_str   = get_option( 'oemm_zip_available_date', '' );
$zip_available  = $zip_date_str && ( strtotime( $zip_date_str ) <= time() );
$zip_date_human = $zip_date_str ? date_i18n( 'd.m.Y', strtotime( $zip_date_str ) ) : '';

$rest_url  = rest_url( 'oemm-xxvi/v1' );
$nonce_val = wp_create_nonce( 'wp_rest' );

// Fotos als JSON fuer JS
$fotos_json = array_map( function( $f ) {
    $mime  = '';
    $fname = $f->filename ?? '';
    if ( preg_match( '/\.(mp4|mov|webm|avi)$/i', $fname ) ) {
        $mime = 'video';
    }
    return [
        'id'        => (int) $f->id,
        'url'       => oemm_xxvi_fotos_get_serve_url( (int) $f->id, (int) $f->user_id ),
        'is_public' => (int) $f->is_public,
        'like_count'=> (int) $f->like_count,
        'user_liked'=> (int) $f->user_liked,
        'shot_at'   => $f->shot_at ? date_i18n( 'd.m.Y H:i', strtotime( $f->shot_at ) ) : '',
        'is_video'  => $mime === 'video' ? 1 : 0,
    ];
}, $fotos );
?>
<style>
/* === MEINE FOTOS === */
.omm-fotos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:16px}
.omm-foto-card{position:relative;border-radius:10px;overflow:hidden;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);aspect-ratio:4/3;cursor:pointer;transition:transform .15s,border-color .15s}
.omm-foto-card:hover{transform:translateY(-2px);border-color:rgba(240,192,64,.4)}
.omm-foto-card img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
.omm-foto-overlay{position:absolute;bottom:0;left:0;right:0;padding:10px 10px;background:linear-gradient(transparent,rgba(0,0,0,.85));display:flex;align-items:center;gap:8px;opacity:0;transition:opacity .15s}
.omm-foto-card:hover .omm-foto-overlay,.omm-foto-card.touch-show .omm-foto-overlay{opacity:1}
.omm-foto-btn{border:none;border-radius:8px;padding:8px 13px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Oswald',sans-serif;letter-spacing:.3px;transition:background .12s;line-height:1;min-width:38px;text-align:center}
.omm-btn-public{background:rgba(34,197,94,.25);color:#4ade80;border:1px solid rgba(34,197,94,.4)}
.omm-btn-public:hover{background:rgba(34,197,94,.45)}
.omm-btn-private{background:rgba(255,255,255,.15);color:rgba(255,255,255,.75);border:1px solid rgba(255,255,255,.2)}
.omm-btn-private:hover{background:rgba(255,255,255,.28)}
.omm-btn-like{background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.3);margin-left:auto}
.omm-btn-like:hover,.omm-btn-like.liked{background:rgba(239,68,68,.45);color:#ff6b6b}
.omm-btn-delete{background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.omm-btn-delete:hover{background:rgba(239,68,68,.55);color:#fff}
.omm-btn-download{background:rgba(240,192,64,.15);color:#f0c040;border:1px solid rgba(240,192,64,.25)}
.omm-btn-download:hover{background:rgba(240,192,64,.35);color:#fff}

/* Upload Button */
.omm-upload-trigger{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:rgba(240,192,64,.12);color:#f0c040;border:1px solid rgba(240,192,64,.25);font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;padding:13px 20px;border-radius:12px;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;transition:background .15s,border-color .15s;box-sizing:border-box}
.omm-upload-trigger:hover{background:rgba(240,192,64,.22);border-color:rgba(240,192,64,.45)}
/* Upload Modal */
#ommUploadModal{display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center}
#ommUploadModal.open{display:flex}
#ommUploadBox{background:#1a1a2e;border:1px solid rgba(240,192,64,.25);border-radius:16px;padding:28px 28px 24px;max-width:380px;width:92%}
#ommUploadBox h3{font-family:'Oswald',sans-serif;font-size:18px;font-weight:700;color:#f0c040;margin:0 0 16px}
.omm-drop-zone{border:2px dashed rgba(240,192,64,.3);border-radius:12px;padding:30px 20px;text-align:center;cursor:pointer;color:rgba(255,255,255,.4);font-size:13px;transition:border-color .2s,background .2s;margin-bottom:14px;position:relative}
.omm-drop-zone.drag-over{border-color:#f0c040;background:rgba(240,192,64,.05);color:rgba(255,255,255,.7)}
.omm-drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.omm-drop-zone .drop-icon{font-size:32px;margin-bottom:8px}
.omm-drop-zone .drop-preview{max-width:100%;max-height:140px;border-radius:8px;margin-top:10px;display:none}

.omm-public-toggle{display:flex;align-items:center;gap:10px;margin-bottom:18px;font-size:13px;color:rgba(255,255,255,.65);cursor:pointer;user-select:none}
.omm-public-toggle input{width:18px;height:18px;cursor:pointer;accent-color:#f0c040}
.omm-upload-progress{display:none;height:6px;background:rgba(255,255,255,.1);border-radius:3px;margin-bottom:14px;overflow:hidden}
.omm-upload-progress-bar{height:100%;background:#f0c040;width:0;transition:width .3s;border-radius:3px}
.omm-upload-btns{display:flex;gap:10px}
.omm-upload-send{flex:1;background:#f0c040;color:#1a1a2e;border:none;font-family:'Oswald',sans-serif;font-size:14px;font-weight:800;padding:12px;border-radius:8px;cursor:pointer;text-transform:uppercase;letter-spacing:.5px}
.omm-upload-send:hover{background:#e5b530}
.omm-upload-send:disabled{background:rgba(240,192,64,.3);cursor:not-allowed}
.omm-upload-cancel{background:rgba(255,255,255,.08);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.15);font-family:'Oswald',sans-serif;font-size:14px;font-weight:700;padding:12px 18px;border-radius:8px;cursor:pointer}
.omm-upload-cancel:hover{background:rgba(255,255,255,.15)}
/* Delete Confirm Overlay */
#ommDeleteModal{display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.8);align-items:center;justify-content:center}
#ommDeleteModal.open{display:flex}
#ommDeleteBox{background:#1a1a2e;border:1px solid rgba(239,68,68,.4);border-radius:16px;padding:28px 32px;text-align:center;max-width:320px;width:90%}
#ommDeleteBox p{color:rgba(255,255,255,.75);font-size:14px;margin:10px 0 22px}
.omm-del-btns{display:flex;gap:10px;justify-content:center}
.omm-del-btn-cancel{background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);font-family:'Oswald',sans-serif;font-size:13px;font-weight:700;padding:10px 22px;border-radius:8px;cursor:pointer;text-transform:uppercase;letter-spacing:.3px}
.omm-del-btn-cancel:hover{background:rgba(255,255,255,.2)}
.omm-del-btn-confirm{background:rgba(239,68,68,.8);color:#fff;border:1px solid rgba(239,68,68,.9);font-family:'Oswald',sans-serif;font-size:13px;font-weight:700;padding:10px 22px;border-radius:8px;cursor:pointer;text-transform:uppercase;letter-spacing:.3px}
.omm-del-btn-confirm:hover{background:rgb(239,68,68)}
.omm-foto-badge-pub{position:absolute;top:6px;right:6px;background:rgba(34,197,94,.85);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:20px;font-family:'Oswald',sans-serif;letter-spacing:.5px}
.omm-zip-hint{background:rgba(240,192,64,.08);border:1px solid rgba(240,192,64,.2);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;color:rgba(240,192,64,.9);font-size:13px}
.omm-empty{text-align:center;padding:60px 20px;color:rgba(255,255,255,.3)}
.omm-empty-icon{font-size:48px;margin-bottom:12px}

/* === SWIPE LIGHTBOX === */
#ommLightbox{display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.95);flex-direction:column;align-items:center;justify-content:center;touch-action:none;user-select:none;-webkit-user-select:none}
#ommLightbox.open{display:flex}
#ommLbTrackWrap{width:100%;flex:1;overflow:hidden;position:relative}
#ommLbTrack{display:flex;height:100%;transition:transform .3s cubic-bezier(.4,0,.2,1);will-change:transform}
.omm-lb-slide{min-width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:20px}
.omm-lb-slide img{max-width:100%;max-height:100%;object-fit:contain;border-radius:6px;pointer-events:none}
/* Nav Arrows */
.omm-lb-nav{position:fixed;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.1);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:100001;transition:background .15s;-webkit-tap-highlight-color:transparent}
.omm-lb-nav:hover{background:rgba(255,255,255,.25)}
.omm-lb-nav.disabled{opacity:.2;pointer-events:none}
#ommLbPrev{left:10px}
#ommLbNext{right:10px}
/* Top Bar */
#ommLbBar{width:100%;display:flex;align-items:center;padding:12px 16px;gap:10px;flex-shrink:0}
#ommLbCounter{font-family:'Oswald',sans-serif;font-size:13px;color:rgba(255,255,255,.5);letter-spacing:.05em;margin-right:auto}
#ommLbCaption{font-size:11px;color:rgba(255,255,255,.35)}
/* Action Buttons in Lightbox */
#ommLbActions{display:flex;gap:10px;align-items:center;justify-content:center;padding:14px 16px;flex-shrink:0}
#ommLbActions .omm-foto-btn{padding:11px 20px;font-size:16px;border-radius:10px;min-width:52px}
/* Dots */
#ommLbDots{display:flex;gap:5px;justify-content:center;padding-bottom:12px;flex-shrink:0;flex-wrap:wrap;max-height:28px;overflow:hidden}
.omm-lb-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.2);cursor:pointer;transition:background .2s,transform .2s;-webkit-tap-highlight-color:transparent}
.omm-lb-dot.active{background:#f0c040;transform:scale(1.4)}
/* Close */
#ommLbClose{background:rgba(255,255,255,.1);border:none;color:#fff;width:36px;height:36px;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;-webkit-tap-highlight-color:transparent}
#ommLbClose:hover{background:rgba(255,255,255,.25)}
</style>

<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:6px">📷 Meine Fotos</h2>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin-bottom:16px">Deine Fotobox-Bilder vom ÖMM <?php echo esc_html( $event_year ); ?> — Foto antippen zum Durchswipen</p>
<button class="omm-upload-trigger" id="ommUploadOpen">📷 Eigene Bilder uploaden</button>

<!-- UPLOAD MODAL -->
<div id="ommUploadModal" role="dialog" aria-modal="true">
    <div id="ommUploadBox">
        <h3>📤 Foto / Video hochladen</h3>
        <div class="omm-drop-zone" id="ommDropZone">
            <input type="file" id="ommUploadFile" accept="image/jpeg,image/png,image/webp" />
            <div class="drop-icon">📷</div>
            <div>Foto hierher ziehen oder tippen</div>
            <div style="font-size:11px;color:rgba(255,255,255,.3);margin-top:4px">JPEG · PNG · WEBP — max. 25 MB</div>
            <img class="drop-preview" id="ommDropPreview" src="" alt="" />
        </div>
        <label class="omm-public-toggle">
            <input type="checkbox" id="ommUploadPublic" /> Sofort öffentlich sichtbar (Community-Album)
        </label>
        <div class="omm-upload-progress" id="ommUploadProgress">
            <div class="omm-upload-progress-bar" id="ommUploadBar"></div>
        </div>
        <div id="ommUploadMsg" style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:10px;min-height:16px"></div>
        <div class="omm-upload-btns">
            <button class="omm-upload-cancel" id="ommUploadCancel">Abbrechen</button>
            <button class="omm-upload-send" id="ommUploadSend" disabled>Hochladen</button>
        </div>
    </div>
</div>



<?php if ( empty( $fotos ) ) : ?>
<div class="omm-empty">
    <div class="omm-empty-icon">📷</div>
    <div style="font-family:'Oswald',sans-serif;font-size:18px;font-weight:600;color:rgba(255,255,255,.5);margin-bottom:8px">Noch keine Fotos</div>
    <div style="font-size:13px;color:rgba(255,255,255,.25)">Deine Fotobox-Bilder erscheinen hier automatisch nach dem Scan.</div>
</div>
<?php else : ?>

<div style="font-size:13px;color:rgba(255,255,255,.35);margin-bottom:14px">
    <?php echo count($fotos); ?> Foto<?php echo count($fotos) !== 1 ? 's' : ''; ?> &nbsp;·&nbsp;
    <?php echo count(array_filter($fotos, fn($f) => $f->is_public)); ?> öffentlich
    <span style="color:rgba(255,255,255,.2);margin-left:6px">· Antippen zum Swipen</span>
</div>

<div class="omm-fotos-grid" id="omm-fotos-grid">
<?php foreach ( $fotos as $idx => $foto ) :
    $serve_url  = oemm_xxvi_fotos_get_serve_url( (int) $foto->id, (int) $foto->user_id );
    $is_public  = (int) $foto->is_public;
    $like_count = (int) $foto->like_count;
    $user_liked = (int) $foto->user_liked;
    $shot_label = $foto->shot_at ? date_i18n( 'd.m.Y H:i', strtotime( $foto->shot_at ) ) : '';
?>
<?php
    $is_video_file = (bool) preg_match( '/\.(mp4|mov|webm|avi)$/i', $foto->filename ?? '' );
?>
<div class="omm-foto-card" data-foto-id="<?php echo (int)$foto->id; ?>" data-idx="<?php echo $idx; ?>" data-public="<?php echo $is_public; ?>" data-is-video="<?php echo $is_video_file ? 1 : 0; ?>">
<?php if ( $is_video_file ) : ?>
    <video src="<?php echo esc_url( $serve_url ); ?>" muted playsinline preload="metadata" style="width:100%;height:100%;object-fit:cover"></video>
    <div class="omm-video-badge">▶ VIDEO</div>
<?php else : ?>
    <img src="<?php echo esc_url( $serve_url ); ?>" alt="ÖMM Foto" loading="<?php echo $idx < 4 ? 'eager' : 'lazy'; ?>" />
<?php endif; ?>

    <?php if ( $is_public ) : ?>
    <div class="omm-foto-badge-pub">ÖFFENTLICH</div>
    <?php endif; ?>

    <div class="omm-foto-overlay">
        <button class="omm-foto-btn <?php echo $is_public ? 'omm-btn-public' : 'omm-btn-private'; ?> omm-toggle-public"
                data-foto-id="<?php echo (int)$foto->id; ?>" data-public="<?php echo $is_public; ?>">
            <?php echo $is_public ? '🌍' : '🔒'; ?>
        </button>
        <button class="omm-foto-btn omm-btn-like omm-toggle-like <?php echo $user_liked ? 'liked' : ''; ?>"
                data-foto-id="<?php echo (int)$foto->id; ?>">
            ❤️ <span class="like-count"><?php echo $like_count; ?></span>
        </button>
        <a class="omm-foto-btn omm-btn-download omm-dl-foto"
           href="<?php echo esc_url( oemm_xxvi_fotos_get_serve_url( (int)$foto->id, (int)$foto->user_id ) ); ?>&dl=1"
           download
           title="Foto herunterladen"
           onclick="event.stopPropagation()">
            ⬇️
        </a>
        <button class="omm-foto-btn omm-btn-delete omm-delete-foto"
                data-foto-id="<?php echo (int)$foto->id; ?>"
                title="Foto löschen">
            🗑️
        </button>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ZIP-Hinweis -->
<?php if ( ! $zip_available ) : ?>
<div class="omm-zip-hint" style="margin-top:28px;margin-bottom:0">
    <span style="font-size:20px">📦</span>
    <span>ZIP-Download steht <?php echo $zip_date_human ? 'ab <strong>' . esc_html( $zip_date_human ) . '</strong>' : 'nach dem Event'; ?> im <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="color:#f0c040;text-decoration:underline">Downloads-Bereich</a> bereit.</span>
</div>
<?php endif; ?>

<!-- Link zum öffentlichen Album -->
<div style="margin-top:16px;padding:16px 20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;display:flex;align-items:center;gap:14px">
    <span style="font-size:24px">🖼️</span>
    <div style="flex:1">
        <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,.8)">Öffentliches Album</div>
        <div style="font-size:12px;color:rgba(255,255,255,.35);margin-top:2px">Alle öffentlichen Fotos der ÖMM <?php echo esc_html($event_year); ?> Community</div>
    </div>
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('omm-album')); ?>"
       style="background:rgba(240,192,64,.15);color:#f0c040;border:1px solid rgba(240,192,64,.25);font-family:'Oswald',sans-serif;font-size:12px;font-weight:700;padding:8px 16px;border-radius:8px;text-decoration:none;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap">
        → Zum Album
    </a>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="ommDeleteModal" role="dialog" aria-modal="true">
    <div id="ommDeleteBox">
        <div style="font-size:32px">🗑️</div>
        <p>Dieses Foto wirklich löschen?<br><span style="font-size:12px;color:rgba(255,255,255,.4)">Diese Aktion kann nicht rückgängig gemacht werden.</span></p>
        <div class="omm-del-btns">
            <button class="omm-del-btn-cancel" id="ommDelCancel">Abbrechen</button>
            <button class="omm-del-btn-confirm" id="ommDelConfirm">Löschen</button>
        </div>
    </div>
</div>

<!-- SWIPE LIGHTBOX -->
<div id="ommLightbox" role="dialog" aria-modal="true">
    <div id="ommLbBar">
        <span id="ommLbCounter"></span>
        <span id="ommLbCaption"></span>
        <button id="ommLbClose" aria-label="Schliessen">&#10005;</button>
    </div>
    <div id="ommLbTrackWrap">
        <div id="ommLbTrack"></div>
    </div>
    <div id="ommLbActions"></div>
    <div id="ommLbDots"></div>
    <button class="omm-lb-nav disabled" id="ommLbPrev">&#8592;</button>
    <button class="omm-lb-nav" id="ommLbNext">&#8594;</button>
</div>

<script>
(function(){
    'use strict';
    const REST  = <?php echo json_encode( rtrim( $rest_url, '/' ) ); ?>;
    const NONCE = <?php echo json_encode( $nonce_val ); ?>;
    const FOTOS = <?php echo json_encode( $fotos_json ); ?>;

    // ============================================================
    // SWIPE LIGHTBOX
    // ============================================================
    let lbIdx = 0;
    let lbBuilt = false;

    const lb      = document.getElementById('ommLightbox');
    const lbTrack = document.getElementById('ommLbTrack');
    const lbDots  = document.getElementById('ommLbDots');
    const lbPrev  = document.getElementById('ommLbPrev');
    const lbNext  = document.getElementById('ommLbNext');
    const lbClose = document.getElementById('ommLbClose');
    const lbCnt   = document.getElementById('ommLbCounter');
    const lbCap   = document.getElementById('ommLbCaption');
    const lbAct   = document.getElementById('ommLbActions');

    function buildLightbox() {
        if (lbBuilt) return;
        lbBuilt = true;
        lbTrack.innerHTML = '';
        lbDots.innerHTML  = '';
        lbAct.innerHTML   = '';

        FOTOS.forEach(function(f, i) {
            // Slide
            const slide = document.createElement('div');
            slide.className = 'omm-lb-slide';
            const img = document.createElement('img');
            img.src = f.url;
            img.alt = 'ÖMM Foto ' + (i+1);
            img.loading = i === 0 ? 'eager' : 'lazy';
            slide.appendChild(img);
            lbTrack.appendChild(slide);

            // Dot (max 40)
            if (FOTOS.length <= 40) {
                const dot = document.createElement('span');
                dot.className = 'omm-lb-dot';
                dot.dataset.i = i;
                dot.addEventListener('click', function(){ openAt(parseInt(this.dataset.i)); });
                lbDots.appendChild(dot);
            }
        });

        // Action-Buttons (Public-Toggle + Like) — aktualisieren sich per openAt
        const pubBtn = document.createElement('button');
        pubBtn.id = 'lbPubBtn';
        pubBtn.className = 'omm-foto-btn';
        pubBtn.addEventListener('click', function(){
            const f = FOTOS[lbIdx];
            handleTogglePublic(f.id, f.is_public, function(newVal){
                FOTOS[lbIdx].is_public = newVal;
                updateLbActions();
                // Grid-Karte auch updaten
                const card = document.querySelector('.omm-foto-card[data-foto-id="'+f.id+'"]');
                if (card) syncCardPublic(card, newVal);
            });
        });
        lbAct.appendChild(pubBtn);

        const likeBtn = document.createElement('button');
        likeBtn.id = 'lbLikeBtn';
        likeBtn.className = 'omm-foto-btn omm-btn-like';
        likeBtn.addEventListener('click', function(){
            const f = FOTOS[lbIdx];
            handleToggleLike(f.id, function(newCount, liked){
                FOTOS[lbIdx].like_count = newCount;
                FOTOS[lbIdx].user_liked = liked ? 1 : 0;
                updateLbActions();
                // Grid-Karte updaten
                const card = document.querySelector('.omm-foto-card[data-foto-id="'+f.id+'"]');
                if (card) {
                    const lc = card.querySelector('.like-count');
                    if (lc) lc.textContent = newCount;
                    const lb = card.querySelector('.omm-toggle-like');
                    if (lb) lb.classList.toggle('liked', liked);
                }
            });
        });
        lbAct.appendChild(likeBtn);

        const dlBtn = document.createElement('a');
        dlBtn.id = 'lbDlBtn';
        dlBtn.className = 'omm-foto-btn omm-btn-download';
        dlBtn.title = 'Foto herunterladen';
        dlBtn.textContent = '\u2b07\ufe0f';
        dlBtn.setAttribute('download', '');
        dlBtn.href = FOTOS[lbIdx] ? FOTOS[lbIdx].url + '&dl=1' : '#';
        lbAct.appendChild(dlBtn);

        const delBtn = document.createElement('button');
        delBtn.id = 'lbDelBtn';
        delBtn.className = 'omm-foto-btn omm-btn-delete';
        delBtn.title = 'Foto löschen';
        delBtn.textContent = '🗑️';
        delBtn.addEventListener('click', function(){
            const f = FOTOS[lbIdx];
            openDeleteModal(f.id, function(){
                // Lightbox schliessen + Slide entfernen
                lb.classList.remove('open');
                document.body.style.overflow = '';
                lbBuilt = false; // rebuild beim naechsten Oeffnen
                lbTrack.innerHTML = '';
                lbDots.innerHTML = '';
                lbAct.innerHTML = '';
            });
        });
        lbAct.appendChild(delBtn);

        if (FOTOS.length <= 40) {
            // Dots nicht bei sehr vielen Fotos
        } else {
            lbDots.style.display = 'none';
        }

        bindLbSwipe();
    }

    function openAt(idx) {
        if (!lbBuilt) buildLightbox();
        lbIdx = idx;
        lbTrack.style.transition = 'none';
        updateLbPos();
        lb.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function updateLbPos() {
        lbTrack.style.transform = 'translateX(-' + (lbIdx * 100) + '%)';
        lbCnt.textContent = (lbIdx + 1) + ' / ' + FOTOS.length;
        const f = FOTOS[lbIdx];
        lbCap.textContent = f.shot_at || '';
        lbPrev.classList.toggle('disabled', lbIdx === 0);
        lbNext.classList.toggle('disabled', lbIdx === FOTOS.length - 1);
        document.querySelectorAll('.omm-lb-dot').forEach(function(d, i){ d.classList.toggle('active', i === lbIdx); });
        updateLbActions();
    }

    function updateLbActions() {
        const f = FOTOS[lbIdx];
        const pb = document.getElementById('lbPubBtn');
        const lk = document.getElementById('lbLikeBtn');
        const dl = document.getElementById('lbDlBtn');
        if (!pb || !lk || !f) return;
        pb.className = 'omm-foto-btn ' + (f.is_public ? 'omm-btn-public' : 'omm-btn-private');
        pb.textContent = f.is_public ? '🌍 Öffentlich' : '🔒 Privat';
        lk.className = 'omm-foto-btn omm-btn-like' + (f.user_liked ? ' liked' : '');
        lk.innerHTML = '❤️ <span>' + f.like_count + '</span>';
        if (dl) dl.href = f.url + '&dl=1';
    }

    function goLb(idx) {
        if (idx < 0 || idx >= FOTOS.length) return;
        lbIdx = idx;
        lbTrack.style.transition = 'transform .3s cubic-bezier(.4,0,.2,1)';
        updateLbPos();
    }

    function closeLb() {
        lb.classList.remove('open');
        document.body.style.overflow = '';
    }

    lbClose.addEventListener('click', closeLb);
    lbPrev.addEventListener('click', function(){ goLb(lbIdx - 1); });
    lbNext.addEventListener('click', function(){ goLb(lbIdx + 1); });

    document.addEventListener('keydown', function(e){
        if (!lb.classList.contains('open')) return;
        if (e.key === 'ArrowLeft')  goLb(lbIdx - 1);
        if (e.key === 'ArrowRight') goLb(lbIdx + 1);
        if (e.key === 'Escape')     closeLb();
    });

    function bindLbSwipe() {
        const wrap = document.getElementById('ommLbTrackWrap');
        let sx = 0, dx = 0, drag = false;

        wrap.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; drag = true; dx = 0; }, {passive:true});
        wrap.addEventListener('touchmove',  function(e){
            if (!drag) return;
            dx = e.touches[0].clientX - sx;
            lbTrack.style.transition = 'none';
            lbTrack.style.transform = 'translateX(calc(-' + (lbIdx*100) + '% + ' + dx + 'px))';
        }, {passive:true});
        wrap.addEventListener('touchend', function(){
            drag = false;
            if      (dx < -60 && lbIdx < FOTOS.length-1) goLb(lbIdx + 1);
            else if (dx >  60 && lbIdx > 0)              goLb(lbIdx - 1);
            else { lbTrack.style.transition = 'transform .3s cubic-bezier(.4,0,.2,1)'; updateLbPos(); }
            dx = 0;
        });

        // Mouse drag
        wrap.addEventListener('mousedown',  function(e){ sx = e.clientX; drag = true; dx = 0; wrap.style.cursor = 'grabbing'; });
        wrap.addEventListener('mousemove',  function(e){
            if (!drag) return;
            dx = e.clientX - sx;
            lbTrack.style.transition = 'none';
            lbTrack.style.transform = 'translateX(calc(-' + (lbIdx*100) + '% + ' + dx + 'px))';
        });
        wrap.addEventListener('mouseup',    finishDrag);
        wrap.addEventListener('mouseleave', function(){ if (drag) finishDrag(); });

        function finishDrag() {
            drag = false; wrap.style.cursor = '';
            if      (dx < -60 && lbIdx < FOTOS.length-1) goLb(lbIdx + 1);
            else if (dx >  60 && lbIdx > 0)              goLb(lbIdx - 1);
            else { lbTrack.style.transition = 'transform .3s cubic-bezier(.4,0,.2,1)'; updateLbPos(); }
            dx = 0;
        }
    }

    // Klick auf Grid-Karte -> Lightbox öffnen
    document.querySelectorAll('.omm-foto-card').forEach(function(card){
        card.addEventListener('click', function(e){
            if (e.target.closest('button')) return; // Button-Klick nicht
            openAt(parseInt(card.dataset.idx));
        });
        // Touch: Overlay kurz zeigen
        card.addEventListener('touchend', function(e){
            if (e.target.closest('button')) return;
            // kurzes Touch -> Lightbox; langes -> nur Overlay
            card.classList.add('touch-show');
            clearTimeout(card._t);
            card._t = setTimeout(function(){ card.classList.remove('touch-show'); }, 2000);
        });
    });

    // ============================================================
    // TOGGLE PUBLIC (Grid + Lightbox)
    // ============================================================
    function handleTogglePublic(fotoId, isPublic, cb) {
        fetch(REST + '/foto/toggle-public', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            body: JSON.stringify({ foto_id: fotoId, public: !isPublic })
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d.success && cb) cb(d.is_public);
        }).catch(function(e){ console.error('toggle-public failed', e); });
    }

    function syncCardPublic(card, newVal) {
        const btn = card.querySelector('.omm-toggle-public');
        if (btn) {
            btn.className = 'omm-foto-btn ' + (newVal ? 'omm-btn-public' : 'omm-btn-private') + ' omm-toggle-public';
            btn.textContent = newVal ? '🌍' : '🔒';
            btn.dataset.public = newVal;
        }
        card.dataset.public = newVal;
        let badge = card.querySelector('.omm-foto-badge-pub');
        if (newVal && !badge) {
            badge = document.createElement('div');
            badge.className = 'omm-foto-badge-pub';
            badge.textContent = 'ÖFFENTLICH';
            card.appendChild(badge);
        } else if (!newVal && badge) {
            badge.remove();
        }
    }

    // Grid Public-Toggle-Buttons
    document.querySelectorAll('.omm-toggle-public').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            const fotoId   = parseInt(this.dataset.fotoId);
            const isPublic = parseInt(this.dataset.public);
            const card     = this.closest('.omm-foto-card');
            const fidx     = parseInt(card.dataset.idx);
            handleTogglePublic(fotoId, isPublic, function(newVal){
                FOTOS[fidx].is_public = newVal;
                syncCardPublic(card, newVal);
            });
        });
    });

    // ============================================================
    // TOGGLE LIKE
    // ============================================================
    function handleToggleLike(fotoId, cb) {
        fetch(REST + '/foto/like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            body: JSON.stringify({ foto_id: fotoId })
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d.success && cb) cb(d.like_count, d.liked);
        }).catch(function(e){ console.error('like failed', e); });
    }

    document.querySelectorAll('.omm-toggle-like').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            const fotoId = parseInt(this.dataset.fotoId);
            const card   = this.closest('.omm-foto-card');
            const fidx   = parseInt(card.dataset.idx);
            const self   = this;
            handleToggleLike(fotoId, function(newCount, liked){
                FOTOS[fidx].like_count = newCount;
                FOTOS[fidx].user_liked = liked ? 1 : 0;
                const lc = self.querySelector('.like-count');
                if (lc) lc.textContent = newCount;
                self.classList.toggle('liked', liked);
            });
        });
    });

    // ============================================================
    // DELETE FOTO
    // ============================================================
    let _deleteTarget = null; // { fotoId, cb }

    const delModal   = document.getElementById('ommDeleteModal');
    const delCancel  = document.getElementById('ommDelCancel');
    const delConfirm = document.getElementById('ommDelConfirm');

    function openDeleteModal(fotoId, afterDeleteCb) {
        _deleteTarget = { fotoId: fotoId, cb: afterDeleteCb || null };
        delModal.classList.add('open');
    }

    function closeDeleteModal() {
        delModal.classList.remove('open');
        _deleteTarget = null;
    }

    delCancel.addEventListener('click', closeDeleteModal);
    delModal.addEventListener('click', function(e){ if (e.target === delModal) closeDeleteModal(); });

    delConfirm.addEventListener('click', function(){
        if (!_deleteTarget) return;
        const fotoId = _deleteTarget.fotoId;
        const cb     = _deleteTarget.cb;
        closeDeleteModal();

        delConfirm.disabled = true;
        delConfirm.textContent = '...';

        fetch(REST + '/foto/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            body: JSON.stringify({ foto_id: fotoId })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            delConfirm.disabled = false;
            delConfirm.textContent = 'L\u00f6schen';
            if (d.success) {
                // Aus FOTOS-Array entfernen
                const fidx = FOTOS.findIndex(function(f){ return f.id === fotoId; });
                if (fidx !== -1) FOTOS.splice(fidx, 1);

                // Grid-Karte entfernen
                const card = document.querySelector('.omm-foto-card[data-foto-id="'+fotoId+'"]');
                if (card) {
                    card.style.transition = 'opacity .25s,transform .25s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.9)';
                    setTimeout(function(){ card.remove(); updateFotoCount(); }, 260);
                }

                // Callback (z.B. Lightbox schliessen)
                if (cb) cb();
            } else {
                alert('L\u00f6schen fehlgeschlagen: ' + (d.message || 'Unbekannter Fehler'));
            }
        })
        .catch(function(e){
            delConfirm.disabled = false;
            delConfirm.textContent = 'L\u00f6schen';
            console.error('delete failed', e);
            alert('Netzwerkfehler beim L\u00f6schen.');
        });
    });

    // Grid Delete-Buttons
    document.querySelectorAll('.omm-delete-foto').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            const fotoId = parseInt(this.dataset.fotoId);
            openDeleteModal(fotoId, null);
        });
    });

    // ============================================================
    // USER UPLOAD
    // ============================================================
    const uploadModal   = document.getElementById('ommUploadModal');
    const uploadOpen    = document.getElementById('ommUploadOpen');
    const uploadCancel  = document.getElementById('ommUploadCancel');
    const uploadSend    = document.getElementById('ommUploadSend');
    const uploadFile    = document.getElementById('ommUploadFile');
    const uploadPublic  = document.getElementById('ommUploadPublic');
    const dropZone      = document.getElementById('ommDropZone');
    const dropPreview   = document.getElementById('ommDropPreview');
    const uploadMsg     = document.getElementById('ommUploadMsg');
    const uploadProg    = document.getElementById('ommUploadProgress');
    const uploadBar     = document.getElementById('ommUploadBar');

    uploadOpen.addEventListener('click', function(){
        uploadModal.classList.add('open');
    });
    function closeUploadModal() {
        uploadModal.classList.remove('open');
        uploadFile.value = '';
        dropPreview.style.display = 'none';
        dropPreview.src = '';
        uploadSend.disabled = true;
        uploadMsg.textContent = '';
        uploadProg.style.display = 'none';
        uploadBar.style.width = '0';
        uploadPublic.checked = false;
    }
    uploadCancel.addEventListener('click', closeUploadModal);
    uploadModal.addEventListener('click', function(e){ if(e.target === uploadModal) closeUploadModal(); });

    function handleFileSelect(file) {
        if (!file) return;
        const maxMB = 25;
        if (file.size > maxMB * 1024 * 1024) {
            uploadMsg.textContent = 'Datei zu groß (max ' + maxMB + ' MB)';
            uploadMsg.style.color = '#f87171';
            uploadSend.disabled = true;
            return;
        }
        uploadMsg.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
        uploadMsg.style.color = 'rgba(255,255,255,.5)';
        uploadSend.disabled = false;
        const reader = new FileReader();
        reader.onload = function(e){ dropPreview.src = e.target.result; dropPreview.style.display = 'block'; };
        reader.readAsDataURL(file);
    }

    uploadFile.addEventListener('change', function(){ handleFileSelect(this.files[0]); });

    // Drag & Drop
    dropZone.addEventListener('dragover', function(e){ e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', function(){ dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function(e){
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const f = e.dataTransfer.files[0];
        if (f) { uploadFile.files = e.dataTransfer.files; handleFileSelect(f); }
    });

    uploadSend.addEventListener('click', function(){
        const file = uploadFile.files[0];
        if (!file) return;

        uploadSend.disabled = true;
        uploadSend.textContent = 'Lädt...';
        uploadMsg.textContent = '';
        uploadProg.style.display = 'block';
        uploadBar.style.width = '0';

        const fd = new FormData();
        fd.append('datei', file);
        fd.append('is_public', uploadPublic.checked ? '1' : '0');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', REST + '/foto/user-upload');
        xhr.setRequestHeader('X-WP-Nonce', NONCE);

        xhr.upload.addEventListener('progress', function(e){
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                uploadBar.style.width = pct + '%';
                uploadMsg.textContent = pct + '%';
                uploadMsg.style.color = 'rgba(255,255,255,.5)';
            }
        });

        xhr.addEventListener('load', function(){
            uploadSend.disabled = false;
            uploadSend.textContent = 'Hochladen';
            try {
                const d = JSON.parse(xhr.responseText);
                if (d.success) {
                    uploadBar.style.width = '100%';
                    uploadMsg.textContent = '✅ Hochgeladen!';
                    uploadMsg.style.color = '#4ade80';

                    // Neues Foto in FOTOS-Array vorne einfuegen
                    const newF = {
                        id: d.foto_id,
                        url: d.url,
                        is_public: d.is_public,
                        like_count: 0,
                        user_liked: 0,
                        shot_at: '',
                        is_video: 0,
                    };
                    FOTOS.unshift(newF);

                    // Neue Karte oben im Grid einfügen
                    const grid = document.getElementById('omm-fotos-grid');
                    if (grid) {
                        const card = document.createElement('div');
                        card.className = 'omm-foto-card';
                        card.dataset.fotoId = d.foto_id;
                        card.dataset.idx = '0';
                        card.dataset.public = d.is_public;
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.9)';
                        card.style.transition = 'opacity .3s,transform .3s';
                        card.innerHTML = '<img src="'+d.url+'" alt="ÖMM Foto" />';
                        if (d.is_public) {
                            const badge = document.createElement('div');
                            badge.className = 'omm-foto-badge-pub';
                            badge.textContent = 'ÖFFENTLICH';
                            card.appendChild(badge);
                        }

                        // Overlay mit Buttons
                        const overlay = document.createElement('div');
                        overlay.className = 'omm-foto-overlay';
                        overlay.innerHTML =
                            '<button class="omm-foto-btn '+(d.is_public?'omm-btn-public':'omm-btn-private')+' omm-toggle-public" data-foto-id="'+d.foto_id+'" data-public="'+d.is_public+'">'+(d.is_public?'🌍':'🔒')+'</button>'
                            +'<button class="omm-foto-btn omm-btn-like omm-toggle-like" data-foto-id="'+d.foto_id+'">❤️ <span class="like-count">0</span></button>'
                            +'<a class="omm-foto-btn omm-btn-download omm-dl-foto" href="'+d.url+'&dl=1" download onclick="event.stopPropagation()">⬇️</a>'
                            +'<button class="omm-foto-btn omm-btn-delete omm-delete-foto" data-foto-id="'+d.foto_id+'" title="Foto löschen">🗑️</button>';
                        card.appendChild(overlay);

                        // Click-Handler für Lightbox rebuild
                        card.addEventListener('click', function(){
                            lbBuilt = false; lbTrack.innerHTML=''; lbDots.innerHTML=''; lbAct.innerHTML='';
                            openAt(0);
                        });

                        grid.insertBefore(card, grid.firstChild);
                        requestAnimationFrame(function(){
                            requestAnimationFrame(function(){
                                card.style.opacity = '1';
                                card.style.transform = 'scale(1)';
                            });
                        });
                    }

                    // Lightbox rebuild erzwingen
                    lbBuilt = false;

                    setTimeout(closeUploadModal, 1500);
                    updateFotoCount();
                } else {
                    uploadMsg.textContent = '❌ ' + (d.message || 'Fehler beim Hochladen');
                    uploadMsg.style.color = '#f87171';
                }
            } catch(e) {
                uploadMsg.textContent = '❌ Serverfehler';
                uploadMsg.style.color = '#f87171';
            }
        });

        xhr.addEventListener('error', function(){
            uploadSend.disabled = false;
            uploadSend.textContent = 'Hochladen';
            uploadMsg.textContent = '❌ Netzwerkfehler';
            uploadMsg.style.color = '#f87171';
        });

        xhr.send(fd);
    });

    function updateFotoCount() {
        const countEl = document.querySelector('#omm-fotos-grid');
        if (!countEl) return;
        const remaining = document.querySelectorAll('.omm-foto-card').length;
        const pubCount  = FOTOS.filter(function(f){ return f.is_public; }).length;
        const info = countEl.previousElementSibling;
        if (info && info.tagName === 'DIV') {
            info.innerHTML = remaining + ' Foto' + (remaining !== 1 ? 's' : '') +
                ' &nbsp;&middot;&nbsp; ' + pubCount + ' \u00f6ffentlich' +
                '<span style="color:rgba(255,255,255,.2);margin-left:6px">\u00b7 Antippen zum Swipen</span>';
        }
        if (remaining === 0) {
            const grid = document.getElementById('omm-fotos-grid');
            if (grid) grid.innerHTML = '<div class="omm-empty" style="grid-column:1/-1"><div class="omm-empty-icon">📷</div><div style="font-family:\u2019Oswald\u2019,sans-serif;font-size:18px;font-weight:600;color:rgba(255,255,255,.5);margin-bottom:8px">Keine Fotos mehr</div></div>';
        }
    }

})();
</script>
