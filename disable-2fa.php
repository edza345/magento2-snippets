<?php
// ANSI color codes
$green = "\033[32m";
$yellow = "\033[33m";
$red = "\033[31m";
$cyan = "\033[36m";
$reset = "\033[0m";

echo "{$cyan}Reading app/etc/env.php...{$reset}\n";
$env = include 'app/etc/env.php';
$db = $env['db']['connection']['default'];

$dsn = sprintf('mysql:host=%s;dbname=%s', $db['host'], $db['dbname']);
$user = $db['username'];
$pass = $db['password'];

try {
    echo "{$cyan}Connecting to database {$db['dbname']} on host {$db['host']}...{$reset}\n";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT config_id, value FROM core_config_data WHERE scope = 'default' AND scope_id = 0 AND path = 'twofactorauth/general/enable'";
    echo "{$cyan}Checking current 2FA status...{$reset}\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $runMagentoCacheFlush = false;

    if ($row === false) {
        echo "{$yellow}2FA config not found. Adding it as disabled (value=0)...{$reset}\n";
        $insert = "INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'twofactorauth/general/enable', 0)";
        $pdo->exec($insert);
        echo "{$green}2FA has been disabled (new config added).{$reset}\n";
        $runMagentoCacheFlush = true;
    } else {
        $current = $row['value'];
        echo "{$yellow}Current 2FA value: $current{$reset}\n";
        if ($current == 0) {
            echo "{$green}2FA is already disabled. No action taken.{$reset}\n";
        } else {
            echo "{$yellow}2FA is enabled. Disabling...{$reset}\n";
            $update = "UPDATE core_config_data SET value = 0 WHERE config_id = " . (int)$row['config_id'];
            $pdo->exec($update);
            echo "{$green}2FA has been disabled (value updated).{$reset}\n";
            $runMagentoCacheFlush = true;
        }
    }

    // Run Magento cache flush if changes were made
    if ($runMagentoCacheFlush) {
        echo "{$cyan}Running: bin/magento c:f{$reset}\n";
        exec('bin/magento c:f 2>&1', $output, $return_var);
        foreach ($output as $line) {
            echo "{$cyan}$line{$reset}\n";
        }
        if ($return_var !== 0) {
            echo "{$red}Magento command failed with exit code $return_var{$reset}\n";
            exit($return_var);
        } else {
            echo "{$green}Magento cache flushed successfully.{$reset}\n";
        }
    } else {
        echo "{$yellow}No Magento cache flush needed.{$reset}\n";
    }

} catch (Exception $e) {
    echo "{$red}ERROR: {$e->getMessage()}{$reset}\n";
    exit(1);
}