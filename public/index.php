<?php

declare(strict_types=1);

use Bookmarks\Http\ApplicationFactory;
use Bookmarks\Infrastructure\Persistence\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL);

ApplicationFactory::create(ConnectionFactory::sqlite(ConnectionFactory::pathFromEnvironment()), debug: $debug)->run();
