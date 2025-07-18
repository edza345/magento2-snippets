<?php
// ANSI colors for CLI output
$green = "\033[32m";
$yellow = "\033[33m";
$blue = "\033[34m";
$cyan = "\033[36m";
$magenta = "\033[35m";
$red = "\033[31m";
$reset = "\033[0m";

// Connect to DB for admin user check
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();

$resource = $obj->get(ResourceConnection::class);
$connection = $resource->getConnection();

echo "{$magenta}Locked Admin Users (Navigate with ↑/↓, Space to select, Enter to unlock+enable):{$reset}\n\n";

// Fetch locked admin users (include active and inactive)
$lockedUsers = $connection->fetchAll("SELECT user_id, username, is_active, failures_num, lock_expires FROM admin_user WHERE lock_expires IS NOT NULL AND lock_expires > NOW()");

if (empty($lockedUsers)) {
    echo "{$green}No locked admin users found!{$reset}\n";
    exit(0);
}

// Prepare menu
$users = [];
foreach ($lockedUsers as $row) {
    $users[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'is_active' => $row['is_active'],
        'checked' => false,
        'lock_expires' => $row['lock_expires']
    ];
}

// Disable output buffering
system('stty cbreak -echo');

// Helper to render menu
function renderMenu($users, $active) {
    global $cyan, $green, $yellow, $reset, $red;
    echo "\033[2J\033[0;0H"; // Clear screen
    echo "Locked admin users (press Space to select/unselect, Enter to unlock+enable, q to quit):\n\n";
    foreach ($users as $i => $u) {
        $marker = ($u['checked'] ? "{$green}[x]{$reset}" : "[ ]");
        $arrow = ($i === $active ? "{$cyan}→{$reset}" : " ");
        $username = $u['username'];
        $expires = $u['lock_expires'];
        $status = ($u['is_active'] ? "{$green}active{$reset}" : "{$red}inactive{$reset}");
        echo " $arrow $marker $username {$yellow}(locked until $expires){$reset} [$status]\n";
    }
}

// Menu navigation loop
$active = 0;
renderMenu($users, $active);
while (true) {
    $c = fread(STDIN, 1);
    $code = ord($c);

    if ($code === 113) { // q
        echo "\nExit.\n";
        system('stty sane');
        exit(0);
    }

    if ($c === "\033") {
        $c2 = fread(STDIN, 1);
        if ($c2 === "[") {
            $c3 = fread(STDIN, 1);
            if ($c3 === "A") { // up
                $active = ($active - 1 + count($users)) % count($users);
                renderMenu($users, $active);
            }
            if ($c3 === "B") { // down
                $active = ($active + 1) % count($users);
                renderMenu($users, $active);
            }
        }
        continue;
    }
    if ($code === 32) { // space
        $users[$active]['checked'] = !$users[$active]['checked'];
        renderMenu($users, $active);
        continue;
    }
    if ($code === 10 || $code === 13) { // enter
        break;
    }
}

system('stty sane');

// Unlock and enable selected users
$unlockIds = [];
foreach ($users as $u) {
    if ($u['checked']) {
        $unlockIds[] = $u['user_id'];
    }
}

if (empty($unlockIds)) {
    echo "{$yellow}No users selected, nothing to unlock.{$reset}\n";
    exit(0);
}

$idsString = implode(', ', $unlockIds);
$sql = "UPDATE admin_user SET failures_num = 0, lock_expires = NULL, is_active = 1 WHERE user_id IN ($idsString)";
$connection->query($sql);

echo "{$green}Unlocked & enabled user(s):{$reset}\n";
foreach ($users as $u) {
    if ($u['checked']) {
        echo "  {$cyan}{$u['username']}{$reset}\n";
    }
}
