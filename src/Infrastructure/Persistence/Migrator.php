<?php

declare(strict_types=1);

namespace Bookmarks\Infrastructure\Persistence;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Applies the numbered *.sql files in migrations/ once each, in order,
 * recording applied versions in a schema_migrations table.
 */
final readonly class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $directory,
    ) {}

    public static function defaultDirectory(): string
    {
        return dirname(__DIR__, 3) . '/migrations';
    }

    /**
     * @return list<string> versions applied by this run
     */
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

        $applied = [];
        $statement = $this->pdo->query('SELECT version FROM schema_migrations');
        if ($statement !== false) {
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $version) {
                if (is_string($version)) {
                    $applied[$version] = true;
                }
            }
        }

        $files = glob($this->directory . '/*.sql');
        if ($files === false || $files === []) {
            throw new RuntimeException(sprintf('No migrations found in "%s".', $this->directory));
        }
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException(sprintf('Cannot read migration "%s".', $file));
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $this->pdo
                    ->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                    ->execute([$version, gmdate('Y-m-d\TH:i:s\Z')]);
                $this->pdo->commit();
            } catch (Throwable $exception) {
                $this->pdo->rollBack();

                throw $exception;
            }

            $ran[] = $version;
        }

        return $ran;
    }
}
