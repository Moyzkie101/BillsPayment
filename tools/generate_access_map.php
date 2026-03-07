<?php
// generate_access_map.php
// Usage: php tools/generate_access_map.php
// Regenerates `accesslevel-map.json` using a menu-based access-level scheme:
// - one access level per root menu (levels start at 1)
// - admin access level -1 contains all permissions (sentinel)

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

$keysPerRoot = [];
$allLeafKeys = [];
foreach ($permissionCatalog as $root) {
    $rootLeaves = flatten_catalog_leaf_keys([$root]);
    $keysPerRoot[] = $rootLeaves;
    $allLeafKeys = array_merge($allLeafKeys, $rootLeaves);
}
$allLeafKeys = array_values(array_unique($allLeafKeys));
sort($allLeafKeys, SORT_STRING);

if (count($allLeafKeys) === 0) {
    echo "No permission keys found to generate mapping.\n";
    exit(1);
}

// backup
if (!copy($mapPath, $backupPath)) {
    echo "Failed to create backup at $backupPath\n";
    exit(1);
}
echo "Backup created: $backupPath\n";

$accessLevels = [];

// Build bitmask-based combos for all non-empty subsets of root menus.
$numRoots = count($keysPerRoot);
if ($numRoots > 0) {
    $maxMask = (1 << $numRoots) - 1;
    for ($mask = 1; $mask <= $maxMask; $mask++) {
        $combo = [];
        for ($i = 0; $i < $numRoots; $i++) {
            if (($mask >> $i) & 1) {
                $combo = array_merge($combo, $keysPerRoot[$i]);
            }
        }
        $combo = array_values(array_unique(array_filter($combo, 'is_string')));
        sort($combo, SORT_STRING);
        $accessLevels[] = [ 'access_level' => $mask, 'permissions' => $combo ];
    }
}

// admin sentinel remains -1 (full unrestricted access)
$accessLevels[] = [ 'access_level' => -1, 'permissions' => $allLeafKeys ];

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

echo "Generated new menu-based access level map with " . count($accessLevels) . " entries.\n";
echo "Wrote to: $mapPath\n";
exit(0);
