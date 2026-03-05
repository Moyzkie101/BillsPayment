<?php
// generate_access_map.php
// Usage: php tools/generate_access_map.php
// This script backs up the existing accesslevel-map.json and writes a new
// version containing single-permission and pair-permission access levels.

$mapPath = __DIR__ . '/../assets/js/accesslevel-map.json';
$backupPath = $mapPath . '.bak.' . date('Ymd_His');

if (!file_exists($mapPath)) {
    echo "accesslevel-map.json not found at: $mapPath\n";
    exit(1);
}

$raw = file_get_contents($mapPath);
$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    echo "Failed to parse JSON from $mapPath\n";
    exit(1);
}

$permissionCatalog = [];
if (isset($decoded['permission_catalog']) && is_array($decoded['permission_catalog'])) {
    $permissionCatalog = $decoded['permission_catalog'];
} else {
    echo "permission_catalog missing or invalid in map file\n";
    exit(1);
}

function flatten_catalog_keys($nodes) {
    $keys = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        if (isset($node['key']) && is_string($node['key']) && trim($node['key']) !== '') {
            $keys[] = trim($node['key']);
        }
        if (isset($node['children']) && is_array($node['children'])) {
            $keys = array_merge($keys, flatten_catalog_keys($node['children']));
        }
    }
    $keys = array_values(array_unique($keys));
    sort($keys, SORT_STRING);
    return $keys;
}

// collect only leaf node keys (nodes without children)
function flatten_catalog_leaf_keys($nodes) {
    $keys = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        $hasChildren = isset($node['children']) && is_array($node['children']) && count($node['children']) > 0;
        if (!$hasChildren && isset($node['key']) && is_string($node['key']) && trim($node['key']) !== '') {
            $keys[] = trim($node['key']);
        }
        if ($hasChildren) {
            $keys = array_merge($keys, flatten_catalog_leaf_keys($node['children']));
        }
    }
    $keys = array_values(array_unique($keys));
    sort($keys, SORT_STRING);
    return $keys;
}

$keys = flatten_catalog_leaf_keys($permissionCatalog);
$countKeys = count($keys);
if ($countKeys === 0) {
    echo "No permission keys found to generate mapping.\n";
    exit(1);
}

// Create backup
if (!copy($mapPath, $backupPath)) {
    echo "Failed to create backup at $backupPath\n";
    exit(1);
}

echo "Backup created: $backupPath\n";

$accessLevels = [];
$nextLevel = 1;

// allow specifying generation mode as first CLI argument.
// modes: numeric max size (e.g. 3), 'all', or 'perroot'
$maxSize = null;
$wantAll = false;
$mode = 'partial'; // 'partial' (numeric maxSize), 'all', 'perroot'
$crossSize = 2; // for perroot mode, how many cross-root combinations to include
if (isset($argv[1])) {
    $arg1 = strtolower((string)$argv[1]);
    if (is_numeric($argv[1])) {
        $mode = 'partial';
        $maxSize = max(1, (int)$argv[1]);
    } elseif ($arg1 === 'all') {
        $mode = 'all';
        $wantAll = true;
    } elseif ($arg1 === 'perroot') {
        $mode = 'perroot';
        if (isset($argv[2]) && is_numeric($argv[2])) {
            $crossSize = max(1, (int)$argv[2]);
        }
    }
}

// estimate total combinations
if ($mode === 'all') {
    // total non-empty subsets = 2^P - 1
    if ($countKeys >= 63) {
        echo "Permission count too large to compute full power-set safely (count={$countKeys}). Aborting.\n";
        exit(1);
    }
    $totalCombos = (1 << $countKeys) - 1;
    echo "Requested full power-set generation: {$totalCombos} combinations.\n";
} elseif ($mode === 'perroot') {
    // estimate sum of (2^p_i - 1) over each root node + cross-root combos up to crossSize
    $totalCombos = 0;
    foreach ($permissionCatalog as $root) {
        $leafKeys = flatten_catalog_leaf_keys([$root]);
        $p = count($leafKeys);
        if ($p > 0) $totalCombos += (1 << $p) - 1;
    }
    // cross-root combos using global keys up to crossSize
    for ($k = 1; $k <= min($crossSize, $countKeys); $k++) {
        // compute nCk
        $n = $countKeys;
        $r = $k;
        $num = 1;
        $den = 1;
        for ($i = 0; $i < $r; $i++) {
            $num *= ($n - $i);
            $den *= ($i + 1);
            $g = gcd($num, $den);
            if ($g > 1) { $num /= $g; $den /= $g; }
        }
        $totalCombos += (int)($num / $den);
    }
    echo "Generating per-root full power-sets + cross-root combos up to size {$crossSize} (estimated total: {$totalCombos})\n";
} else {
    if ($maxSize === null) $maxSize = 2; // default
    // compute sum_{k=1..maxSize} C(P,k)
    $totalCombos = 0;
    for ($k = 1; $k <= min($maxSize, $countKeys); $k++) {
        $n = $countKeys;
        $r = $k;
        $num = 1;
        $den = 1;
        for ($i = 0; $i < $r; $i++) {
            $num *= ($n - $i);
            $den *= ($i + 1);
            $g = gcd($num, $den);
            if ($g > 1) { $num /= $g; $den /= $g; }
        }
        $totalCombos += (int)($num / $den);
    }
    echo "Generating combinations up to size: $maxSize (estimated total: $totalCombos)\n";
}

// safety threshold - do not run huge generation without explicit force
$SAFETY_LIMIT = 5000000; // 5 million
$force = (isset($argv[2]) && strtolower($argv[2]) === 'force');
if ($totalCombos > $SAFETY_LIMIT && !$force) {
    echo "Generation would produce $totalCombos combinations which may be very large.\n";
    echo "To proceed anyway, re-run with the first arg 'all' or number and second arg 'force'.\n";
    exit(1);
}

// helper gcd
function gcd($a, $b) {
    $a = (int)$a; $b = (int)$b;
    if ($b === 0) return abs($a);
    while ($b) {
        $t = $b;
        $b = $a % $b;
        $a = $t;
    }
    return abs($a);
}

// generator for combinations of fixed size using iterative indices
function generate_combinations_indices($items, $size) {
    $n = count($items);
    if ($size <= 0 || $size > $n) return;
    $indices = range(0, $size - 1);
    while (true) {
        $combo = [];
        foreach ($indices as $idx) $combo[] = $items[$idx];
        yield $combo;
        $i = $size - 1;
        while ($i >= 0 && $indices[$i] === $n - $size + $i) $i--;
        if ($i < 0) break;
        $indices[$i]++;
        for ($j = $i + 1; $j < $size; $j++) {
            $indices[$j] = $indices[$j - 1] + 1;
        }
    }
}

function popcount($x) {
    $x = (int)$x;
    $c = 0;
    while ($x) {
        $c += $x & 1;
        $x >>= 1;
    }
    return $c;
}

// streaming write to avoid holding everything in memory
$tmpPath = $mapPath . '.tmp';
$fp = fopen($tmpPath, 'w');
if ($fp === false) {
    echo "Failed to open temporary file for writing: $tmpPath\n";
    exit(1);
}

fwrite($fp, "{" . PHP_EOL);
fwrite($fp, "    \"version\": 2," . PHP_EOL);
fwrite($fp, "    \"permission_catalog\": " . json_encode($permissionCatalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "," . PHP_EOL);
fwrite($fp, "    \"access_levels\": [" . PHP_EOL);

$written = 0;
$seen = [];

// function to write a single access level object
function write_access_level_obj($fp, $level, $perms, $isFirst) {
    $obj = [ 'access_level' => $level, 'permissions' => array_values($perms) ];
    $json = json_encode($obj, JSON_UNESCAPED_SLASHES);
    if ($isFirst) {
        fwrite($fp, "        " . $json . PHP_EOL);
    } else {
        fwrite($fp, "        ," . $json . PHP_EOL);
    }
}

// iterate and stream combinations based on the selected mode
if ($mode === 'perroot') {
    // per-root: generate every non-empty subset within each root node (leaf keys only)
    foreach ($permissionCatalog as $root) {
        $rootLeaves = flatten_catalog_leaf_keys([$root]);
        $p = count($rootLeaves);
        if ($p === 0) continue;
        // iterate masks 1..(1<<p)-1
        for ($mask = 1; $mask < (1 << $p); $mask++) {
            $combo = [];
            for ($b = 0; $b < $p; $b++) {
                if (($mask >> $b) & 1) $combo[] = $rootLeaves[$b];
            }
            $key = json_encode($combo);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $isFirst = ($written === 0);
            write_access_level_obj($fp, $nextLevel++, $combo, $isFirst);
            $written++;
        }
    }

    // also include cross-root combos up to $crossSize using global leaf keys
    for ($sz = 1; $sz <= min($crossSize, $countKeys); $sz++) {
        foreach (generate_combinations_indices($keys, $sz) as $combo) {
            $key = json_encode($combo);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $isFirst = ($written === 0);
            write_access_level_obj($fp, $nextLevel++, $combo, $isFirst);
            $written++;
        }
    }
} elseif ($mode === 'all') {
    // full power-set over global leaf keys
    $n = $countKeys;
    $limit = (1 << $n);
    for ($mask = 1; $mask < $limit; $mask++) {
        $combo = [];
        for ($b = 0; $b < $n; $b++) {
            if (($mask >> $b) & 1) $combo[] = $keys[$b];
        }
        $isFirst = ($written === 0);
        write_access_level_obj($fp, $nextLevel++, $combo, $isFirst);
        $written++;
    }
} else {
    // partial: combinations up to $maxSize across global leaf keys
    for ($sz = 1; $sz <= min($maxSize, $countKeys); $sz++) {
        foreach (generate_combinations_indices($keys, $sz) as $combo) {
            $isFirst = ($written === 0);
            write_access_level_obj($fp, $nextLevel++, $combo, $isFirst);
            $written++;
        }
    }
}

fwrite($fp, "    ],\n");
fwrite($fp, "    \"needs_migration\": false\n");
fwrite($fp, "}\n");
fclose($fp);

// replace original file with tmp
if (!rename($tmpPath, $mapPath)) {
    echo "Failed to replace original map file with generated file.\n";
    exit(1);
}

echo "Generated new access level map with " . $written . " entries.\n";
echo "Wrote to: $mapPath\n";
exit(0);

$newMap = [
    'version' => 2,
    'permission_catalog' => $permissionCatalog,
    'access_levels' => $accessLevels,
    'needs_migration' => false
];

$encoded = json_encode($newMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    echo "Failed to encode new map JSON\n";
    exit(1);
}

if (file_put_contents($mapPath, $encoded) === false) {
    echo "Failed to write new map to $mapPath\n";
    exit(1);
}

echo "Generated new access level map with " . count($accessLevels) . " entries.\n";
echo "Wrote to: $mapPath\n";
exit(0);
