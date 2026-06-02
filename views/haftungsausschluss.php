<?php defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$fullname = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$username = $user->user_login;
$initials = strtoupper( substr( $fullname, 0, 1 ) );
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Haftungsausschluss — ÖMM 2026</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
  min-height: 100vh;
  background: radial-gradient(ellipse at 50% 20%, #1a3a6e 0%, #0d1b3e 50%, #060d1f 100%);
  background-attachment: fixed;
  color: #fff;
  margin: 0; padding: 0;
}

h1,h2,h3,h4,h5 { font-family: 'Oswald', sans-serif; font-weight: 600; }

.gate-wrap {
  max-width: 680px;
  margin: 0 auto;
  padding: 32px 20px 60px;
}

.gate-logo { text-align: center; margin-bottom: 28px; }
.gate-logo img { width: 64px; filter: brightness(0) invert(1); opacity: 0.9; }
.gate-logo .brand {
  font-family: 'Oswald', sans-serif;
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.45);
  text-transform: uppercase;
  letter-spacing: 2px;
  margin-top: 8px;
}

.gate-title {
  font-family: 'Oswald', sans-serif;
  font-size: 26px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 4px;
}
.gate-subtitle {
  font-size: 13px;
  color: rgba(255,255,255,0.35);
  margin-bottom: 24px;
}

.doc-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  margin-bottom: 16px;
  overflow: hidden;
}
.doc-body {
  padding: 22px 24px;
  font-size: 13px;
  color: rgba(255,255,255,0.55);
  line-height: 1.75;
}
.doc-body h4 {
  font-family: 'Oswald', sans-serif;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: rgba(255,255,255,0.7);
  margin: 16px 0 5px;
}
.doc-body h4:first-child { margin-top: 0; }
.doc-body p { margin: 0 0 8px; }

.sig-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  margin-bottom: 16px;
  overflow: hidden;
}
.sig-header {
  padding: 14px 20px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sig-header h3 {
  font-family: 'Oswald', sans-serif;
  font-size: 14px;
  font-weight: 600;
  color: rgba(255,255,255,0.8);
  margin: 0 0 2px;
}
.sig-header p { font-size: 12px; color: rgba(255,255,255,0.3); margin: 0; }

.sig-canvas-wrap {
  position: relative;
  background: rgba(255,255,255,0.96);
}
#sig-canvas {
  display: block;
  width: 100%;
  height: 160px;
  cursor: crosshair;
  touch-action: none;
}
.sig-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  pointer-events: none;
  transition: opacity 0.3s;
}
.sig-placeholder span.emoji { font-size: 48px; line-height: 1; }
.sig-placeholder span.hint { font-size: 14px; color: rgba(0,0,0,0.35); font-style: italic; }
.sig-line {
  position: absolute;
  bottom: 32px; left: 5%; right: 5%;
  height: 1px;
  background: rgba(0,0,0,0.12);
  pointer-events: none;
}
.sig-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-top: 1px solid rgba(255,255,255,0.06);
}
.sig-clear {
  background: none;
  border: 1px solid rgba(255,255,255,0.1);
  color: rgba(255,255,255,0.35);
  font-family: 'Oswald', sans-serif;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}
.sig-clear:hover { border-color: rgba(255,255,255,0.22); color: rgba(255,255,255,0.55); }

.confirm-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid rgba(255,255,255,0.06);
  cursor: pointer;
}
.confirm-box {
  width: 20px; height: 20px;
  border-radius: 6px;
  border: 2px solid rgba(255,255,255,0.18);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  margin-top: 1px;
  transition: all 0.15s;
}
.confirm-box.checked { background: #4ade80; border-color: #4ade80; color: #1a1a2e; }
.confirm-text { font-size: 13px; color: rgba(255,255,255,0.55); line-height: 1.5; }
.confirm-text strong { color: rgba(255,255,255,0.8); }

.submit-btn {
  display: block;
  width: calc(100% - 32px);
  margin: 0 16px 16px;
  padding: 15px;
  background: linear-gradient(135deg, #f0c040, #e8a800);
  border: none;
  border-radius: 12px;
  font-family: 'Oswald', sans-serif;
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #1a1a2e;
  cursor: pointer;
  transition: all 0.15s;
  opacity: 0.35;
  pointer-events: none;
}
.submit-btn.active { opacity: 1; pointer-events: auto; box-shadow: 0 4px 20px rgba(240,192,64,0.25); }
.submit-btn.active:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(240,192,64,0.35); }

.omm-error { color: #f87171; padding: 10px 16px 14px; font-size: 13px; display: none; }

/* SUCCESS OVERLAY */
.success-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: radial-gradient(ellipse at center, #0f3460 0%, #0a0a1a 100%);
  z-index: 9999;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 40px;
}
.success-overlay.visible { display: flex; }
.success-icon { font-size: 64px; margin-bottom: 20px; animation: pop 0.4s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes pop { 0% { transform: scale(0); } 100% { transform: scale(1); } }
.success-title { font-family: 'Oswald', sans-serif; font-size: 28px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.success-sub { font-size: 14px; color: rgba(255,255,255,0.4); margin-bottom: 6px; }
.success-ts {
  font-family: 'Oswald', sans-serif;
  font-size: 13px;
  color: #4ade80;
  margin-bottom: 32px;
  background: rgba(74,222,128,0.1);
  border: 1px solid rgba(74,222,128,0.2);
  padding: 8px 20px;
  border-radius: 20px;
}
.success-btn {
  display: inline-block;
  background: linear-gradient(135deg, #f0c040, #e8a800);
  color: #1a1a2e;
  font-family: 'Oswald', sans-serif;
  font-size: 15px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 14px 36px;
  border-radius: 12px;
  text-decoration: none;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(240,192,64,0.3);
}
</style>
</head>
<body>

<!-- SUCCESS OVERLAY -->
<div class="success-overlay" id="success-overlay">
  <div class="success-icon">✅</div>
  <div class="success-title">Erfolgreich unterzeichnet</div>
  <div class="success-sub">Haftungsausschluss ÖMM 2026</div>
  <div class="success-ts" id="success-ts">—</div>
  <button class="success-btn" id="success-btn">→ Zum Dashboard</button>
</div>

<div class="gate-wrap">

  <div class="gate-logo">
    <img src="https://www.mopedmarathon.at/wp-content/uploads/2023/10/cropped-rocky-512x512-1.png" alt="Rocky">
    <div class="brand">Ötztaler Moped Verein</div>
  </div>

  <div class="gate-title">Haftungsausschluss</div>
  <div class="gate-subtitle">Bitte lies den Text vollständig durch und unterschreibe digital, um fortzufahren.</div>

  <div class="doc-card">
    <div class="doc-body">
      <div style="font-size:64px;text-align:center;line-height:1;margin-bottom:16px;margin-top:-4px;">❤️</div>
      <div style="text-align:center;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.07);">
        <p style="color:rgba(255,255,255,0.85);font-size:15px;line-height:1.7;margin-bottom:12px;font-weight:700;">Vielen Dank für dein Vertrauen und herzlich Willkommen im Verein!</p>
        <p style="color:rgba(255,255,255,0.5);font-size:13px;line-height:1.7;margin-bottom:12px;">Du kannst dir sicher sein, dass wir uns wieder die allergrößte Mühe geben werden, um für dich und die 50ccm Community ein großartiges Treffen auf die Beine zu stellen. Das ist und bleibt unser Qualitätsanspruch!</p>
        <p style="color:rgba(255,255,255,0.5);font-size:13px;line-height:1.7;">Nochmals vielen Dank für dein Vertrauen und beste Grüße aus Sölden,<br><strong style="color:rgba(255,255,255,0.7);">dein Ötztaler Moped Verein</strong></p>
      </div>

      <h4>Allgemeine Geschäftsbedingungen des Ötztaler Moped Vereins</h4>
      <p style="color:rgba(255,255,255,0.3);font-size:11px;margin-bottom:14px;">Letztes Update: Juni 2026</p>

      <p>Der Teilnehmer <strong style="color:rgba(255,255,255,0.85);"><?php echo esc_html($fullname); ?></strong> <span style="color:rgba(255,255,255,0.4);">(@<?php echo esc_html($username); ?>)</span> erklärt seinen Beitritt zum Ötztaler Moped Verein, später „ÖMV" genannt, mit Kauf der Mitgliedschaft, als ordentliches Mitglied ohne Stimmrecht für die Dauer bis Ende September des jeweiligen Jahres.</p>
      <p>Mit der Bezahlung des Mitgliedsbeitrags ist die Teilnahme an Veranstaltungen die durch den Ötztaler Moped Verein organisiert werden, allen voran der Ötztaler Mopedmarathon, später angeführt als „ÖMM" bezeichnet, möglich. Diese Ausflugsfahrt erfolgt nicht gewerblich, ist kein Rennen und dient der Mitgliederwerbung bzw. zur Popularisierung der Mopedliebhaberei.</p>
      <p>Dieser Mitgliedsbeitrag wird für die reibungslose Durchführung des „ÖMM" verwendet, fließt ungekürzt und unmittelbar dem Ötztaler Moped Verein zu, der damit alleiniger Vertragspartner des „Teilnehmers" in den Allgemeinen Geschäftsbedingungen und Haftungsausschluss ist. Weitere Veranstaltungen des ÖMV werden nicht auf Kosten des Vereins organisiert und es werden hierfür gesonderte Beträge als Unkosten für Veranstaltungen an die Teilnehmer verrechnet. Lediglich die offizielle Vereinsausfahrt unter dem Namen ÖMM ist durch den Mitgliedsbeitrag gedeckt.</p>
      <p>Alle Vereinsmitglieder, welche sich an der Organisation des ÖMM beteiligen, handeln als Vertreter des ÖMV und damit nicht in eigenem Namen.</p>

      <h4>Teilnahmebedingungen &amp; Haftungsausschluss</h4>
      <p>Die Teilnahme ist nur bei Volljährigkeit gestattet. Bei Fahrern unter 18 Jahren muss vor dem Start die schriftliche Einwilligung der Eltern an den ÖMV übergeben werden.</p>
      <p>Mir, dem „Teilnehmer", ist bewusst, dass eine derartige Ausflugsfahrt mit gewissen Risiken behaftet ist. Ich bestätige daher ausdrücklich, dass für Verletzungen und Schäden jeglicher Art dem ÖMV keinerlei Schuld zuweisbar ist und ich den ÖMV schad- und klaglos halte. Ich bin im Besitz einer gültigen Haftpflichtversicherung und persönlichen Unfallversicherung.</p>
      <p>Weiters verpflichte ich mich, mich an die Rundfahrt- und Sicherheitsvorschriften des ÖMV zu halten.</p>
      <p style="background:rgba(240,192,64,0.08);border:1px solid rgba(240,192,64,0.25);border-radius:10px;padding:14px 16px;"><strong style="color:#f0c040;font-size:14px;">⚠️ Ich bestätige hiermit ausdrücklich, dass ich bei der verbindlichen Fahrerbesprechung des ÖMV persönlich anwesend sein werde.</strong></p>
      <p>Ich bestätige, dass meine Ausrüstung keine Mängel aufweist. Bei augenscheinlichen Mängeln kann ich jederzeit von der Teilnahme ausgeschlossen werden.</p>
      <p>Es werden auf der Strecke Geschwindigkeits- und Alkoholkontrollen erhoben. Der Teilnehmer erklärt ausdrücklich, weder unter Alkohol- noch Drogeneinfluss zu stehen.</p>

      <h4>Bild- &amp; Tonrechte</h4>
      <p>Der ÖMV oder von ihm autorisierte Dritte sind berechtigt, Audio- und Videoaufzeichnungen sowie Fotos des ÖMM für jegliche Zwecke in allen Medien weltweit zu verwenden. Der ÖMV ist berechtigt, diese Rechte an Dritte zu übertragen.</p>

      <h4>Rückgaberecht &amp; Streitbeilegung</h4>
      <p>Rückgaberecht laut Fernabsatzgesetz: 14 Tage ohne Angabe von Gründen. Gerichtsstand: Innsbruck.</p>
      <p style="color:rgba(255,255,255,0.3);font-size:12px;">Information gem. § 19 Abs 3 AStG: Wir sind weder verpflichtet noch bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>
    </div>
  </div>

  <!-- FAHRERBESPRECHUNG -->
  <div style="background:rgba(240,192,64,0.1);border:2px solid rgba(240,192,64,0.35);border-radius:14px;padding:18px 20px;margin-bottom:16px;text-align:center;">
    <div style="font-size:28px;margin-bottom:8px;">⚠️</div>
    <div style="font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;color:#f0c040;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Verbindliche Fahrerbesprechung</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.65);line-height:1.6;">Mit meiner Unterschrift bestätige ich ausdrücklich,<br>dass ich bei der verbindlichen Fahrerbesprechung des ÖMV<br><strong style="color:rgba(255,255,255,0.85);">persönlich anwesend sein werde.</strong></div>
  </div>

  <!-- UNTERSCHRIFT -->
  <div class="sig-card">
    <div class="sig-header">
      <h3>Digitale Unterschrift</h3>
      <p>Mit dem Finger oder Stift hier unterschreiben</p>
    </div>

    <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.06);display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.25);margin-bottom:6px;">Teilnehmer</div>
        <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,0.85);"><?php echo esc_html($fullname); ?></div>
        <div style="font-size:12px;color:rgba(255,255,255,0.35);margin-top:2px;">@<?php echo esc_html($username); ?></div>
      </div>
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.25);margin-bottom:6px;">Veranstalter</div>
        <div style="font-size:14px;font-weight:600;color:rgba(255,255,255,0.85);">Ötztaler Moped Verein</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.35);margin-top:2px;">ÖMV — Sölden, Tirol</div>
      </div>
    </div>
    <div style="padding:10px 20px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:8px;">
      <span style="font-size:11px;color:rgba(255,255,255,0.25);text-transform:uppercase;letter-spacing:0.6px;">Vereinbarungsdatum:</span>
      <span style="font-family:'Oswald',sans-serif;font-size:13px;color:#f0c040;font-weight:600;" id="omm-live-clock">—</span>
    </div>

    <div class="sig-canvas-wrap">
      <canvas id="sig-canvas"></canvas>
      <div class="sig-placeholder" id="sig-placeholder">
        <span class="emoji">✍️</span>
        <span class="hint">Hier unterschreiben</span>
      </div>
      <div class="sig-line"></div>
    </div>
    <div class="sig-actions">
      <button class="sig-clear" onclick="ommClearSig()">✕ Löschen</button>
      <span style="font-size:11px;color:rgba(255,255,255,0.2);" id="omm-clock-small">—</span>
    </div>

    <div class="confirm-row" onclick="ommToggleConfirm()">
      <div class="confirm-box" id="omm-confirm-box"></div>
      <div class="confirm-text">Diese Vereinbarung wird zwischen mir und dem <strong>Ötztaler Moped Verein</strong> abgeschlossen. Ich habe die AGB vollständig gelesen und erkläre mich einverstanden. Ich nehme <strong>auf eigene Gefahr</strong> teil.</div>
    </div>

    <button class="submit-btn" id="omm-submit-btn" onclick="ommSubmit()">✓ Verbindlich unterschreiben</button>
    <div class="omm-error" id="omm-error"></div>
  </div>

</div>

<script>
(function(){
  var canvas = document.getElementById('sig-canvas');
  var ctx = canvas.getContext('2d');
  var drawing = false, hasSig = false, confirmed = false;

  function resize(){
    var rect = canvas.getBoundingClientRect();
    var dpr = window.devicePixelRatio||1;
    canvas.width = rect.width*dpr; canvas.height = rect.height*dpr;
    ctx.scale(dpr,dpr);
    ctx.strokeStyle = '#1a3a6e'; ctx.lineWidth = 2.8; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
  }
  resize(); window.addEventListener('resize', resize);

  function pos(e){ var r=canvas.getBoundingClientRect(),t=e.touches?e.touches[0]:e; return{x:t.clientX-r.left,y:t.clientY-r.top}; }
  canvas.addEventListener('mousedown',function(e){drawing=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);});
  canvas.addEventListener('mousemove',function(e){if(!drawing)return;var p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();sigDone();});
  canvas.addEventListener('mouseup',function(){drawing=false;});
  canvas.addEventListener('touchstart',function(e){e.preventDefault();drawing=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);},{passive:false});
  canvas.addEventListener('touchmove',function(e){e.preventDefault();if(!drawing)return;var p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();sigDone();},{passive:false});
  canvas.addEventListener('touchend',function(){drawing=false;});

  function sigDone(){hasSig=true;document.getElementById('sig-placeholder').style.opacity='0';checkBtn();}
  window.ommClearSig=function(){var r=canvas.getBoundingClientRect();ctx.clearRect(0,0,r.width,r.height);hasSig=false;document.getElementById('sig-placeholder').style.opacity='1';checkBtn();};
  window.ommToggleConfirm=function(){confirmed=!confirmed;var b=document.getElementById('omm-confirm-box');b.classList.toggle('checked',confirmed);b.textContent=confirmed?'✓':'';checkBtn();};
  function checkBtn(){document.getElementById('omm-submit-btn').classList.toggle('active',hasSig&&confirmed);}

  function clock(){
    var n=new Date(),pad=function(x){return String(x).padStart(2,'0');};
    var ts=pad(n.getDate())+'.'+pad(n.getMonth()+1)+'.'+n.getFullYear()+' — '+pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds())+' Uhr';
    var el=document.getElementById('omm-live-clock'); if(el) el.textContent=ts;
    var el2=document.getElementById('omm-clock-small'); if(el2) el2.textContent=ts;
  }
  clock(); setInterval(clock,1000);

  window.ommSubmit=function(){
    if(!hasSig||!confirmed) return;
    var btn=document.getElementById('omm-submit-btn');
    var err=document.getElementById('omm-error');
    btn.textContent='⏳ Wird gespeichert...'; btn.classList.remove('active');
    var sigPng=canvas.toDataURL('image/png');
    var ts=document.getElementById('omm-live-clock').textContent;
    var restUrl = (typeof oemm_rest !== 'undefined') ? oemm_rest : '<?php echo esc_js( rest_url("oemm-xxvi/v1/sign") ); ?>';
    var nonce = (typeof oemm_nonce !== 'undefined') ? oemm_nonce : '<?php echo esc_js( wp_create_nonce("wp_rest") ); ?>';
    fetch(restUrl,{
      method:'POST',
      headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
      body:JSON.stringify({signature_png:sigPng,fullname:'<?php echo esc_js($fullname); ?>',username:'<?php echo esc_js($username); ?>',signed_ts:ts})
    })
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.success){
        document.getElementById('success-ts').textContent=ts;
        document.getElementById('success-overlay').classList.add('visible');
        var dashUrl = (typeof oemm_dashboard !== 'undefined') ? oemm_dashboard : '<?php echo esc_js( wc_get_account_endpoint_url("omm-dashboard") ); ?>';
        document.getElementById('success-btn').onclick=function(){ window.location.href=d.redirect||dashUrl; };
      } else {
        err.style.display='block'; err.textContent=d.message||'Fehler. Bitte nochmal versuchen.';
        btn.textContent='✓ Verbindlich unterschreiben'; btn.classList.add('active');
      }
    })
    .catch(function(){
      err.style.display='block'; err.textContent='Verbindungsfehler.';
      btn.textContent='✓ Verbindlich unterschreiben'; btn.classList.add('active');
    });
  };
})();
</script>
