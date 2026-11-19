<?php

declare(strict_types=1);

namespace Bookmarks\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public const string IN_MEMORY = ':memory:';

    private function __construct() {}

    /**
     * Path from the DATABASE_PATH environment variable, defaulting to var/bookmarks.sqlite.
     */
    public static function pathFromEnvironment(): string
    {
        $path = getenv('DATABASE_PATH');

        return is_string($path) && $path !== '' ? $path : dirname(__DIR__, 3) . '/var/bookmarks.sqlite';
    }

    public static function sqlite(string $path): PDO
    {
        if ($path !== self::IN_MEMORY) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Cannot create database directory "%s".', $directory));
            }
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($path !== self::IN_MEMORY) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $pdo;
    }
}
