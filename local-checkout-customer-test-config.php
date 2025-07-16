<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();

$state = $obj->get(State::class);
try {
    $state->setAreaCode('frontend');
} catch (LocalizedException $e) {
    // Area code already set, ignore
}

// Enable Check/Money Order payment method in core_config_data (scope: default)
$resource = $obj->get(ResourceConnection::class);
$connection = $resource->getConnection();

$sqlSelect = "SELECT config_id FROM core_config_data WHERE path = 'payment/checkmo/active' AND scope = 'default' AND scope_id = 0";
$configId = $connection->fetchOne($sqlSelect);

if ($configId) {
    $sqlUpdate = "UPDATE core_config_data SET value = 1 WHERE config_id = ?";
    $connection->query($sqlUpdate, [$configId]);
    echo "\033[32mCheck / Money Order payment method ENABLED (updated).\033[0m\n";
} else {
    $sqlInsert = "INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'payment/checkmo/active', 1)";
    $connection->query($sqlInsert);
    echo "\033[32mCheck / Money Order payment method ENABLED (inserted).\033[0m\n";
}

// Gather required customer attributes and assign test values
$attributeValues = [
    'email' => 'test@test.com',
    'firstname' => 'testName',
    'lastname' => 'testLastname',
    'password' => 'Password123!'
];

// Use EAV to get all required attributes for customer entity
$eavConfig = $obj->get(EavConfig::class);
$customerMetadata = $obj->get(CustomerMetadataInterface::class);
$attributeMetadata = $customerMetadata->getAllAttributesMetadata();

// Get website code and ID, print available codes, use 'base' by default
$storeManager = $obj->get(StoreManagerInterface::class);

echo "\nAvailable websites:\n";
foreach ($storeManager->getWebsites() as $website) {
    echo "  Code: \033[36m" . $website->getCode() . "\033[0m, ID: " . $website->getId() . "\n";
}
$website = $storeManager->getWebsite('base'); // CHANGE 'base' if you want a different website
$websiteId = $website->getId();
$websiteCode = $website->getCode();
echo "Using website: \033[36m" . $website->getCode() . "\033[0m (ID: $websiteId)\n\n";

// Get a store view for this website
$stores = $website->getStores();
$store = reset($stores); // Get the first store for this website
$storeId = $store->getId();
$storeCode = $store->getCode();

foreach ($attributeMetadata as $attrMeta) {
    if ($attrMeta->isRequired()) {
        $attrCode = $attrMeta->getAttributeCode();
        // If already filled, skip
        if (isset($attributeValues[$attrCode])) {
            continue;
        }
        $label = strtolower($attrMeta->getFrontendLabel());
        // Assign simple test values based on label or code
        switch ($attrCode) {
            case 'prefix':
                $attributeValues['prefix'] = 'TestPrefix';
                break;
            case 'middlename':
                $attributeValues['middlename'] = 'TestMiddle';
                break;
            case 'suffix':
                $attributeValues['suffix'] = 'TestSuffix';
                break;
            case 'taxvat':
                $attributeValues['taxvat'] = '123456789';
                break;
            case 'dob':
                $attributeValues['dob'] = '1990-01-01';
                break;
            case 'gender':
                $attributeValues['gender'] = 1;
                break;
            case 'website':
            case 'website_id':
                $attributeValues[$attrCode] = $websiteId;
                break;
            case 'store_id':
                $attributeValues[$attrCode] = $storeId;
                break;
            case 'group_id':
                $attributeValues[$attrCode] = 1;
                break;
            default:
                // Fallback for any other required field
                $attributeValues[$attrCode] = 'Test' . ucfirst($attrCode);
        }
    }
}

try {
    $customerRepository = $obj->get(CustomerRepositoryInterface::class);
    $customerFactory = $obj->get(CustomerInterfaceFactory::class);

    try {
        $customer = $customerRepository->get($attributeValues['email'], $websiteId);
        echo "\033[33mCustomer with email {$attributeValues['email']} already exists. Not creating.\033[0m\n";
    } catch (NoSuchEntityException $e) {
        $customer = $customerFactory->create();
        $customer->setWebsiteId($websiteId)
            ->setStoreId($storeId)
            ->setEmail($attributeValues['email'])
            ->setFirstname($attributeValues['firstname'])
            ->setLastname($attributeValues['lastname']);

        // Set all other required attributes
        foreach ($attributeValues as $attrCode => $val) {
            if (in_array($attrCode, ['email', 'firstname', 'lastname', 'password'])) {
                continue;
            }
            $customer->setData($attrCode, $val);
        }

        $accountManagement = $obj->get(AccountManagementInterface::class);
        $customer = $accountManagement->createAccount($customer, $attributeValues['password'], null);
        echo "\033[32mTest customer created with all required attributes:\033[0m\n";
        echo "Email: {$attributeValues['email']}\n";
        echo "Password: {$attributeValues['password']}\n\n";

        // Clear cache after successful customer creation
        echo "\033[36mClearing Magento cache...\033[0m\n";
        exec('bin/magento c:f 2>&1', $output, $return_var);
        foreach ($output as $line) {
            echo "\033[36m$line\033[0m\n";
        }
        if ($return_var !== 0) {
            echo "\033[31mMagento cache flush failed with exit code $return_var\033[0m\n";
        } else {
            echo "\033[32mMagento cache flushed successfully.\033[0m\n";
        }
    }
} catch (\Exception $e) {
    // Print error, attempted website and store data, and all attribute values
    echo "\033[31mError creating customer: " . $e->getMessage() . "\033[0m\n";
    echo "Website code: {$websiteCode}, Website ID: {$websiteId}\n";
    echo "Store code: {$storeCode}, Store ID: {$storeId}\n";
    echo "Customer data:\n";
    foreach ($attributeValues as $k => $v) {
        echo "  {$k}: " . var_export($v, true) . "\n";
    }
    exit(1);
}
