<?php

/**
 * Front controller — the only web-reachable PHP file.
 *
 * Everything it does lives in src/Http/Application.php, so the request path can
 * be exercised without a web server.
 */

declare(strict_types=1);

use MediasIndex\Http\Application;
use MediasIndex\Http\Request;
use MediasIndex\Support\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

Application::create(Config::load())
    ->handle(Request::fromGlobals())
    ->send();
