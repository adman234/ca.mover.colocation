<?php
/* -----------------------------------------------------------------------
 * ca.mover.colocation — Settings Save Handler
 * Called via AJAX from Colocation.page
 * ----------------------------------------------------------------------- */

$plugin      = 'ca.mover.colocation';
$cfg_dir     = "/boot/config/plugins/{$plugin}";
$cfg_file    = "{$cfg_dir}/{$plugin}.cfg";
$rules_file  = "{$cfg_dir}/colocation_rules.cfg";
$script_path = "/usr/local/emhttp/plugins/{$plugin}/colocation_prep";
$mover_cfg   = '/boot/config/plugins/ca.mover.tuning/ca.mover.tuning.cfg';

// Ensure config directory exists on the boot drive
if (!is_dir($cfg_dir)) {
    mkdir($cfg_dir, 0755, true);
}

$action = $_POST['action'] ?? '';

/* -----------------------------------------------------------------------
 * Action: set or clear the before-script in CA Mover Tuning
 * ----------------------------------------------------------------------- */
if ($action === 'set_before_script') {
    if (!file_exists($mover_cfg)) {
        echo 'ERROR: CA Mover Tuning config not found.';
        exit;
    }
    $content = file_get_contents($mover_cfg);
    // Only set if currently empty
    if (!preg_match('/beforeScript="([^"]+)"/', $content, $m) || empty($m[1])) {
        $content = preg_replace('/beforeScript="[^"]*"/', "beforeScript=\"{$script_path}\"", $content);
        file_put_contents($mover_cfg, $content);
        echo 'OK';
    } else {
        echo 'ERROR: CA Mover Tuning already has a Before Script set: ' . htmlspecialchars($m[1]);
    }
    exit;
}

if ($action === 'clear_before_script') {
    if (!file_exists($mover_cfg)) {
        echo 'ERROR: CA Mover Tuning config not found.';
        exit;
    }
    $content = file_get_contents($mover_cfg);
    $content = preg_replace('/beforeScript="[^"]*"/', 'beforeScript=""', $content);
    file_put_contents($mover_cfg, $content);
    echo 'OK';
    exit;
}

/* -----------------------------------------------------------------------
 * Action: save main settings + rules
 * ----------------------------------------------------------------------- */
$enabled     = (($_POST['colocateEnabled'] ?? 'no') === 'yes') ? 'yes' : 'no';
$log_enabled = (($_POST['logEnabled']      ?? 'yes') === 'yes') ? 'yes' : 'no';

// Write main config
$cfg_content  = "colocateEnabled=\"{$enabled}\"\n";
$cfg_content .= "logEnabled=\"{$log_enabled}\"\n";
file_put_contents($cfg_file, $cfg_content);

// Write rules file (normalize line endings)
$rules = $_POST['rules'] ?? '';
$rules = str_replace(["\r\n", "\r"], "\n", $rules);
file_put_contents($rules_file, $rules);

// Auto-update the before-script in CA Mover Tuning when the state changes
if (file_exists($mover_cfg)) {
    $mt_content = file_get_contents($mover_cfg);
    preg_match('/beforeScript="([^"]*)"/', $mt_content, $m);
    $current_before = $m[1] ?? '';

    if ($enabled === 'yes' && $current_before !== $script_path) {
        // Only auto-set if the field is currently empty
        if (empty($current_before)) {
            $mt_content = preg_replace('/beforeScript="[^"]*"/', "beforeScript=\"{$script_path}\"", $mt_content);
            file_put_contents($mover_cfg, $mt_content);
        }
        // If already set to something else, leave it — the UI shows a conflict warning
    } elseif ($enabled === 'no' && $current_before === $script_path) {
        // Remove our script when the plugin is disabled
        $mt_content = preg_replace('/beforeScript="[^"]*"/', 'beforeScript=""', $mt_content);
        file_put_contents($mover_cfg, $mt_content);
    }
}

echo 'OK';
