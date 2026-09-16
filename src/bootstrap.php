<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Env.php';

Env::load(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/lib/Log.php';
require_once __DIR__ . '/lib/TelegramClient.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/PedidosClient.php';
require_once __DIR__ . '/lib/CatalogClient.php';
require_once __DIR__ . '/lib/OpenMeteoClient.php';
require_once __DIR__ . '/lib/LLMClient.php';
require_once __DIR__ . '/lib/Router.php';
require_once __DIR__ . '/lib/App.php';
