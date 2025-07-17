<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ThemeFactory;

require __DIR__ . '/app/bootstrap.php';

// ANSI colors
$green = "\033[32m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$magenta = "\033[35m";
$red = "\033[31m";
$reset = "\033[0m";

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();

$resource = $obj->get(ResourceConnection::class);
$connection = $resource->getConnection();
$storeManager = $obj->get(StoreManagerInterface::class);
$themeFactory = $obj->get(ThemeFactory::class);

// Helper: resolve theme_id to theme title and code
function getThemeTitleAndCode($themeId, $themeFactory)
{
    if (!$themeId) return ['(not set)', null];
    try {
        $theme = $themeFactory->create()->load($themeId);
        if ($theme && $theme->getId()) {
            $themeTitle = $theme->getThemeTitle() . " (ID: $themeId)";
            $themePath = $theme->getFullPath(); // e.g. frontend/Vendor/Name
            // Now resolve to app/design/frontend path and read registration.php
            $themeCode = findThemeCodeFromRegistration($themePath);
            if ($themeCode) {
                return [$themeTitle, $themeCode];
            } else {
                return [$themeTitle, '(code not found)'];
            }
        }
        return ["(Unknown ID: $themeId)", null];
    } catch (\Exception $e) {
        return ["(Error loading theme ID: $themeId)", null];
    }
}

// Helper: returns theme code from registration.php in app/design/frontend
function findThemeCodeFromRegistration($themePath)
{
    $parts = explode('/', $themePath);
    if (count($parts) != 3 || $parts[0] != 'frontend') {
        return null;
    }
    $vendor = $parts[1];
    $theme = $parts[2];
    $registration = "app/design/frontend/$vendor/$theme/registration.php";
    if (!file_exists($registration)) {
        return null;
    }
    $content = file_get_contents($registration);
    // Regex to extract theme code
    if (preg_match('/ComponentRegistrar::THEME,\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        return $m[1];
    }
    return null;
}

// Get all website-level theme config
$sql = "SELECT scope, scope_id, value FROM core_config_data WHERE path = 'design/theme/theme_id'";
$themeConfigs = $connection->fetchAll($sql);

// Organize theme config for lookup
$themeByScope = [
    'default' => null, // default fallback
    'websites' => [],
    'stores' => [],
];
foreach ($themeConfigs as $conf) {
    if ($conf['scope'] == 'default') {
        $themeByScope['default'] = $conf['value'];
    } elseif ($conf['scope'] == 'websites') {
        $themeByScope['websites'][$conf['scope_id']] = $conf['value'];
    } elseif ($conf['scope'] == 'stores') {
        $themeByScope['stores'][$conf['scope_id']] = $conf['value'];
    }
}

// Print per-website and per-store theme usage
echo "{$magenta}Magento theme usage per website and store view:{$reset}\n\n";

foreach ($storeManager->getWebsites() as $website) {
    $websiteId = $website->getId();
    $websiteCode = $website->getCode();

    // Theme for website scope or fallback
    $themeId = null;
    if (isset($themeByScope['websites'][$websiteId])) {
        $themeId = $themeByScope['websites'][$websiteId];
    } elseif ($themeByScope['default']) {
        $themeId = $themeByScope['default'];
    }
    list($themeTitle, $themeCode) = getThemeTitleAndCode($themeId, $themeFactory);

    echo "{$blue}Website: {$cyan}$websiteCode{$reset} (ID: $websiteId)\n";
    echo "  {$yellow}Theme (website scope): {$green}$themeTitle{$reset}";
    if ($themeCode && $themeCode !== '(code not found)') {
        echo " {$cyan}[code: {$themeCode}]{$reset}";
    } else {
        echo " {$red}(code not found){$reset}";
    }
    echo "\n";

    // Now list each store view
    foreach ($website->getStores() as $store) {
        $storeId = $store->getId();
        $storeCode = $store->getCode();
        // Store-level override?
        if (isset($themeByScope['stores'][$storeId])) {
            $storeThemeId = $themeByScope['stores'][$storeId];
            list($storeThemeTitle, $storeThemeCode) = getThemeTitleAndCode($storeThemeId, $themeFactory);
            echo "    Store: {$cyan}$storeCode{$reset} (ID: $storeId) - Theme: {$green}$storeThemeTitle{$reset}";
            if ($storeThemeCode && $storeThemeCode !== '(code not found)') {
                echo " {$cyan}[code: {$storeThemeCode}]{$reset}";
            } else {
                echo " {$red}(code not found){$reset}";
            }
            echo "\n";
        } else {
            // Inherit from website or default
            echo "    Store: {$cyan}$storeCode{$reset} (ID: $storeId) - Theme: (inherited: {$green}$themeTitle{$reset}";
            if ($themeCode && $themeCode !== '(code not found)') {
                echo " {$cyan}[code: {$themeCode}]{$reset}";
            } else {
                echo " {$red}(code not found){$reset}";
            }
            echo ")\n";
        }
    }
    echo "{$magenta}-------------------------------------------{$reset}\n";
}
