<?php
/**
 * View: Packliste — ÖMM Backend XXVI v2.1.4
 */
defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user();
?>
<style>
/* STICKY PROGRESS — JS-fixed für WP-Theme-Kompatibilität */
.omm-progress-sticky{
  background:#0d1b3e;
  border-bottom:2px solid rgba(74,222,128,.25);
  padding:10px 0 8px;
  margin-bottom:16px;
}
/* Sticky via JS — klebt in der Content-Spalte */
.omm-progress-sticky.is-stuck{
  position:fixed;
  top:0;
  z-index:9000;
  background:#0d1b3e;
  border-bottom:2px solid rgba(74,222,128,.25);
  padding:10px 0 8px;
  box-shadow:0 2px 8px rgba(0,0,0,.3);
  /* Breite & Position wird per JS gesetzt */
}
.omm-progress-spacer{ display:none; height:0; }
.omm-progress-spacer.active{ display:block; }
.omm-progress-inner{display:flex;align-items:center;gap:14px}
.omm-progress-track{flex:1;height:10px;background:rgba(255,255,255,.08);border-radius:5px;overflow:hidden;position:relative}
.omm-progress-fill{height:100%;background:linear-gradient(90deg,#4ade80,#22c55e);border-radius:5px;transition:width .4s ease;width:0%}
.omm-progress-pct{font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;color:#4ade80;min-width:42px;text-align:right;flex-shrink:0}
.omm-milestone-dots{display:flex;gap:0;position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
.omm-milestone-dot{position:absolute;top:50%;transform:translateY(-50%);width:3px;height:14px;background:rgba(255,255,255,.2);border-radius:2px;margin-top:0}
.omm-milestone-dot.reached{background:#f0c040}

/* GROUPS */
.check-group{margin-bottom:6px}
.check-group-title{font-family:'Oswald',sans-serif;font-size:18px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#f0c040;padding:12px 0 8px;border-bottom:2px solid rgba(240,192,64,.2);margin-bottom:2px;text-shadow:0 0 18px rgba(240,192,64,.2)}
.omm-checklist{padding:0 0 20px}
.check-item{display:flex;align-items:center;gap:12px;padding:11px 4px;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;user-select:none;transition:background .1s;border-radius:6px}
.check-item:last-child{border-bottom:none}
.check-item:hover{background:rgba(255,255,255,.03)}
.check-box{width:22px;height:22px;border-radius:6px;border:2px solid rgba(255,255,255,.2);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s;font-size:13px;font-weight:700;color:#1a1a2e}
.check-item.checked .check-box{background:#4ade80;border-color:#4ade80}
.check-item.checked .check-text{text-decoration:line-through;color:rgba(255,255,255,.3)}
.check-text{font-size:14px;color:rgba(255,255,255,.8);flex:1;line-height:1.4}
.check-link{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:26px;height:26px;border-radius:6px;overflow:hidden;transition:transform .15s,box-shadow .15s;text-decoration:none}
.check-link:hover{transform:scale(1.15);box-shadow:0 2px 8px rgba(0,0,0,.4)}
.check-link img{width:22px;height:22px;display:block;border-radius:4px}
.badge-pflicht{font-size:10px;font-family:'Oswald',sans-serif;font-weight:600;text-transform:uppercase;color:#f87171;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);padding:2px 7px;border-radius:20px;flex-shrink:0;white-space:nowrap}
.badge-empfohlen{font-size:10px;font-family:'Oswald',sans-serif;font-weight:600;text-transform:uppercase;color:#fbbf24;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);padding:2px 7px;border-radius:20px;flex-shrink:0;white-space:nowrap}
.badge-nice{font-size:10px;font-family:'Oswald',sans-serif;font-weight:600;text-transform:uppercase;color:#4ade80;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.2);padding:2px 7px;border-radius:20px;flex-shrink:0;white-space:nowrap}

/* FIREWORKS */
#omm-fw-canvas{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999;display:none}
.omm-milestone-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);background:linear-gradient(135deg,#f0c040,#f59e0b);color:#1a1a2e;font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;padding:20px 36px;border-radius:18px;z-index:100000;text-align:center;box-shadow:0 20px 60px rgba(240,192,64,.5);transition:transform .3s cubic-bezier(.34,1.56,.64,1);pointer-events:none;max-width:90vw}
.omm-milestone-toast.show{transform:translate(-50%,-50%) scale(1)}
.omm-milestone-toast .toast-sub{font-size:13px;font-family:'Inter',sans-serif;font-weight:500;margin-top:6px;opacity:.8}
</style>

<canvas id="omm-fw-canvas"></canvas>
<div class="omm-milestone-toast" id="omm-milestone-toast">
  <div id="omm-toast-text">🎉 10 erledigt!</div>
  <div class="toast-sub" id="omm-toast-sub">Weiter so! 🏍️🔥</div>
</div>

<!-- STICKY PROGRESS BAR -->
<div class="omm-progress-sticky" id="omm-progress-bar">
  <div class="omm-progress-inner">
    <div class="omm-progress-track" id="omm-progress-track">
      <div class="omm-progress-fill" id="omm-progress-fill"></div>
    </div>
    <div class="omm-progress-pct" id="omm-pct">0%</div>
  </div>
</div>
<div class="omm-progress-spacer" id="omm-progress-spacer"></div>

<div class="omm-checklist">

<?php

define('OEMM_YT_LOGO', 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20fill%3D%22%23FF0000%22%20d%3D%22M23.495%206.205a3.007%203.007%200%200%200-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007%203.007%200%200%200%20.527%206.205a31.247%2031.247%200%200%200-.522%205.805%2031.247%2031.247%200%200%200%20.522%205.783%203.007%203.007%200%200%200%202.088%202.088c1.868.502%209.396.502%209.396.502s7.506%200%209.396-.502a3.007%203.007%200%200%200%202.088-2.088%2031.247%2031.247%200%200%200%20.5-5.783%2031.247%2031.247%200%200%200-.5-5.805zM9.609%2015.601V8.408l6.264%203.602z%22/%3E%3C/svg%3E');
define('OEMM_IG_LOGO', 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cdefs%3E%3CradialGradient%20id%3D%22ig%22%20cx%3D%2230%25%22%20cy%3D%22107%25%22%20r%3D%22150%25%22%3E%3Cstop%20offset%3D%220%25%22%20stop-color%3D%22%23fdf497%22/%3E%3Cstop%20offset%3D%225%25%22%20stop-color%3D%22%23fdf497%22/%3E%3Cstop%20offset%3D%2245%25%22%20stop-color%3D%22%23fd5949%22/%3E%3Cstop%20offset%3D%2260%25%22%20stop-color%3D%22%23d6249f%22/%3E%3Cstop%20offset%3D%2290%25%22%20stop-color%3D%22%23285AEB%22/%3E%3C/radialGradient%3E%3C/defs%3E%3Cpath%20fill%3D%22url%28%23ig%29%22%20d%3D%22M12%202.163c3.204%200%203.584.012%204.85.07%203.252.148%204.771%201.691%204.919%204.919.058%201.265.069%201.645.069%204.849%200%203.205-.012%203.584-.069%204.849-.149%203.225-1.664%204.771-4.919%204.919-1.266.058-1.644.07-4.85.07-3.204%200-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849%200-3.204.013-3.583.07-4.849.149-3.227%201.664-4.771%204.919-4.919%201.266-.057%201.645-.069%204.849-.069zm0-2.163c-3.259%200-3.667.014-4.947.072-4.358.2-6.78%202.618-6.98%206.98-.059%201.281-.073%201.689-.073%204.948%200%203.259.014%203.668.072%204.948.2%204.358%202.618%206.78%206.98%206.98%201.281.058%201.689.072%204.948.072%203.259%200%203.668-.014%204.948-.072%204.354-.2%206.782-2.618%206.979-6.98.059-1.28.073-1.689.073-4.948%200-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0%205.838c-3.403%200-6.162%202.759-6.162%206.162s2.759%206.163%206.162%206.163%206.162-2.759%206.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0%2010.162c-2.209%200-4-1.79-4-4%200-2.209%201.791-4%204-4s4%201.791%204%204c0%202.21-1.791%204-4%204zm6.406-11.845c-.796%200-1.441.645-1.441%201.44s.645%201.44%201.441%201.44c.795%200%201.439-.645%201.439-1.44s-.644-1.44-1.439-1.44z%22/%3E%3C/svg%3E');
define('OEMM_FB_LOGO', 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20fill%3D%22%231877F2%22%20d%3D%22M24%2012.073c0-6.627-5.373-12-12-12s-12%205.373-12%2012c0%205.99%204.388%2010.954%2010.125%2011.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007%201.792-4.669%204.533-4.669%201.312%200%202.686.235%202.686.235v2.953H15.83c-1.491%200-1.956.925-1.956%201.874v2.25h3.328l-.532%203.47h-2.796v8.385C19.612%2023.027%2024%2018.062%2024%2012.073z%22/%3E%3C/svg%3E');

function omm_cl_group($title) {
    echo '<div class="check-group"><div class="check-group-title">' . $title . '</div>' . "\n";
}
function omm_cl_item($text, $badge = 'pflicht', $link = null, $logo_const = null) {
    $link_html = '';
    if ($link && $logo_const) {
        $src = constant($logo_const);
        $link_html = ' <a class="check-link" href="' . esc_url($link) . '" target="_blank" onclick="event.stopPropagation()" title="Öffnen"><img src="' . esc_attr($src) . '" alt=""></a>';
    } elseif ($link) {
        $link_html = ' <a class="check-link" href="' . esc_url($link) . '" target="_blank" onclick="event.stopPropagation()" title="Öffnen" style="font-size:16px;width:auto;background:none">🔗</a>';
    }
    $badge_label = $badge === 'nice' ? 'NICE TO HAVE' : strtoupper($badge);
    echo '<div class="check-item" onclick="ommToggleCheck(this)">'
       . '<div class="check-box"></div>'
       . '<span class="check-text">' . esc_html($text) . '</span>'
       . $link_html
       . '<span class="badge-' . $badge . '">' . $badge_label . '</span>'
       . '</div>' . "\n";
}
function omm_cl_end_group() { echo '</div>' . "\n"; }

/* ===== EVENT-VORBEREITUNG ===== */
omm_cl_group('📋 Event-Vorbereitung');
omm_cl_item('Checkin Unterlagen dabei', 'empfohlen');
omm_cl_item('Haftungsausschluss unterschrieben', 'empfohlen');
omm_cl_item('Auto geparkt und in der Unterkunft eingecheckt', 'empfohlen');
omm_cl_item('Startpaket abgeholt', 'empfohlen');
omm_cl_item('Roadbook durchgelesen');
omm_cl_item('Foto in der Fotobox gemacht', 'empfohlen');
omm_cl_item('Benzingespräch am Eventgelände geführt', 'empfohlen');
omm_cl_end_group();

/* ===== FAHRZEUG ===== */
omm_cl_group('🏍️ Fahrzeug');
omm_cl_item('Moped gecheckt: Öl, Kette, Reifen, Bremsen');
omm_cl_item('Tank voll');
omm_cl_item('Beleuchtung funktioniert');
omm_cl_item('Zulassung + Versicherung dabei');
omm_cl_item('Werkzeugset + Flickzeug');
omm_cl_item('Ersatzzündkerze (mit Zündkerzenschlüssel)');
omm_cl_end_group();

/* ===== FAHRER ===== */
omm_cl_group('👤 Fahrer');
omm_cl_item('Helm mit Prüfzeichen (richtig sitzend, Visier sauber)');
omm_cl_item('Handschuhe');
omm_cl_item('Schuhe (Knöchelhoch!)');
omm_cl_item('Ausweis / Führerschein');
omm_cl_item('Personalausweis');
omm_cl_item('Bargeld (cash is beautiful!)');
omm_cl_end_group();

/* ===== KOMMUNIKATION ===== */
omm_cl_group('📡 Kommunikation');
omm_cl_item('Notfallkontakt informiert');
omm_cl_item('Alle Mitteilnehmer haben die Handynummern aller anderen ausgetauscht');
omm_cl_item('WhatsApp Gruppe der Gang ist aktiv');
omm_cl_item('Treffpunkte und Uhrzeiten klar kommuniziert');
omm_cl_item('Besenwagen App regelmäßig öffnen (trackt nicht aktiv!)');
omm_cl_item('Einen wildfremden Menschen angesprochen und gefragt, welches Moped er hat', 'empfohlen');
omm_cl_end_group();

/* ===== AUSRÜSTUNG ===== */
omm_cl_group('🎒 Ausrüstung');
omm_cl_item('Erste-Hilfe-Set');
omm_cl_item('Regenjacke (Alpen = Regen kommt immer)', 'empfohlen');
omm_cl_item('Wechsel-T-Shirt', 'empfohlen');
omm_cl_item('Powerbank', 'empfohlen');
omm_cl_item('Sonnencreme (auf 2.500m brennts!)', 'empfohlen');
omm_cl_item('Karte/GPX-Route offline gespeichert', 'empfohlen');
omm_cl_end_group();

/* ===== LOGISTIK ===== */
omm_cl_group('🗂️ Logistik');
omm_cl_item('Hotel/Unterkunft reserviert (Reservierungsbestätigungen dabei?!)', 'empfohlen');
omm_cl_item('Spanngurte geprüft', 'empfohlen');
omm_cl_item('2-Takt Öl', 'empfohlen');
omm_cl_item('Hunger mitbringen — Es wird super gekocht!', 'empfohlen');
omm_cl_item('Gute Laune nach dem Renntag', 'empfohlen');
omm_cl_item('Tagesfotos/Videos auf Handy — zum Angeben am Stammtisch', 'empfohlen');
omm_cl_end_group();

/* ===== SOCIAL MEDIA ===== */
omm_cl_group('📱 Social Media Connections');
omm_cl_item('ÖMM Freundebuch für die Videowall ausgefüllt', 'empfohlen');
omm_cl_item('ÖMM-YouTube Kanal abonnieren', 'empfohlen', 'https://www.youtube.com/@%C3%96tztalerMopedVerein?sub_confirmation=1', 'OEMM_YT_LOGO');
omm_cl_item('ÖMM-Instagram abonnieren', 'empfohlen', 'https://www.instagram.com/mopedmarathon/', 'OEMM_IG_LOGO');
omm_cl_item('ÖMM-Facebook Seite abonnieren', 'empfohlen', 'https://www.facebook.com/mopedmarathon/', 'OEMM_FB_LOGO');
omm_cl_item('The Silent and the Smoker — YouTube abonnieren', 'empfohlen', 'https://www.youtube.com/@thesilentandthesmoker?sub_confirmation=1', 'OEMM_YT_LOGO');
omm_cl_item('The Silent and the Smoker — Instagram abonnieren', 'empfohlen', 'https://www.instagram.com/thesilentandthesmoker/', 'OEMM_IG_LOGO');
omm_cl_item('The Silent and the Smoker — Facebook abonnieren', 'empfohlen', 'https://www.facebook.com/thesilentandthesmoker/', 'OEMM_FB_LOGO');
omm_cl_end_group();

/* ===== NICE TO HAVE ===== */
omm_cl_group('🎉 Stimmung');
omm_cl_item('Trinkfest (Sölsch Bier und Wein erst wieder am Eventgelände!) Don\'t drink and drive!', 'nice');
omm_cl_item('Kaffee vor der Abfahrt', 'nice');
omm_cl_item('Fotos der ganzen Gruppe', 'nice');
omm_cl_item('Moped-Storys zum Erzählen', 'nice');
omm_cl_end_group();

omm_cl_group('🏆 Extras');
omm_cl_item('Action-Cam montiert und Akkus geladen', 'nice');
omm_cl_item('Drohne (Serpentinenaufnahmen von oben — unbezahlbar)', 'nice');
omm_cl_item('Tageskilometer-Wette', 'nice');
omm_cl_item('Fotos der Passhöhen 🏔', 'nice');
omm_cl_item('Ersatzzündkerzen für alle (der eine Freund weiß wer gemeint ist)', 'nice');
omm_cl_item('Trophäenfoto vor jedem Passschild', 'nice');
omm_cl_end_group();
?>

</div><!-- .omm-checklist -->

<script>
(function(){
var STORE_KEY = 'omm_packliste_2026_<?php echo (int)$user->ID; ?>';
var prevChecked = 0;
var totalItems  = 0;

function ommToggleCheck(item) {
    item.classList.toggle('checked');
    item.querySelector('.check-box').textContent = item.classList.contains('checked') ? '✓' : '';
    ommSaveState();
    ommUpdateProgress();
}
window.ommToggleCheck = ommToggleCheck;

function ommBuildMilestoneDots(total) {
    var track = document.getElementById('omm-progress-track');
    if (!track || total === 0) return;
    // Remove old dots
    track.querySelectorAll('.omm-milestone-dot').forEach(function(d){ d.remove(); });
    var milestones = [];
    for (var m = 10; m < total; m += 10) milestones.push(m);
    milestones.forEach(function(m) {
        var dot = document.createElement('div');
        dot.className = 'omm-milestone-dot';
        dot.style.left = (m / total * 100) + '%';
        dot.dataset.milestone = m;
        track.appendChild(dot);
    });
}

function ommUpdateDots(done) {
    document.querySelectorAll('.omm-milestone-dot').forEach(function(d) {
        var m = parseInt(d.dataset.milestone);
        d.classList.toggle('reached', done >= m);
    });
}

function ommUpdateProgress() {
    var all  = document.querySelectorAll('.check-item');
    var done = document.querySelectorAll('.check-item.checked').length;
    totalItems = all.length;
    var pct  = totalItems ? Math.round(done / totalItems * 100) : 0;
    var ptEl = document.getElementById('omm-pct');
    var pfEl = document.getElementById('omm-progress-fill');
    if (ptEl) { ptEl.textContent = pct + '%'; ptEl.style.color = pct === 100 ? '#4ade80' : ''; }
    if (pfEl) pfEl.style.width = pct + '%';
    ommUpdateDots(done);
    if (done > 0 && done > prevChecked) {
        if (done === totalItems) { ommFireworks(done, totalItems); }
        else if (done % 10 === 0) { ommFireworks(done, totalItems); }
    }
    prevChecked = done;
}

function ommSaveState() {
    var items = document.querySelectorAll('.check-item');
    var state = Array.from(items).map(function(i){ return i.classList.contains('checked') ? 1 : 0; });
    try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch(e){}
}

function ommLoadState() {
    try {
        var saved = localStorage.getItem(STORE_KEY);
        if (!saved) return;
        var state = JSON.parse(saved);
        document.querySelectorAll('.check-item').forEach(function(item, i) {
            if (state[i]) { item.classList.add('checked'); item.querySelector('.check-box').textContent = '✓'; }
        });
        prevChecked = state.filter(function(x){ return x; }).length;
    } catch(e) {}
}

function ommFireworks(count, total) {
    var isAll = (count === total);
    var msgs = {
        10: { t: '🎉 10 ERLEDIGT!',                                        s: 'Super Start — weiter so! 🏍️' },
        20: { t: '🔥 YOU ARE ON FIRE!!!',                                   s: '20 Punkte abgehakt — unaufhaltbar!' },
        30: { t: '💪 YEAH!!! 30 PUNKTE!',                                  s: 'Du bist bald komplett ausgerüstet!' },
        40: { t: '🏆 DU WIRST NOCH EINE ÖMM LEGENDE WERDEN!',              s: '40 Punkte — absolute Klasse!' },
        50: { t: '🚀 WEITER SO — DU BIST NICHT MEHR WEIT VOM HERO!',       s: 'Noch ein paar Punkte bis zur Legende! 🏍️🔥' }
    };
    var msg = isAll
        ? { t: '🎯 YEAH!!! DU BIST EINE PERFEKT VORBEREITETE LEGENDE!', s: 'ROCK ON!!! 🏍️🔥🏆' }
        : (msgs[count] || { t: '🎉 ' + count + ' ERLEDIGT!', s: 'Weiter so! 🏍️' });

    var toast = document.getElementById('omm-milestone-toast');
    var txtEl = document.getElementById('omm-toast-text');
    var subEl = document.getElementById('omm-toast-sub');
    if (txtEl) txtEl.textContent = msg.t;
    if (subEl) subEl.textContent = msg.s;
    if (toast) { toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 3200); }

    var canvas = document.getElementById('omm-fw-canvas');
    if (!canvas) return;
    canvas.width = window.innerWidth; canvas.height = window.innerHeight;
    canvas.style.display = 'block';
    var ctx = canvas.getContext('2d');
    var particles = [];
    var colors = ['#f0c040','#f87171','#4ade80','#60a5fa','#a78bfa','#fff','#fb923c','#fbbf24'];
    var numR = isAll ? 10 : 7;
    for (var r = 0; r < numR; r++) {
        (function(ri) {
            setTimeout(function() {
                var ex = window.innerWidth * (0.05 + ri * (0.9 / numR));
                var ey = window.innerHeight * (0.1 + Math.random() * 0.4);
                var col = colors[Math.floor(Math.random() * colors.length)];
                var num = isAll ? 70 : 55;
                for (var p = 0; p < num; p++) {
                    var a = (Math.PI*2/num)*p + Math.random()*.3, sp = 2 + Math.random()*(isAll?7:5);
                    particles.push({x:ex,y:ey,vx:Math.cos(a)*sp,vy:Math.sin(a)*sp,alpha:1,color:col,size:2.5+Math.random()*3.5});
                }
            }, ri * 180);
        })(r);
    }
    var startTs = null;
    function anim(ts) {
        if (!startTs) startTs = ts;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        for (var i = particles.length-1; i >= 0; i--) {
            var p = particles[i];
            p.x+=p.vx; p.y+=p.vy; p.vy+=0.13; p.alpha-=0.016;
            if (p.alpha > 0) { ctx.save(); ctx.globalAlpha=p.alpha; ctx.fillStyle=p.color; ctx.beginPath(); ctx.arc(p.x,p.y,p.size,0,Math.PI*2); ctx.fill(); ctx.restore(); }
            else particles.splice(i,1);
        }
        if (ts-startTs < (isAll?5000:3500) || particles.length > 0) requestAnimationFrame(anim);
        else { canvas.style.display='none'; ctx.clearRect(0,0,canvas.width,canvas.height); }
    }
    requestAnimationFrame(anim);
    window.addEventListener('resize', function(){ canvas.width=window.innerWidth; canvas.height=window.innerHeight; }, {once:true});
}

// Sticky Progress: klebt in der Content-Spalte via position:fixed mit exakter Breite/Position
(function() {
    var bar    = document.getElementById('omm-progress-bar');
    var spacer = document.getElementById('omm-progress-spacer');
    if (!bar) return;

    var sentinel = document.createElement('div');
    sentinel.style.cssText = 'height:1px;margin:0;padding:0;pointer-events:none';
    bar.parentNode.insertBefore(sentinel, bar);

    function applyStuck() {
        var parent = bar.parentElement;
        var rect   = parent.getBoundingClientRect();
        bar.style.left  = rect.left + 'px';
        bar.style.width = rect.width + 'px';
        bar.classList.add('is-stuck');
        spacer.style.height = bar.offsetHeight + 'px';
        spacer.classList.add('active');
    }
    function removeStuck() {
        bar.classList.remove('is-stuck');
        bar.style.left  = '';
        bar.style.width = '';
        spacer.classList.remove('active');
    }

    var obs = new IntersectionObserver(function(entries) {
        if (!entries[0].isIntersecting) { applyStuck(); }
        else { removeStuck(); }
    }, { threshold: 0, rootMargin: '0px 0px 0px 0px' });
    obs.observe(sentinel);

    window.addEventListener('resize', function() {
        if (bar.classList.contains('is-stuck')) applyStuck();
    });
})();

// Init
ommLoadState();
var allItems = document.querySelectorAll('.check-item');
totalItems = allItems.length;
ommBuildMilestoneDots(totalItems);
ommUpdateProgress();
})();
</script>
