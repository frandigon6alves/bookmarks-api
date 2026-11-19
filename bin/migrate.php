<?php

declare(strict_types=1);

use Bookmarks\Infrastructure\Persistence\ConnectionFactory;
use Bookmarks\Infrastructure\Persistence\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = ConnectionFactory::pathFromEnvironment();
$applied = (new Migrator(ConnectionFactory::sqlite($path), Migrator::defaultDirectory()))->migrate();

echo $applied === []
    ? sprintf("Database %s is up to date.\n", $path)
    : sprintf("Applied to %s: %s\n", $path, implode(', ', $applied));
