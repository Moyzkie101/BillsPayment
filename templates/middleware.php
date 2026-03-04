<?php
// Middleware helpers for access-level -> permissions checks
// Prevent double inclusion which can cause "Cannot redeclare" fatal errors
if (defined('__BP_MIDDLEWARE_LOADED__')) {
    return;
}
define('__BP_MIDDLEWARE_LOADED__', true);
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ensure DB connection is available
if (!isset($conn)) {
    $possible = __DIR__ . '/../config/config.php';
    if (file_exists($possible)) {
        include_once $possible;
    }
}

if (!function_exists('load_access_map')) {
function load_access_map()
{
    static $map = null;
    static $_last_debug = null;
    if ($map !== null) return $map;

    $path = __DIR__ . '/../assets/js/accesslevel-map.json';
    if (!file_exists($path)) {
        $map = [];
        return $map;
    }

    $raw = @file_get_contents($path);
    $arr = json_decode($raw, true);
    // If initial decode failed, try to clean common non-JSON artifacts (C-style comments, // comments, trailing commas)
    if (json_last_error() !== JSON_ERROR_NONE) {
        $clean = $raw;
        // remove /* ... */ comments
        $clean = preg_replace('!/\*.*?\*/!s', '', $clean);
        // remove // comments
        $clean = preg_replace('/\/\/.*(?=[\r\n])/', '', $clean);
        // remove trailing commas before ] or }
        $clean = preg_replace('/,\s*([\]}])/', '$1', $clean);
        $arr = json_decode($clean, true);
        if (isset($_GET['debug_access']) && $_GET['debug_access'] && function_exists('error_log')) {
            error_log('[debug_access] load_access_map: attempted_clean json_err_after_clean=' . json_last_error_msg());
        }
    }
    if (isset($_GET['debug_access']) && $_GET['debug_access']) {
        if (function_exists('error_log')) {
            error_log('[debug_access] load_access_map: file_exists=' . (file_exists($path) ? '1' : '0') . ' raw_len=' . strlen($raw) . ' json_err=' . json_last_error_msg());
            error_log('[debug_access] load_access_map: decoded_type=' . gettype($arr) . ' is_array=' . (is_array($arr) ? '1' : '0') . ' count=' . (is_array($arr) ? count($arr) : 0));
            if (is_array($arr) && count($arr) > 0) {
                $first = $arr[0];
                if (is_array($first)) {
                    error_log('[debug_access] load_access_map: first_item_keys=' . json_encode(array_keys($first)));
                } else {
                    error_log('[debug_access] load_access_map: first_item_rep=' . substr(var_export($first, true), 0, 200));
                }
            }
        }
    }
    $out = [];
    if (is_array($arr)) {
        $levels = [];
        if (isset($arr['access_levels']) && is_array($arr['access_levels'])) {
            $levels = $arr['access_levels'];
        } elseif (array_keys($arr) === range(0, count($arr) - 1)) {
            $levels = $arr;
        }

        foreach ($levels as $item) {
            $lvl = isset($item['access_level']) ? intval($item['access_level']) : 0;
            if ($lvl) {
                $out[$lvl] = isset($item['permissions']) && is_array($item['permissions']) ? $item['permissions'] : [];
            }
        }
    }

    // Fallback: if map is empty but raw contains data, try to recover permissions via regex
    if (empty($out) && is_string($raw) && strlen($raw) > 0) {
        if (@preg_match_all('/"access_level"\s*:\s*(\d+)\s*,.*?"permissions"\s*:\s*\[([^\]]*)\]/is', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $lvl = intval($row[1]);
                $permBlock = $row[2];
                $perms = [];
                if (@preg_match_all('/"([^\"]*)"/s', $permBlock, $pm)) {
                    foreach ($pm[1] as $p) {
                        $perms[] = stripcslashes($p);
                    }
                }
                if ($lvl) $out[$lvl] = $perms;
            }
            if (isset($_GET['debug_access']) && $_GET['debug_access'] && function_exists('error_log')) {
                error_log('[debug_access] load_access_map: fallback_parsed_count=' . count($out));
            }
        }
    }

    if (isset($_GET['debug_access']) && $_GET['debug_access']) {
        if (function_exists('error_log')) {
            error_log('[debug_access] load_access_map: keys=' . json_encode(array_values(array_keys($out))));
        }
    }

    $map = $out;
    $last_debug = [
        'file_exists' => file_exists($path) ? true : false,
        'raw_len' => is_string($raw) ? strlen($raw) : 0,
        'json_err' => json_last_error_msg(),
        'keys' => array_values(array_keys($out)),
    ];
    $last_debug = $last_debug; // noop to ensure variable exists in scope
    $last_debug_ref = $last_debug; // avoid unused warning
    $last_debug_ref = $last_debug_ref;
    $last_debug = $last_debug; // keep
    $last_debug_local = $last_debug; // keep
    $last_debug = $last_debug_local;
    $last_debug = $last_debug; // final
    $last_debug_placeholder = $last_debug; // final placeholder
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $last_debug_placeholder = $last_debug_placeholder;
    $map = $out;
    // store debug into static var for access via access_map_debug()
    $GLOBALS['__access_map_last_debug'] = $last_debug;
    return $map;
}
}

if (!function_exists('access_map_debug')) {
function access_map_debug()
{
    if (isset($GLOBALS['__access_map_last_debug'])) return $GLOBALS['__access_map_last_debug'];
    return [
        'file_exists' => file_exists(__DIR__ . '/../assets/js/accesslevel-map.json'),
        'raw_len' => 0,
        'json_err' => 'not_parsed',
        'keys' => [],
    ];
}
}

// Try to load permissions for a specific numeric level by scanning the raw file.
if (!function_exists('load_permissions_for_level')) {
function load_permissions_for_level($lvl)
{
    $path = __DIR__ . '/../assets/js/accesslevel-map.json';
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') return [];

    $searchPos = 0;
    $needle = '"access_level"';
    while (($pos = strpos($raw, '"access_level"', $searchPos)) !== false) {
        // extract a slice to find the number
        $slice = substr($raw, $pos, 200);
        if (preg_match('/"access_level"\s*:\s*(\d+)/', $slice, $m)) {
            $found = intval($m[1]);
            if ($found === intval($lvl)) {
                // find permissions key after this position
                $permPos = strpos($raw, '"permissions"', $pos);
                if ($permPos === false) return [];
                $bracketPos = strpos($raw, '[', $permPos);
                if ($bracketPos === false) return [];
                // find matching closing bracket, simple scan that ignores quotes
                $len = strlen($raw);
                $i = $bracketPos;
                $depth = 0;
                $inString = false;
                $escape = false;
                for (; $i < $len; $i++) {
                    $ch = $raw[$i];
                    if ($inString) {
                        if ($escape) { $escape = false; continue; }
                        if ($ch === '\\') { $escape = true; continue; }
                        if ($ch === '"') { $inString = false; continue; }
                        continue;
                    } else {
                        if ($ch === '"') { $inString = true; continue; }
                        if ($ch === '[') { $depth++; continue; }
                        if ($ch === ']') { $depth--; if ($depth === 0) break; }
                    }
                }
                if ($i >= $len) return [];
                $permBlock = substr($raw, $bracketPos + 1, $i - $bracketPos - 1);
                $perms = [];
                if (@preg_match_all('/"([^\"]*)"/s', $permBlock, $pm)) {
                    foreach ($pm[1] as $p) {
                        $perms[] = stripcslashes($p);
                    }
                }
                return $perms;
            }
        }
        $searchPos = $pos + 12;
    }
    return [];
}
}

if (!function_exists('resolve_access_level_column')) {
function resolve_access_level_column()
{
    static $columnName = null;
    if ($columnName !== null) return $columnName;

    global $conn;
    $columnName = 'access_level';

    if (!isset($conn) || !$conn) {
        return $columnName;
    }

    $checkAccess = @mysqli_query($conn, "SHOW COLUMNS FROM mldb.user_form LIKE 'access_level'");
    if ($checkAccess && mysqli_num_rows($checkAccess) > 0) {
        $columnName = 'access_level';
        return $columnName;
    }

    $checkAcess = @mysqli_query($conn, "SHOW COLUMNS FROM mldb.user_form LIKE 'acess_level'");
    if ($checkAcess && mysqli_num_rows($checkAcess) > 0) {
        $columnName = 'acess_level';
    }

    return $columnName;
}
}

if (!function_exists('get_user_access_level')) {
function get_user_access_level()
{
    static $resolvedLevel = null;
    if ($resolvedLevel !== null) {
        return $resolvedLevel;
    }

    $identities = [];
    if (!empty($_SESSION['admin_email'])) $identities[] = trim((string)$_SESSION['admin_email']);
    if (!empty($_SESSION['user_email'])) $identities[] = trim((string)$_SESSION['user_email']);
    if (!empty($_SESSION['id_number'])) $identities[] = trim((string)$_SESSION['id_number']);
    $identities = array_values(array_unique(array_filter($identities)));

    if (empty($identities)) {
        $resolvedLevel = isset($_SESSION['user_access_level']) ? intval($_SESSION['user_access_level']) : 0;
        return $resolvedLevel;
    }

    $levelColumn = resolve_access_level_column();

    global $conn;
    if (isset($conn) && $conn) {
        foreach ($identities as $identity) {
            $sql = "SELECT $levelColumn AS resolved_level FROM mldb.user_form WHERE email = ? OR id_number = ? LIMIT 1";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'ss', $identity, $identity);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $al);
                if (mysqli_stmt_fetch($stmt)) {
                    mysqli_stmt_close($stmt);
                    $resolvedLevel = intval($al);
                    $_SESSION['user_access_level'] = $resolvedLevel;
                    return $resolvedLevel;
                }
                mysqli_stmt_close($stmt);
            }

            $safe = mysqli_real_escape_string($conn, $identity);
            $raw = @mysqli_query($conn, "SELECT $levelColumn AS resolved_level FROM mldb.user_form WHERE email = '$safe' OR id_number = '$safe' LIMIT 1");
            if ($raw && $row = mysqli_fetch_assoc($raw)) {
                $resolvedLevel = intval($row['resolved_level']);
                $_SESSION['user_access_level'] = $resolvedLevel;
                return $resolvedLevel;
            }
        }
    }

    $resolvedLevel = isset($_SESSION['user_access_level']) ? intval($_SESSION['user_access_level']) : 0;
    return $resolvedLevel;
}
}

if (!function_exists('get_current_user_permissions')) {
function permission_children_map()
{
    return [
        'Bills Payment' => [
            'BP Import Transaction',
            'BP Import Cancellation',
            'BP Post Transaction',
            'BP Settlement Adjustment Entry',
            'BP Settlement Per Bank',
            'BP Report Volume',
            'BP Report EDI',
            'BP Report Transaction Details',
            'BP Report Transaction Summary',
            'BP Report Cancellation',
            'BP Report Balance Sheet'
        ],
        'Billing Invoice' => [
            'BI Create Manual',
            'BI Create Automated',
            'Invoice Review',
            'Invoice Approval',
            'BI Report Billing Invoice'
        ],
        'Masterfiles' => [
            'Masterfiles View Bank List'
        ],
        'Maintenance' => [
            'Accounts',
            'Maintenance Duplicate Transaction',
            'Maintenance Masterfiles Partner List',
            'Maintenance Masterfiles Bank List'
        ],
        'Accounts' => [
            'Maintenance Accounts User Management',
            'Maintenance Accounts Access Levels'
        ],
        'Tools' => [
            'Tools KPX Generator',
            'Tools Branch Maker',
            'Tools File Fetch'
        ]
    ];
}

function normalize_permission_list($permissions)
{
    if (!is_array($permissions)) {
        return [];
    }

    $set = [];
    foreach ($permissions as $permission) {
        if (!is_string($permission)) continue;
        $trimmed = trim($permission);
        if ($trimmed === '') continue;
        $set[$trimmed] = true;
    }

    $keys = array_keys($set);
    sort($keys, SORT_STRING);
    return $keys;
}

function expand_hierarchical_permissions($permissions)
{
    $normalized = normalize_permission_list($permissions);
    if (empty($normalized)) {
        return [];
    }

    $set = [];
    foreach ($normalized as $permission) {
        $set[$permission] = true;
    }

    $childrenMap = permission_children_map();
    $changed = true;

    while ($changed) {
        $changed = false;
        foreach ($childrenMap as $parent => $children) {
            if (!isset($set[$parent])) {
                continue;
            }

            $hasExplicitChild = false;
            foreach ($children as $child) {
                if (isset($set[$child])) {
                    $hasExplicitChild = true;
                    break;
                }
            }

            if ($hasExplicitChild) {
                continue;
            }

            foreach ($children as $child) {
                if (!isset($set[$child])) {
                    $set[$child] = true;
                    $changed = true;
                }
            }
        }
    }

    $keys = array_keys($set);
    sort($keys, SORT_STRING);
    return $keys;
}

function get_current_user_permissions()
{
    $lvl = intval(get_user_access_level());
    if (!$lvl) return [];

    $map = load_access_map();
    if (isset($map[$lvl]) && is_array($map[$lvl]) && count($map[$lvl]) > 0) {
        return expand_hierarchical_permissions($map[$lvl]);
    }
    // fallback: try to extract permissions for this level directly from the raw file
    $fb = load_permissions_for_level($lvl);
    if (is_array($fb) && count($fb) > 0) {
        return expand_hierarchical_permissions($fb);
    }
    // if map has the key but it's empty, return empty
    if (isset($map[$lvl])) return [];
    return [];
}
}

if (!function_exists('has_permission')) {
function has_permission($perm)
{
    if (!$perm) return false;
    $perms = get_current_user_permissions();
    return in_array($perm, $perms, true);
}
}

if (!function_exists('has_any_permission')) {
function has_any_permission(array $list)
{
    if (empty($list)) return false;
    $perms = get_current_user_permissions();
    foreach ($list as $p) {
        if (in_array($p, $perms, true)) return true;
    }
    return false;
}
}

?>