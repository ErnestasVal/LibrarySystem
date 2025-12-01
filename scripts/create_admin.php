<?php
// CLI helper to create an admin user
// Usage:
//   php scripts/create_admin.php username password [FirstName] [LastName]

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/classes/User.php';

function prompt($message) {
    if (function_exists('readline')) {
        $line = readline($message);
    } else {
        echo $message;
        $line = fgets(STDIN);
    }
    return trim($line);
}

// Parse arguments
$argv0 = $argv[0] ?? 'create_admin.php';
$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
$vardas = $argv[3] ?? null;
$pavarde = $argv[4] ?? null;

if (!$username) {
    $username = prompt("Enter username: ");
}
if (!$password) {
    // For password, hide input if possible
    if (stripos(PHP_OS, 'WIN') === 0) {
        // Windows: no easy built-in hidden input, just prompt normally
        $password = prompt("Enter password: ");
    } else {
        // Attempt to use `shell_exec('stty -echo')` to hide input
        echo "Enter password: ";
        if (shell_exec('stty -echo') !== null) {
            $password = rtrim(fgets(STDIN), "\n");
            shell_exec('stty echo');
            echo "\n";
        } else {
            $password = rtrim(fgets(STDIN), "\n");
        }
    }
}
if (!$vardas) {
    $vardas = prompt("Enter first name (or leave blank for 'Admin'): ");
    if ($vardas === '') $vardas = 'Admin';
}
if (!$pavarde) {
    $pavarde = prompt("Enter last name (or leave blank for 'User'): ");
    if ($pavarde === '') $pavarde = 'User';
}

// Basic validation
if (strlen($username) < 3) {
    echo "Username must be at least 3 characters.\n";
    exit(2);
}
if (strlen($password) < 6) {
    echo "Password must be at least 6 characters.\n";
    exit(2);
}

// $db should be created by src/config.php
if (!isset($db) || !$db) {
    echo "Database connection not available. Check src/config.php.\n";
    exit(3);
}

// Use User class to register
$newUser = new User($db);
$newUser->username = $username;
if ($newUser->usernameExists()) {
    echo "Username '$username' already exists.\n";
    exit(4);
}

$newUser->password = $password;
$newUser->vardas = $vardas;
$newUser->pavarde = $pavarde;
$newUser->tipas = defined('USER_ADMIN') ? USER_ADMIN : 'Administratorius';

if ($newUser->register()) {
    echo "Admin user '$username' created successfully.\n";
    exit(0);
} else {
    echo "Failed to create admin user.\n";
    exit(5);
}
