<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Support;

use Bookmarks\Infrastructure\Persistence\ConnectionFactory;
use Bookmarks\Infrastructure\Persistence\Migrator;
use PDO;

final class Database
{
    public static function migratedInMemory(): PDO
    {
        $pdo = ConnectionFactory::sqlite(ConnectionFactory::IN_MEMORY);
        (new Migrator($pdo, Migrator::defaultDirectory()))->migrate();

        return $pdo;
    }
}
