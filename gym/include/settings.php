<?php
/**
 * Dynamic Settings Service & Localization Engine
 * GYM Management System
 */

// Global settings in-memory cache
global $GLOBAL_SETTINGS_CACHE;
$GLOBAL_SETTINGS_CACHE = null;

function load_all_settings($dbh = null) {
    global $GLOBAL_SETTINGS_CACHE;
    if ($GLOBAL_SETTINGS_CACHE !== null) {
        return $GLOBAL_SETTINGS_CACHE;
    }
    if ($dbh === null) {
        global $dbh;
    }
    $GLOBAL_SETTINGS_CACHE = [];
    if (!$dbh) {
        return $GLOBAL_SETTINGS_CACHE;
    }
    try {
        $stmt = $dbh->query("SELECT setting_key, setting_value FROM tbl_settings");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $GLOBAL_SETTINGS_CACHE[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully if table not yet ready
    }
    return $GLOBAL_SETTINGS_CACHE;
}

function get_setting($key, $default = '', $dbh = null) {
    global $GLOBAL_SETTINGS_CACHE;
    if ($GLOBAL_SETTINGS_CACHE === null) {
        load_all_settings($dbh);
    }
    if (isset($GLOBAL_SETTINGS_CACHE[$key]) && $GLOBAL_SETTINGS_CACHE[$key] !== '') {
        return $GLOBAL_SETTINGS_CACHE[$key];
    }
    return $default;
}

function set_setting($key, $value, $group = 'general', $dbh = null) {
    global $GLOBAL_SETTINGS_CACHE;
    if ($dbh === null) {
        global $dbh;
    }
    try {
        $stmt = $dbh->prepare("INSERT INTO tbl_settings (setting_group, setting_key, setting_value) 
            VALUES (:grp, :k, :v) 
            ON DUPLICATE KEY UPDATE setting_value = :v2, setting_group = :grp2");
        $stmt->execute([
            ':grp' => $group,
            ':k' => $key,
            ':v' => $value,
            ':v2' => $value,
            ':grp2' => $group
        ]);
        if ($GLOBAL_SETTINGS_CACHE !== null) {
            $GLOBAL_SETTINGS_CACHE[$key] = $value;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function get_settings_by_group($group, $dbh = null) {
    if ($dbh === null) {
        global $dbh;
    }
    $results = [];
    try {
        $stmt = $dbh->prepare("SELECT setting_key, setting_value FROM tbl_settings WHERE setting_group = ?");
        $stmt->execute([$group]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}
    return $results;
}

function get_currency_symbol($dbh = null) {
    return get_setting('currency_symbol', '₹', $dbh);
}

function format_currency($amount, $dbh = null) {
    $sym = get_currency_symbol($dbh);
    $formatted = number_format(floatval($amount), 2);
    // Remove .00 if whole integer for cleaner look
    if (str_ends_with($formatted, '.00')) {
        $formatted = number_format(floatval($amount), 0);
    }
    return $sym . $formatted;
}

function format_gym_date($date_str, $dbh = null) {
    if (empty($date_str)) return '—';
    $format = get_setting('date_format', 'd M Y', $dbh);
    $ts = strtotime($date_str);
    return $ts ? date($format, $ts) : $date_str;
}

function get_favicon_url($dbh = null) {
    $fav = get_setting('gym_favicon', '', $dbh);
    if (!empty($fav)) {
        if (strpos($fav, 'http://') === 0 || strpos($fav, 'https://') === 0) {
            return $fav;
        }
        return '/' . ltrim($fav, '/');
    }
    return '/favicon.ico';
}

function get_logo_url($dbh = null) {
    $logo = get_setting('gym_logo', '', $dbh);
    if (!empty($logo)) {
        if (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0) {
            return $logo;
        }
        return '/' . ltrim($logo, '/');
    }
    return '';
}

function render_favicon_tags($dbh = null) {
    $fav = get_favicon_url($dbh);
    $ext = strtolower(pathinfo(parse_url($fav, PHP_URL_PATH), PATHINFO_EXTENSION));
    $type = 'image/png';
    if ($ext === 'ico') $type = 'image/x-icon';
    elseif ($ext === 'svg') $type = 'image/svg+xml';
    elseif ($ext === 'jpg' || $ext === 'jpeg') $type = 'image/jpeg';
    
    return '<link rel="icon" type="' . $type . '" href="' . htmlspecialchars($fav, ENT_QUOTES, 'UTF-8') . '">' . "\n\t" .
           '<link rel="shortcut icon" type="' . $type . '" href="' . htmlspecialchars($fav, ENT_QUOTES, 'UTF-8') . '">' . "\n\t" .
           '<link rel="apple-touch-icon" href="' . htmlspecialchars($fav, ENT_QUOTES, 'UTF-8') . '">';
}
