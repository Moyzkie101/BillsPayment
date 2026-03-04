(function (window, $) {
    'use strict';

    const DEFAULT_PERMISSION_TREE = [
        {
            key: 'Bills Payment',
            label: 'Bills Payment',
            icon: 'payments',
            children: [
                { key: 'BP Import Transaction', label: 'Import > Transaction', icon: 'receipt' },
                { key: 'BP Import Cancellation', label: 'Import > Cancellation', icon: 'block' },
                { key: 'BP Post Transaction', label: 'Post > Transaction', icon: 'send' },
                { key: 'BP Settlement Adjustment Entry', label: 'Settlement > Adjustment Entry', icon: 'account_tree' },
                { key: 'BP Settlement Per Bank', label: 'Settlement > Per Bank', icon: 'account_balance' },
                { key: 'BP Report Volume', label: 'Report > Volume Report', icon: 'bar_chart' },
                { key: 'BP Report EDI', label: 'Report > EDI Report', icon: 'description' },
                { key: 'BP Report Transaction Details', label: 'Report > Transaction Details', icon: 'list_alt' },
                { key: 'BP Report Transaction Summary', label: 'Report > Transaction Summary', icon: 'table_chart' },
                { key: 'BP Report Cancellation', label: 'Report > Cancellation Report', icon: 'cancel' },
                { key: 'BP Report Balance Sheet', label: 'Report > Balance Sheet', icon: 'analytics' }
            ]
        },
        {
            key: 'Billing Invoice',
            label: 'Billing Invoice',
            icon: 'receipt_long',
            children: [
                { key: 'BI Create Manual', label: 'Create > Service Charge (MANUAL)', icon: 'edit_note' },
                { key: 'BI Create Automated', label: 'Create > Service Charge (AUTOMATED)', icon: 'auto_mode' },
                { key: 'Invoice Review', label: 'Review > For Checking / Review', icon: 'rate_review' },
                { key: 'Invoice Approval', label: 'Approval > Billing Invoice Approval', icon: 'fact_check' },
                { key: 'BI Report Billing Invoice', label: 'Report > Billing Invoice Report', icon: 'summarize' }
            ]
        },
        {
            key: 'Masterfiles',
            label: 'Masterfiles',
            icon: 'folder',
            children: [
                { key: 'Masterfiles View Bank List', label: 'View > Bank List', icon: 'account_balance_wallet' }
            ]
        },
        {
            key: 'Maintenance',
            label: 'Maintenance',
            icon: 'build',
            children: [
                {
                    key: 'Accounts',
                    label: 'Accounts',
                    icon: 'manage_accounts',
                    children: [
                        { key: 'Maintenance Accounts User Management', label: 'Accounts > User Management', icon: 'group' },
                        { key: 'Maintenance Accounts Access Levels', label: 'Accounts > Access Levels', icon: 'vpn_key' }
                    ]
                },
                { key: 'Maintenance Duplicate Transaction', label: 'Duplicate > Transaction', icon: 'content_copy' },
                { key: 'Maintenance Masterfiles Partner List', label: 'Masterfiles > Partner List', icon: 'groups' },
                { key: 'Maintenance Masterfiles Bank List', label: 'Masterfiles > Bank List', icon: 'savings' }
            ]
        },
        {
            key: 'Tools',
            label: 'Tools',
            icon: 'handyman',
            children: [
                { key: 'Tools KPX Generator', label: 'KPX/KP7 Generator', icon: 'memory' },
                { key: 'Tools Branch Maker', label: 'Branch Maker', icon: 'alt_route' },
                { key: 'Tools File Fetch', label: 'File Fetch', icon: 'cloud_download' }
            ]
        }
    ];

    const LEGACY_ALIAS_CHILDREN = {
        'Bills Payment': [
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
        'Billing Invoice': [
            'BI Create Manual',
            'BI Create Automated',
            'Invoice Review',
            'Invoice Approval',
            'BI Report Billing Invoice'
        ],
        'Masterfiles': ['Masterfiles View Bank List'],
        'Maintenance': [
            'Accounts',
            'Maintenance Accounts User Management',
            'Maintenance Accounts Access Levels',
            'Maintenance Duplicate Transaction',
            'Maintenance Masterfiles Partner List',
            'Maintenance Masterfiles Bank List'
        ],
        'Accounts': ['Maintenance Accounts User Management', 'Maintenance Accounts Access Levels'],
        'Tools': ['Tools KPX Generator', 'Tools Branch Maker', 'Tools File Fetch']
    };

    let ACCESS_LEVEL_MAP = {};
    let ACCESS_LEVELS_ARRAY = [];
    let PERMISSION_TREE = JSON.parse(JSON.stringify(DEFAULT_PERMISSION_TREE));
    let isMapReady = false;

    initializeAccessLevelMap();

    function initializeAccessLevelMap() {
        $.ajax({
            url: '../../../assets/js/accesslevel-map.json',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function (response) {
                parseMapResponse(response);
                isMapReady = true;
            },
            error: function () {
                useGeneratedFallback();
            }
        });
    }

    function parseMapResponse(response) {
        if (Array.isArray(response)) {
            ACCESS_LEVELS_ARRAY = normalizeLevelsArray(response);
            ACCESS_LEVEL_MAP = convertArrayToMap(ACCESS_LEVELS_ARRAY);
            PERMISSION_TREE = JSON.parse(JSON.stringify(DEFAULT_PERMISSION_TREE));
            return;
        }

        if (response && typeof response === 'object') {
            const levels = Array.isArray(response.access_levels) ? response.access_levels : [];
            const catalog = Array.isArray(response.permission_catalog) ? response.permission_catalog : [];
            ACCESS_LEVELS_ARRAY = normalizeLevelsArray(levels);
            PERMISSION_TREE = catalog.length ? normalizeTree(catalog) : JSON.parse(JSON.stringify(DEFAULT_PERMISSION_TREE));

            // If the provided mapping is very small (legacy or minimal), generate
            // a deterministic set of access level combinations (singles + pairs)
            // from the permission catalog so the find/match logic works for
            // menu+submenu+child combinations without requiring a massive file.
            (function ensureComprehensiveMapping() {
                // use only leaf keys (submenu/child) to generate combinations
                function collectLeafKeys(nodes) {
                    const out = [];
                    (nodes || []).forEach(function walk(node) {
                        if (!node) return;
                        if (!node.children || !node.children.length) {
                            out.push(node.key);
                            return;
                        }
                        (node.children || []).forEach(walk);
                    });
                    return out;
                }

                const flat = collectLeafKeys(PERMISSION_TREE).slice();
                const uniqueKeys = Array.from(new Set(flat)).sort();

                // If levels provided are fewer than the number of single permissions,
                // assume we need to seed a comprehensive mapping.
                if ((ACCESS_LEVELS_ARRAY || []).length < uniqueKeys.length) {
                    const generated = [];
                    const seen = new Set();
                    let nextLevel = 1;

                    function pushPerms(perms) {
                        const key = createPermissionsKey(perms);
                        if (seen.has(key)) return;
                        seen.add(key);
                        generated.push({ access_level: nextLevel++, permissions: normalizePermissions(perms) });
                    }

                    // singles
                    for (let i = 0; i < uniqueKeys.length; i++) {
                        pushPerms([uniqueKeys[i]]);
                    }

                    // pairs (unordered)
                    for (let i = 0; i < uniqueKeys.length; i++) {
                        for (let j = i + 1; j < uniqueKeys.length; j++) {
                            pushPerms([uniqueKeys[i], uniqueKeys[j]]);
                        }
                    }

                    // Merge any explicitly provided mappings (keep their permission sets too)
                    (ACCESS_LEVELS_ARRAY || []).forEach(function (row) {
                        pushPerms(row.permissions || []);
                    });

                    ACCESS_LEVELS_ARRAY = generated.slice();
                }
            })();

            ACCESS_LEVEL_MAP = convertArrayToMap(ACCESS_LEVELS_ARRAY);
            return;
        }

        useGeneratedFallback();
    }

    function useGeneratedFallback() {
        ACCESS_LEVEL_MAP = {};
        ACCESS_LEVELS_ARRAY = [];
        PERMISSION_TREE = JSON.parse(JSON.stringify(DEFAULT_PERMISSION_TREE));
        isMapReady = true;
    }

    function normalizeLevelsArray(levelArray) {
        return levelArray
            .map(function (item) {
                const level = parseInt(item.access_level, 10);
                if (!level) return null;
                return {
                    access_level: level,
                    permissions: normalizePermissions(item.permissions || [])
                };
            })
            .filter(Boolean)
            .sort(function (a, b) { return a.access_level - b.access_level; });
    }

    function normalizeTree(tree) {
        return tree
            .map(function (node) {
                if (!node || typeof node !== 'object' || !node.key) return null;
                const normalized = {
                    key: String(node.key),
                    label: node.label ? String(node.label) : String(node.key),
                    icon: node.icon ? String(node.icon) : 'check_circle'
                };
                if (Array.isArray(node.children) && node.children.length) {
                    normalized.children = normalizeTree(node.children);
                }
                return normalized;
            })
            .filter(Boolean);
    }

    function convertArrayToMap(levelArray) {
        const map = {};
        levelArray.forEach(function (item) {
            map[item.access_level] = {
                access_level: item.access_level,
                permissions: normalizePermissions(item.permissions)
            };
        });
        return map;
    }

    function normalizePermissions(permissions) {
        if (!Array.isArray(permissions)) return [];
        const set = new Set();
        permissions.forEach(function (permission) {
            if (typeof permission === 'string' && permission.trim() !== '') {
                set.add(permission.trim());
            }
        });
        return Array.from(set).sort();
    }

    function flattenTree(tree) {
        const out = [];
        (tree || []).forEach(function walk(node) {
            out.push(node.key);
            (node.children || []).forEach(walk);
        });
        return out;
    }

    function descendantsOf(key) {
        let descendants = [];
        (PERMISSION_TREE || []).forEach(function walk(node) {
            if (node.key === key) {
                descendants = flattenTree(node.children || []);
                return;
            }
            (node.children || []).forEach(walk);
        });
        return descendants;
    }

    function ancestorsOf(key) {
        const trail = [];

        function walk(nodes, parents) {
            for (let index = 0; index < (nodes || []).length; index++) {
                const node = nodes[index];
                if (!node) continue;

                if (node.key === key) {
                    trail.push.apply(trail, parents);
                    return true;
                }

                if (Array.isArray(node.children) && node.children.length) {
                    const nextParents = parents.concat(node.key);
                    if (walk(node.children, nextParents)) {
                        return true;
                    }
                }
            }
            return false;
        }

        walk(PERMISSION_TREE || [], []);
        return Array.from(new Set(trail));
    }

    function expandLegacyAliases(permissions) {
        const set = new Set(normalizePermissions(permissions));

        Object.keys(LEGACY_ALIAS_CHILDREN).forEach(function (legacyKey) {
            if (!set.has(legacyKey)) {
                return;
            }

            const children = LEGACY_ALIAS_CHILDREN[legacyKey] || [];
            const hasExplicitChild = children.some(function (childKey) {
                return set.has(childKey);
            });

            if (!hasExplicitChild) {
                children.forEach(function (child) {
                    set.add(child);
                });
            }
        });

        return Array.from(set).sort();
    }

    function getPermissionsByLevel(level) {
        const normalizedLevel = parseInt(level, 10);
        if (!normalizedLevel || !ACCESS_LEVEL_MAP[normalizedLevel]) {
            return [];
        }
        return expandLegacyAliases(ACCESS_LEVEL_MAP[normalizedLevel].permissions || []);
    }

    function createPermissionsKey(permissions) {
        return JSON.stringify(normalizePermissions(permissions));
    }

    function getAllLevelsArray() {
        return ACCESS_LEVELS_ARRAY.slice();
    }

    function getNextAccessLevel() {
        let maxLevel = 0;
        ACCESS_LEVELS_ARRAY.forEach(function (row) {
            maxLevel = Math.max(maxLevel, parseInt(row.access_level, 10) || 0);
        });
        return maxLevel + 1;
    }

    function findAccessLevelByPermissions(permissions) {
        const target = createPermissionsKey(normalizePermissions(permissions));
        for (let index = 0; index < ACCESS_LEVELS_ARRAY.length; index++) {
            const row = ACCESS_LEVELS_ARRAY[index];
            if (createPermissionsKey(row.permissions || []) === target) {
                return parseInt(row.access_level, 10) || 0;
            }
        }
        return 0;
    }

    function updateUserAccessLevel(idNumber, newAccessLevel, permissions, onSuccess, onError) {
        const payload = {
            id_number: idNumber,
            access_level: newAccessLevel || 0,
            permissions: normalizePermissions(permissions || [])
        };

        $.ajax({
            url: '../../../models/updated/update-access-level.php',
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json',
            success: function (response) {
                if (typeof onSuccess === 'function') {
                    onSuccess(response);
                }
            },
            error: function (xhr) {
                if (typeof onError === 'function') {
                    onError(xhr);
                }
            }
        });
    }

    function getPermissionTree() {
        return JSON.parse(JSON.stringify(PERMISSION_TREE));
    }

    window.AccessLevelManager = {
        isReady: function () { return isMapReady; },
        getPermissionTree: getPermissionTree,
        getPermissionsByLevel: getPermissionsByLevel,
        getAllLevelsArray: getAllLevelsArray,
        getNextAccessLevel: getNextAccessLevel,
        createPermissionsKey: createPermissionsKey,
        findAccessLevelByPermissions: findAccessLevelByPermissions,
        normalizePermissions: normalizePermissions,
        ancestorsOf: ancestorsOf,
        descendantsOf: descendantsOf,
        expandLegacyAliases: expandLegacyAliases,
        updateUserAccessLevel: updateUserAccessLevel
    };
})(window, jQuery);
