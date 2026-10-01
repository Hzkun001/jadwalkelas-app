<?php

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

require_once __DIR__ . '/config.php';

// Register PDO database service to Flight via factory map
Flight::map('db', function() {
    static $db = null;
    if ($db === null) {
        $db = getDbConnection();
        initDatabase($db);
    }
    return $db;
});

// Configure Twig view renderer
$viewsPath = __DIR__ . '/../views';
if (!is_dir($viewsPath)) {
    mkdir($viewsPath, 0755, true);
}

$loader = new FilesystemLoader($viewsPath);
$twig = new Environment($loader, [
    'cache' => false,
    'debug' => true,
]);

// Add session helper to Twig
$twig->addGlobal('session', $_SESSION ?? []);

Flight::map('view', function() use ($twig) {
    return $twig;
});
