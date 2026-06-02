<?php
/**
 * OeMM Diagnose - NACH BENUTZUNG SOFORT LOESCHEN!
 * Passwort: oemm2026
 */
if (($_GET['pw'] ?? '') !== 'oemm2026') {
    die('Zugriff verweigert. ?pw=oemm2026 anhaengen.');
}

// WordPress laden
$possible = [
    '/data/web/web17736/home/site/web/wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php',
];
foreach ($possible as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

global $wpdb;
header('Content-Type: text/plain; charset=utf-8');
echo "=== OeMM DIAGNOSE ===\n\n";

// 1. Meta-Keys aller User
echo "=== ALLE USER + OEMM-META ===\n";
$rows = $wpdb->get_results(
    "SELECT u.ID, u.user_login, m.meta_key, LEFT(m.meta_value,200) as val
     FROM {$wpdb->users} u
     JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
     WHERE m.meta_key LIKE '%oemm%' OR m.meta_key LIKE '%omm%' OR m.meta_key LIKE '%startnr%' OR m.meta_key LIKE '%startnum%'
     ORDER BY u.ID, m.meta_key"
);
foreach ($rows as $r) {
    echo "User #{$r->ID} ({$r->user_login}) | {$r->meta_key} = {$r->val}\n";
}
if (empty($rows)) echo "Keine oemm/omm Meta gefunden.\n";

// 2. Relevante Tabellen
echo "\n=== TABELLEN ===\n";
$all = $wpdb->get_col("SHOW TABLES");
$rel = array_filter($all, fn($t) => preg_match('/(startl|omm|oemm|freunde|marathon)/i', $t));
if (empty($rel)) {
    echo "Keine omm/oemm Tabellen.\nAlle:\n";
    foreach ($all as $t) echo "  $t\n";
} else {
    foreach ($rel as $table) {
        echo "\n--- $table ---\n";
        $cols = $wpdb->get_results("DESCRIBE `$table`");
        foreach ($cols as $c) echo "  {$c->Field} ({$c->Type})\n";
        $data = $wpdb->get_results("SELECT * FROM `$table` LIMIT 3", ARRAY_A);
        foreach ($data as $row) {
            foreach ($row as $k => $v) echo "  $k = " . substr((string)$v, 0, 100) . "\n";
            echo "  ---\n";
        }
    }
}

// 3. Options
echo "\n=== OPTIONS ===\n";
$opts = $wpdb->get_results(
    "SELECT option_name, LEFT(option_value,200) as v FROM {$wpdb->options}
     WHERE (option_name LIKE '%oemm%' OR option_name LIKE '%omm_%' OR option_name LIKE '%startlist%')
     AND option_name NOT LIKE '%transient%' LIMIT 30"
);
foreach ($opts as $o) echo "  {$o->option_name} = {$o->v}\n";
if (empty($opts)) echo "  Keine gefunden.\n";

echo "\n=== ENDE - BITTE DATEI LOESCHEN! ===\n";
