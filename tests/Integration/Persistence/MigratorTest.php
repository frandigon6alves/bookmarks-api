<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Integration\Persistence;

use Bookmarks\Infrastructure\Persistence\ConnectionFactory;
use Bookmarks\Infrastructure\Persistence\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testMigrationsRunOnceAndCreateTheSchema(): void
    {
        $pdo = ConnectionFactory::sqlite(ConnectionFactory::IN_MEMORY);
        $migrator = new Migrator($pdo, Migrator::defaultDirectory());

        self::assertSame(['0001_create_bookmarks'], $migrator->migrate());
        self::assertSame([], $migrator->migrate(), 'A second run is a no-op.');

        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        self::assertNotFalse($statement);
        self::assertSame(['bookmark_tags', 'bookmarks', 'schema_migrations', 'tags'], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testForeignKeysAreEnforced(): void
    {
        $pdo = ConnectionFactory::sqlite(ConnectionFactory::IN_MEMORY);
        $statement = $pdo->query('PRAGMA foreign_keys');
        self::assertNotFalse($statement);

        self::assertSame(1, $statement->fetchColumn());
    }
}
