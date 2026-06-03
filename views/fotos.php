<?php defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$event_year = (int) get_option( 'oemm_event_year', date( 'Y' ) );

// Fotos laden
global $wpdb;
$fotos_table = $wpdb->prefix . 'oemm_xxvi_fotos';
$likes_table = $wpdb->prefix . 'oemm_xxvi_foto_likes';

$fotos = [];
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fotos_table ) ) === $fotos_table ) {
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

// ZIP verfügbar ab?
$zip_date_str   = get_option( 'oemm_zip_available_date', '' );
$zip_available  = $zip_date_str && ( strtotime( $zip_date_str ) <= time() );
$zip_date_human = $zip_date_str ? date_i18n( 'd.m.Y', strtotime( $zip_date_str ) ) : '';

$rest_url  = rest_url( 'oemm-xxvi/v1' );
$nonce_val = wp_create_nonce( 'wp_rest' );
?>
<style>
/* === MEINE FOTOS === */
/* View-Toggle */
.omm-view-toggle{display:flex;gap:6px;margin-bottom:16px}
.omm-view-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.5);padding:6px 14px;border-radius:8px;font-size:12px;font-family:'Oswald',sans-serif;letter-spacing:.4px;cursor:pointer;transition:all .15s;text-transform:uppercase}
.omm-view-btn.active{background:rgba(240,192,64,.15);border-color:rgba(240,192,64,.35);color:#f0c040}

/* Carousel */
.omm-carousel-wrap{position:relative;touch-action:pan-y;user-select:none;-webkit-user-select:none}
.omm-carousel{overflow:hidden;border-radius:14px;background:#0d0d0d;box-shadow:0 4px 32px rgba(0,0,0,.5)}
.omm-carousel-track{display:flex;transition:transform .3s cubic-bezier(.4,0,.2,1);will-change:transform}
.omm-carousel-slide{min-width:100%;position:relative;aspect-ratio:4/3;background:#111;display:flex;align-items:center;justify-content:center;overflow:hidden}
.omm-carousel-slide img{width:100%;height:100%;object-fit:contain;display:block;pointer-events:none}
.omm-carousel-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.1);color:#fff;width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;z-index:10;transition:background .15s;-webkit-tap-highlight-color:transparent;outline:none}
.omm-carousel-nav:hover{background:rgba(240,192,64,.25)}
.omm-carousel-nav.disabled{opacity:.2;pointer-events:none}
.omm-carousel-nav-prev{left:10px}
.omm-carousel-nav-next{right:10px}
.omm-carousel-badge{position:absolute;bottom:10px;right:12px;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);color:#fff;font-family:'Oswald',sans-serif;font-size:11px;letter-spacing:.05em;padding:3px 8px;border-radius:20px;pointer-events:none}
.omm-carousel-shot{position:absolute;top:8px;left:8px;font-size:10px;color:rgba(255,255,255,.5);background:rgba(0,0,0,.5);padding:2px 6px;border-radius:4px;backdrop-filter:blur(4px);pointer-events:none}
.omm-carousel-dots{display:flex;justify-content:center;gap:6px;margin-top:12px;flex-wrap:wrap;overflow:hidden;max-height:32px}
.omm-carousel-dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.2);cursor:pointer;transition:background .2s,transform .2s;-webkit-tap-highlight-color:transparent}
.omm-carousel-dot.active{background:#f0c040;transform:scale(1.3)}
.omm-slide-overlay{position:absolute;inset:0;background:linear-gradient(transparent 55%,rgba(0,0,0,.8));display:flex;align-items:flex-end;padding:10px 12px;gap:6px;opacity:0;transition:opacity .2s;pointer-events:none}
.omm-carousel-slide:hover .omm-slide-overlay,.omm-carousel-slide.touch-show .omm-slide-overlay{opacity:1;pointer-events:all}

.omm-fotos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:20px}
.omm-foto-card{position:relative;border-radius:12px;overflow:hidden;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);aspect-ratio:4/3;cursor:pointer;transition:transform .15s,border-color .15s}
.omm-foto-card:hover{transform:translateY(-2px);border-color:rgba(240,192,64,.4)}
.omm-foto-card img{width:100%;height:100%;object-fit:cover;display:block}
.omm-foto-overlay{position:absolute;bottom:0;left:0;right:0;padding:8px 10px;background:linear-gradient(transparent,rgba(0,0,0,.75));display:flex;align-items:center;gap:6px;opacity:0;transition:opacity .15s}
.omm-foto-card:hover .omm-foto-overlay{opacity:1}
.omm-foto-card.always-show-overlay .omm-foto-overlay{opacity:1}
.omm-foto-btn{border:none;border-radius:6px;padding:4px 9px;font-size:11px;font-weight:600;cursor:pointer;transition:background .12s;font-family:'Oswald',sans-serif;letter-spacing:.3px}
.omm-btn-public{background:rgba(34,197,94,.2);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.omm-btn-public:hover{background:rgba(34,197,94,.35)}
.omm-btn-private{background:rgba(255,255,255,.12);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.15)}
.omm-btn-private:hover{background:rgba(255,255,255,.2)}
.omm-btn-like{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.25);margin-left:auto}
.omm-btn-like:hover{background:rgba(239,68,68,.3)}
.omm-btn-like.liked{background:rgba(239,68,68,.35);color:#ff6b6b;border-color:rgba(239,68,68,.5)}
.omm-foto-badge-pub{position:absolute;top:8px;right:8px;background:rgba(34,197,94,.85);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;font-family:'Oswald',sans-serif;letter-spacing:.5px;backdrop-filter:blur(4px)}
.omm-zip-hint{background:rgba(240,192,64,.08);border:1px solid rgba(240,192,64,.2);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;color:rgba(240,192,64,.9);font-size:13px}
.omm-empty{text-align:center;padding:60px 20px;color:rgba(255,255,255,.3)}
.omm-empty-icon{font-size:48px;margin-bottom:12px}
.omm-foto-lightbox{display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);align-items:center;justify-content:center;padding:20px}
.omm-foto-lightbox.open{display:flex}
.omm-foto-lightbox img{max-width:90vw;max-height:85vh;border-radius:8px;object-fit:contain;box-shadow:0 0 60px rgba(0,0,0,.8)}
.omm-lightbox-close{position:absolute;top:20px;right:24px;color:#fff;font-size:32px;cursor:pointer;line-height:1;opacity:.7}
.omm-lightbox-close:hover{opacity:1}
</style>

<h2 style="font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:6px">📷 Meine Fotos</h2>
<p style="font-size:13px;color:rgba(255,255,255,.45);margin-bottom:14px">Deine Fotobox-Bilder vom ÖMM <?php echo esc_html( $event_year ); ?></p>

<?php if ( ! empty( $fotos ) ) : ?>
<div class="omm-view-toggle">
    <button class="omm-view-btn" id="omm-view-swipe" onclick="ommSetView('swipe')">&#8644; Swipe</button>
    <button class="omm-view-btn active" id="omm-view-grid" onclick="ommSetView('grid')">&#9638; Grid</button>
</div>
<?php endif; ?>

<?php if ( ! $zip_available ) : ?>
<div class="omm-zip-hint">
    <span style="font-size:20px">📦</span>
    <span>ZIP-Download deiner Fotos steht <?php echo $zip_date_human ? 'ab <strong>' . esc_html( $zip_date_human ) . '</strong>' : 'nach dem Event'; ?> im <a href="<?php echo esc_url( wc_get_account_endpoint_url('omm-downloads') ); ?>" style="color:#f0c040;text-decoration:underline">Downloads-Bereich</a> bereit.</span>
</div>
<?php endif; ?>

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
</div>

<!-- CAROUSEL -->
<div id="omm-carousel-view" style="display:none">
    <div class="omm-carousel-wrap" id="ommCarouselWrap">
        <div class="omm-carousel" id="ommCarousel">
            <div class="omm-carousel-track" id="ommCarouselTrack"></div>
            <button class="omm-carousel-nav omm-carousel-nav-prev disabled" id="ommNavPrev">&#8592;</button>
            <button class="omm-carousel-nav omm-carousel-nav-next" id="ommNavNext">&#8594;</button>
        </div>
        <div class="omm-carousel-dots" id="ommDots"></div>
    </div>
</div>

<!-- GRID -->
<div id="omm-grid-view">
<div class="omm-fotos-grid" id="omm-fotos-grid">
<?php foreach ( $fotos as $foto ) :
    $serve_url = oemm_xxvi_fotos_get_serve_url( (int) $foto->id, (int) $foto->user_id );
    $is_public  = (int) $foto->is_public;
    $like_count = (int) $foto->like_count;
    $user_liked = (int) $foto->user_liked;
    $shot_label = $foto->shot_at ? date_i18n( 'd.m.Y H:i', strtotime( $foto->shot_at ) ) : '';
?>
<div class="omm-foto-card always-show-overlay" data-foto-id="<?php echo (int)$foto->id; ?>" data-public="<?php echo $is_public; ?>">
    <img src="<?php echo esc_url( $serve_url ); ?>" alt="ÖMM Foto" loading="lazy"
         onclick="ommOpenLightbox('<?php echo esc_js( $serve_url ); ?>', '<?php echo esc_js( $shot_label ); ?>')" />

    <?php if ( $is_public ) : ?>
    <div class="omm-foto-badge-pub">ÖFFENTLICH</div>
    <?php endif; ?>

    <div class="omm-foto-overlay">
        <button class="omm-foto-btn <?php echo $is_public ? 'omm-btn-public' : 'omm-btn-private'; ?> omm-toggle-public"
                data-foto-id="<?php echo (int)$foto->id; ?>"
                data-public="<?php echo $is_public; ?>"
                title="<?php echo $is_public ? 'Klicken um privat zu machen' : 'Klicken um öffentlich zu machen'; ?>">
            <?php echo $is_public ? '🌍 Öffentlich' : '🔒 Privat'; ?>
        </button>
        <button class="omm-foto-btn omm-btn-like omm-toggle-like <?php echo $user_liked ? 'liked' : ''; ?>"
                data-foto-id="<?php echo (int)$foto->id; ?>"
                title="Like">
            ❤️ <span class="like-count"><?php echo $like_count; ?></span>
        </button>
    </div>
    <?php if ( $shot_label ) : ?>
    <div style="position:absolute;top:8px;left:8px;font-size:10px;color:rgba(255,255,255,.5);background:rgba(0,0,0,.5);padding:2px 6px;border-radius:4px;backdrop-filter:blur(4px)"><?php echo esc_html( $shot_label ); ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div><!-- /omm-grid-view -->
<?php endif; ?>

<!-- Link zum öffentlichen Album -->
<div style="margin-top:28px;padding:16px 20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;display:flex;align-items:center;gap:14px">
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

<!-- Lightbox -->
<div class="omm-foto-lightbox" id="ommLightbox" onclick="ommCloseLightbox(event)">
    <span class="omm-lightbox-close" onclick="ommCloseLightbox()">&times;</span>
    <div style="text-align:center">
        <img id="ommLightboxImg" src="" alt="" />
        <div id="ommLightboxCaption" style="color:rgba(255,255,255,.4);font-size:12px;margin-top:8px"></div>
    </div>
</div>

<script>
const OMM_FOTOS = <?php
    $fotos_json = array_map(function($f) {
        return [
            'id'        => (int)$f->id,
            'url'       => oemm_xxvi_fotos_get_serve_url( (int)$f->id, (int)$f->user_id ),
            'is_public' => (int)$f->is_public,
            'like_count'=> (int)$f->like_count,
            'user_liked'=> (int)$f->user_liked,
            'shot_at'   => $f->shot_at ? date_i18n('d.m.Y H:i', strtotime($f->shot_at)) : '',
        ];
    }, $fotos ?? []);
    echo json_encode($fotos_json);
?>;

(function(){
    const REST  = <?php echo json_encode( rtrim( $rest_url, '/' ) ); ?>;
    const NONCE = <?php echo json_encode( $nonce_val ); ?>;

    // ---- VIEW TOGGLE ----
    let currentView = 'grid';
    window.ommSetView = function(view) {
        currentView = view;
        const gv = document.getElementById('omm-grid-view');
        const cv = document.getElementById('omm-carousel-view');
        if (gv) gv.style.display     = view === 'grid'  ? '' : 'none';
        if (cv) cv.style.display     = view === 'swipe' ? '' : 'none';
        const bg = document.getElementById('omm-view-grid');
        const bs = document.getElementById('omm-view-swipe');
        if (bg) bg.classList.toggle('active', view === 'grid');
        if (bs) bs.classList.toggle('active', view === 'swipe');
        if (view === 'swipe' && !carouselBuilt) buildCarousel();
    };

    // ---- CAROUSEL ----
    let carouselBuilt = false, curIdx = 0;
    const fotos = window.OMM_FOTOS || [];

    function buildCarousel() {
        carouselBuilt = true;
        const track = document.getElementById('ommCarouselTrack');
        const dots  = document.getElementById('ommDots');
        if (!track) return;
        track.innerHTML = ''; dots.innerHTML = '';

        fotos.forEach(function(f, i) {
            const slide = document.createElement('div');
            slide.className = 'omm-carousel-slide';
            slide.dataset.idx = i;

            const img = document.createElement('img');
            img.src = f.url; img.alt = '\u00d6MM Foto '+(i+1);
            img.loading = i === 0 ? 'eager' : 'lazy';
            img.addEventListener('click', function(){ ommOpenLightbox(f.url, f.shot_at); });
            slide.appendChild(img);

            if (f.shot_at) {
                const ts = document.createElement('div');
                ts.className = 'omm-carousel-shot'; ts.textContent = f.shot_at;
                slide.appendChild(ts);
            }
            const badge = document.createElement('div');
            badge.className = 'omm-carousel-badge';
            badge.textContent = (i+1)+' / '+fotos.length;
            slide.appendChild(badge);

            const ov = document.createElement('div'); ov.className = 'omm-slide-overlay';
            const pb = document.createElement('button');
            pb.className = 'omm-foto-btn '+(f.is_public?'omm-btn-public':'omm-btn-private')+' omm-toggle-public';
            pb.dataset.fotoId = f.id; pb.dataset.public = f.is_public;
            pb.textContent = f.is_public ? '\ud83c\udf0d \u00d6ffentlich' : '\ud83d\udd12 Privat';
            pb.addEventListener('click', handleTogglePublic); ov.appendChild(pb);
            const lb = document.createElement('button');
            lb.className = 'omm-foto-btn omm-btn-like omm-toggle-like'+(f.user_liked?' liked':'');
            lb.dataset.fotoId = f.id; lb.style.marginLeft = 'auto';
            lb.innerHTML = '\u2764\ufe0f <span class="like-count">'+f.like_count+'</span>';
            lb.addEventListener('click', handleToggleLike); ov.appendChild(lb);
            slide.appendChild(ov);

            slide.addEventListener('touchend', function(){
                slide.classList.add('touch-show');
                clearTimeout(slide._t);
                slide._t = setTimeout(function(){ slide.classList.remove('touch-show'); }, 2500);
            });
            track.appendChild(slide);

            if (fotos.length <= 30) {
                const dot = document.createElement('span');
                dot.className = 'omm-carousel-dot'+(i===0?' active':'');
                dot.dataset.idx = i;
                dot.addEventListener('click', function(){ goTo(parseInt(this.dataset.idx)); });
                dots.appendChild(dot);
            }
        });
        if (fotos.length > 30) dots.style.display = 'none';
        updateCarousel(); bindSwipe();
    }

    function goTo(idx) {
        if (idx < 0 || idx >= fotos.length) return;
        curIdx = idx;
        const t = document.getElementById('ommCarouselTrack');
        if (t) t.style.transition = 'transform .3s cubic-bezier(.4,0,.2,1)';
        updateCarousel();
    }
    function updateCarousel() {
        const t = document.getElementById('ommCarouselTrack');
        const p = document.getElementById('ommNavPrev');
        const n = document.getElementById('ommNavNext');
        if (!t) return;
        t.style.transform = 'translateX(-'+(curIdx*100)+'%)';
        if (p) p.classList.toggle('disabled', curIdx === 0);
        if (n) n.classList.toggle('disabled', curIdx === fotos.length-1);
        document.querySelectorAll('.omm-carousel-dot').forEach(function(d,i){ d.classList.toggle('active', i===curIdx); });
    }
    const np = document.getElementById('ommNavPrev'); if(np) np.addEventListener('click', function(){ goTo(curIdx-1); });
    const nn = document.getElementById('ommNavNext'); if(nn) nn.addEventListener('click', function(){ goTo(curIdx+1); });
    document.addEventListener('keydown', function(e){
        if (currentView !== 'swipe') return;
        if (e.key==='ArrowLeft')  goTo(curIdx-1);
        if (e.key==='ArrowRight') goTo(curIdx+1);
    });

    function bindSwipe() {
        const w = document.getElementById('ommCarouselWrap'); if(!w) return;
        let sx=0, dx=0, drag=false;
        function applyDrag() {
            const t = document.getElementById('ommCarouselTrack');
            t.style.transition = 'none';
            t.style.transform  = 'translateX(calc(-'+(curIdx*100)+'% + '+dx+'px))';
        }
        function finishDrag() {
            drag=false; w.style.cursor='';
            if      (dx < -50 && curIdx < fotos.length-1) goTo(curIdx+1);
            else if (dx >  50 && curIdx > 0)              goTo(curIdx-1);
            else { const t=document.getElementById('ommCarouselTrack'); t.style.transition='transform .3s cubic-bezier(.4,0,.2,1)'; updateCarousel(); }
            dx=0;
        }
        w.addEventListener('touchstart', function(e){ sx=e.touches[0].clientX; drag=true; dx=0; },{passive:true});
        w.addEventListener('touchmove',  function(e){ if(!drag)return; dx=e.touches[0].clientX-sx; applyDrag(); },{passive:true});
        w.addEventListener('touchend',   function(){ if(drag)finishDrag(); });
        w.addEventListener('mousedown',  function(e){ sx=e.clientX; drag=true; dx=0; w.style.cursor='grabbing'; });
        w.addEventListener('mousemove',  function(e){ if(!drag)return; dx=e.clientX-sx; applyDrag(); });
        w.addEventListener('mouseup',    finishDrag);
        w.addEventListener('mouseleave', function(){ if(drag)finishDrag(); });
    }
    // ---- END CAROUSEL ----

    // --- Public Toggle ---
    document.querySelectorAll('.omm-toggle-public').forEach(btn => {
        btn.addEventListener('click', async function(e){
            e.stopPropagation();
            const fotoId    = parseInt(this.dataset.fotoId);
            const isPublic  = parseInt(this.dataset.public);
            const newPublic = !isPublic;
            this.disabled   = true;

            try {
                const r = await fetch(REST + '/foto/toggle-public', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body: JSON.stringify({ foto_id: fotoId, public: newPublic })
                });
                const d = await r.json();
                if (d.success) {
                    // Karte neu stylen
                    const card = document.querySelector(`.omm-foto-card[data-foto-id="${fotoId}"]`);
                    this.dataset.public = newPublic ? 1 : 0;
                    this.textContent    = newPublic ? '🌍 Öffentlich' : '🔒 Privat';
                    this.className      = 'omm-foto-btn ' + (newPublic ? 'omm-btn-public' : 'omm-btn-private') + ' omm-toggle-public';
                    this.title          = newPublic ? 'Klicken um privat zu machen' : 'Klicken um öffentlich zu machen';
                    // Badge
                    let badge = card.querySelector('.omm-foto-badge-pub');
                    if (newPublic && !badge) {
                        badge = document.createElement('div');
                        badge.className = 'omm-foto-badge-pub';
                        badge.textContent = 'ÖFFENTLICH';
                        card.appendChild(badge);
                    } else if (!newPublic && badge) {
                        badge.remove();
                    }
                }
            } catch(err) { console.error('Toggle failed', err); }
            this.disabled = false;
        });
    });

    // --- Like Toggle ---
    document.querySelectorAll('.omm-toggle-like').forEach(btn => {
        btn.addEventListener('click', async function(e){
            e.stopPropagation();
            const fotoId = parseInt(this.dataset.fotoId);
            this.disabled = true;

            try {
                const r = await fetch(REST + '/foto/like', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body: JSON.stringify({ foto_id: fotoId })
                });
                const d = await r.json();
                if (d.success) {
                    this.querySelector('.like-count').textContent = d.like_count;
                    this.classList.toggle('liked', d.liked);
                }
            } catch(err) { console.error('Like failed', err); }
            this.disabled = false;
        });
    });

    // --- Lightbox ---
    window.ommOpenLightbox = function(url, caption) {
        document.getElementById('ommLightboxImg').src     = url;
        document.getElementById('ommLightboxCaption').textContent = caption || '';
        document.getElementById('ommLightbox').classList.add('open');
    };
    window.ommCloseLightbox = function(e) {
        if (!e || e.target === document.getElementById('ommLightbox') || e.target.classList.contains('omm-lightbox-close')) {
            document.getElementById('ommLightbox').classList.remove('open');
        }
    };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') window.ommCloseLightbox({target:document.getElementById('ommLightbox')}); });
})();
</script>
