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

/**
 * Detects the active Base URL for the Gym Station QR Code.
 * Checks saved setting, active Cloudflare Tunnel, or HTTP host.
 */
function get_gym_base_url($dbh = null) {
    // 1. Explicit custom setting in DB
    $custom = get_setting('gym_checkin_base_url', '', $dbh);
    if (!empty($custom)) {
        return rtrim($custom, '/');
    }
    // 2. Active Cloudflare Tunnel log
    if (file_exists('/tmp/cloudflared.log')) {
        $log = @file_get_contents('/tmp/cloudflared.log');
        if (!empty($log) && preg_match_all('/https:\/\/[a-z0-9\-]+\.trycloudflare\.com/', $log, $matches)) {
            $last = end($matches[0]);
            if (!empty($last)) return rtrim($last, '/');
        }
    }
    // 3. Current HTTP host / forwarded proto
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return $scheme . $host;
}

/**
 * Returns or generates a secure Gym Check-In Token.
 */
function get_gym_checkin_token($dbh = null) {
    $token = get_setting('gym_checkin_token', '', $dbh);
    if (empty($token)) {
        $token = 'fg_' . substr(bin2hex(random_bytes(8)), 0, 12);
        set_setting('gym_checkin_token', $token, 'attendance', $dbh);
    }
    return $token;
}

/**
 * Returns the full scannable Check-In URL for the Gym QR Code.
 */
function get_gym_checkin_url($dbh = null) {
    $base = get_gym_base_url($dbh);
    $token = get_gym_checkin_token($dbh);
    $gymId = function_exists('get_current_gym_id') ? get_current_gym_id() : 1;
    $requireToken = get_setting('gym_checkin_require_token', '1', $dbh);
    
    if ($requireToken === '0' || $requireToken === 'no') {
        return $base . '/checkin.php?gym=' . $gymId;
    }
    return $base . '/checkin.php?gym=' . $gymId . '&token=' . urlencode($token);
}

/**
 * Calculates a member's consecutive check-in streak in days.
 */
function get_member_checkin_streak($userId, $dbh) {
    if (!$dbh || empty($userId)) return 0;
    try {
        $stmt = $dbh->prepare("
            SELECT DISTINCT DATE(check_in_time) as visit_date 
            FROM tbl_attendance 
            WHERE user_id = :uid 
            ORDER BY visit_date DESC 
            LIMIT 30
        ");
        $stmt->execute([':uid' => intval($userId)]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($dates)) return 0;

        $streak = 0;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Active streak requires attendance either today or yesterday
        $firstDate = $dates[0];
        if ($firstDate !== $today && $firstDate !== $yesterday) {
            return 0;
        }
        
        $expected = $firstDate;
        foreach ($dates as $d) {
            if ($d === $expected) {
                $streak++;
                $expected = date('Y-m-d', strtotime($expected . ' -1 day'));
            } else {
                break;
            }
        }
        return $streak;
    } catch (Exception $e) {
        return 0;
    }
}
