<?php
// ANSI colors for CLI output
$green = "\033[32m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$magenta = "\033[35m";
$red = "\033[31m";
$reset = "\033[0m";

// Load config.php
$configFile = __DIR__ . '/app/etc/config.php';
if (!file_exists($configFile)) {
    echo "{$red}ERROR: app/etc/config.php not found!{$reset}\n";
    exit(1);
}
$config = require $configFile;

// Load themes from config
$themes = isset($config['themes']) ? $config['themes'] : [];
$themePathToInfo = [];
foreach ($themes as $fullPath => $info) {
    $themePathToInfo[$fullPath] = $info;
}

// Load system theme assignments from config.php if present
$systemConfig = isset($config['system']) ? $config['system'] : [];

function findThemeIdInConfig($scope, $code, $systemConfig) {
    if ($scope === 'stores' && isset($systemConfig['stores'][$code]['design']['theme']['theme_id'])) {
        return $systemConfig['stores'][$code]['design']['theme']['theme_id'];
    }
    if ($scope === 'websites' && isset($systemConfig['websites'][$code]['design']['theme']['theme_id'])) {
        return $systemConfig['websites'][$code]['design']['theme']['theme_id'];
    }
    if ($scope === 'default' && isset($systemConfig['default']['design']['theme']['theme_id'])) {
        return $systemConfig['default']['design']['theme']['theme_id'];
    }
    return null;
}

// Connect to DB for fallback (core_config_data)
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();

$resource = $obj->get(ResourceConnection::class);
$connection = $resource->getConnection();
$storeManager = $obj->get(StoreManagerInterface::class);

// Helper: get DB config value
function getThemeIdFromDb($connection, $scope, $scopeId) {
    $sql = "SELECT value FROM core_config_data WHERE path = 'design/theme/theme_id' AND scope = ? AND scope_id = ?";
    $themeId = $connection->fetchOne($sql, [$scope, $scopeId]);
    return $themeId ?: null;
}

echo "{$magenta}Magento effective themes per store view (config.php > DB fallback):{$reset}\n\n";

// Build themeId => themePath and themeId => info maps
$themeIdToPath = [];
$themeIdToInfo = [];
$rows = $connection->fetchAll("SELECT theme_id, theme_path, theme_title, area, code FROM theme");
foreach ($rows as $row) {
    $themeIdToPath[$row['theme_id']] = $row['area'] . '/' . $row['theme_path'];
    $themeIdToInfo[$row['theme_id']] = $row;
}

// Build map for website/store code <-> id
$websites = isset($config['scopes']['websites']) ? $config['scopes']['websites'] : [];
$stores = isset($config['scopes']['stores']) ? $config['scopes']['stores'] : [];
if (empty($websites)) {
    $websites = [];
    $rows = $connection->fetchAll("SELECT website_id, code, name FROM store_website");
    foreach ($rows as $row) {
        $websites[$row['code']] = [
            'website_id' => $row['website_id'],
            'name' => $row['name'],
        ];
    }
}

if (empty($stores)) {
    $stores = [];
    $rows = $connection->fetchAll("SELECT store_id, code, name, website_id FROM store");
    foreach ($rows as $row) {
        $stores[$row['code']] = [
            'store_id' => $row['store_id'],
            'name' => $row['name'],
            'website_id' => $row['website_id'],
        ];
    }
}

$websiteIdToCode = [];
foreach ($websites as $code => $w) {
    $websiteIdToCode[$w['website_id']] = $code;
}
$storeIdToCode = [];
foreach ($stores as $code => $s) {
    $storeIdToCode[$s['store_id']] = $code;
}

// Process each website and its stores
foreach ($websites as $websiteCode => $websiteData) {
    $websiteId = $websiteData['website_id'];
    $websiteName = $websiteData['name'];
    echo "{$blue}Website: {$cyan}$websiteCode{$reset} (ID: $websiteId) - {$green}$websiteName{$reset}\n";

    // Find all stores for this website
    foreach ($stores as $storeCode => $storeData) {
        if ($storeData['website_id'] != $websiteId) continue;
        $storeId = $storeData['store_id'];
        $storeName = $storeData['name'];

        // 1. Try config.php: store-level theme
        $themeId = findThemeIdInConfig('stores', $storeCode, $systemConfig);
        $themeSource = $themeId ? "{$yellow}config.php:store{$reset}" : '';

        // 2. If not, config.php: website-level theme
        if (!$themeId) {
            $themeId = findThemeIdInConfig('websites', $websiteCode, $systemConfig);
            $themeSource = $themeId ? "{$yellow}config.php:website{$reset}" : '';
        }

        // 3. If not, config.php: default-level theme
        if (!$themeId) {
            $themeId = findThemeIdInConfig('default', null, $systemConfig);
            $themeSource = $themeId ? "{$yellow}config.php:default{$reset}" : '';
        }

        // 4. If still not, check DB: store
        if (!$themeId) {
            $themeId = getThemeIdFromDb($connection, 'stores', $storeId);
            $themeSource = $themeId ? "{$cyan}db:store{$reset}" : '';
        }

        // 5. If not, DB: website
        if (!$themeId) {
            $themeId = getThemeIdFromDb($connection, 'websites', $websiteId);
            $themeSource = $themeId ? "{$cyan}db:website{$reset}" : '';
        }

        // 6. If not, DB: default
        if (!$themeId) {
            $themeId = getThemeIdFromDb($connection, 'default', 0);
            $themeSource = $themeId ? "{$cyan}db:default{$reset}" : '';
        }

        // 7. Show result (with theme info)
        $themeInfo = null;
        if ($themeId && isset($themeIdToPath[$themeId]) && isset($themePathToInfo[$themeIdToPath[$themeId]])) {
            $themeInfo = $themePathToInfo[$themeIdToPath[$themeId]];
        } elseif ($themeId && isset($themeIdToInfo[$themeId])) {
            $themeInfo = $themeIdToInfo[$themeId];
        }

        if ($themeInfo) {
            $themeTitle = isset($themeInfo['theme_title']) ? $themeInfo['theme_title'] : '';
            $themeCode = isset($themeInfo['code']) ? $themeInfo['code'] : '';
        } else {
            $themeTitle = "{$red}(not found in DB/config){$reset}";
            $themeCode = "{$red}(unknown code){$reset}";
        }

        echo "  Store: {$cyan}$storeCode{$reset} (ID: $storeId) - {$green}$storeName{$reset}\n";
        echo "    Theme: {$green}$themeTitle{$reset} [code: {$cyan}$themeCode{$reset}] (source: $themeSource)\n";
    }
    echo "{$magenta}-------------------------------------------{$reset}\n";
}
