<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$router = $app->getRouter();

require_once __DIR__ . '/../routes/api.php';

$app->run();
