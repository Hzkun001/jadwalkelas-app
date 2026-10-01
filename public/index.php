<?php

require_once __DIR__ . '/../vendor/autoload.php';

session_start();

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/services.php';
require_once __DIR__ . '/../app/config/routes.php';

Flight::start();
