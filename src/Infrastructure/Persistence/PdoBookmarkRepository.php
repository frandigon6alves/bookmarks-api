<?php

declare(strict_types=1);

namespace Bookmarks\Infrastructure\Persistence;

use Bookmarks\Domain\Bookmark;
use Bookmarks\Domain\BookmarkCriteria;
use Bookmarks\Domain\BookmarkData;
use Bookmarks\Domain\BookmarkPage;
use Bookmarks\Domain\BookmarkRepository;
use Bookmarks\Domain\TagCount;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use PDO;
use PDOStatement;
use Throwable;
use UnexpectedValueException;

final readonly class PdoBookmarkRepository implements BookmarkRepository
{
    private const string COLUMNS = 'b.id, b.url, b.title, b.notes, b.created_at, b.updated_at';

    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Bookmark
    {
        return $this->findOne('b.id = :value', $id);
    }

    public function findByUrl(string $url): ?Bookmark
    {
        return $this->findOne('b.url = :value', $url);
    }

    public function add(BookmarkData $data, DateTimeImmutable $now): Bookmark
    {
        return $this->transactional(function () use ($data, $now): Bookmark {
            $timestamp = $now->format(DateTimeInterface::ATOM);
            $this->pdo
                ->prepare('INSERT INTO bookmarks (url, title, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$data->url, $data->title, $data->notes, $timestamp, $timestamp]);
            $id = (int) $this->pdo->lastInsertId();
            $this->replaceTags($id, $data->tags);

            return $this->find($id) ?? throw new LogicException('Inserted bookmark vanished.');
        });
    }

    public function update(int $id, BookmarkData $data, DateTimeImmutable $now): Bookmark
    {
        return $this->transactional(function () use ($id, $data, $now): Bookmark {
            $this->pdo
                ->prepare('UPDATE bookmarks SET url = ?, title = ?, notes = ?, updated_at = ? WHERE id = ?')
                ->execute([$data->url, $data->title, $data->notes, $now->format(DateTimeInterface::ATOM), $id]);
            $this->replaceTags($id, $data->tags);
            $this->deleteUnusedTags();

            return $this->find($id) ?? throw new LogicException(sprintf('Bookmark %d does not exist.', $id));
        });
    }

    public function remove(int $id): bool
    {
        return $this->transactional(function () use ($id): bool {
            $statement = $this->pdo->prepare('DELETE FROM bookmarks WHERE id = ?');
            $statement->execute([$id]);
            $this->deleteUnusedTags();

            return $statement->rowCount() > 0;
        });
    }

    public function search(BookmarkCriteria $criteria): BookmarkPage
    {
        $conditions = [];
        $parameters = [];

        if ($criteria->beforeId !== null) {
            $conditions[] = 'b.id < :before_id';
            $parameters[':before_id'] = $criteria->beforeId;
        }
        if ($criteria->tag !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM bookmark_tags bt JOIN tags t ON t.id = bt.tag_id'
                . ' WHERE bt.bookmark_id = b.id AND t.name = :tag)';
            $parameters[':tag'] = $criteria->tag;
        }
        if ($criteria->search !== null) {
            $conditions[] = "(b.title LIKE :search ESCAPE '\\' OR b.url LIKE :search ESCAPE '\\'"
                . " OR COALESCE(b.notes, '') LIKE :search ESCAPE '\\')";
            $parameters[':search'] = '%' . addcslashes($criteria->search, '\\%_') . '%';
        }

        $sql = 'SELECT ' . self::COLUMNS . ' FROM bookmarks b'
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY b.id DESC LIMIT :limit';

        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        // Fetch one extra row to know whether another page exists.
        $statement->bindValue(':limit', $criteria->limit + 1, PDO::PARAM_INT);
        $statement->execute();

        $rows = $this->rows($statement);
        $hasMore = count($rows) > $criteria->limit;
        $bookmarks = $this->hydrate(array_slice($rows, 0, $criteria->limit));
        $last = $bookmarks === [] ? null : $bookmarks[array_key_last($bookmarks)];

        return new BookmarkPage($bookmarks, $criteria->limit, $hasMore && $last !== null ? $last->id : null);
    }

    public function tagCounts(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.name, COUNT(bt.bookmark_id) AS bookmark_count FROM tags t'
            . ' JOIN bookmark_tags bt ON bt.tag_id = t.id'
            . ' GROUP BY t.id, t.name ORDER BY bookmark_count DESC, t.name ASC',
        );
        $statement->execute();

        return array_map(
            static fn(array $row): TagCount => new TagCount(self::string($row, 'name'), self::int($row, 'bookmark_count')),
            $this->rows($statement),
        );
    }

    private function findOne(string $condition, int|string $value): ?Bookmark
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM bookmarks b WHERE ' . $condition);
        $statement->bindValue(':value', $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->execute();

        return $this->hydrate($this->rows($statement))[0] ?? null;
    }

    /**
     * @param list<string> $tags
     */
    private function replaceTags(int $bookmarkId, array $tags): void
    {
        $this->pdo->prepare('DELETE FROM bookmark_tags WHERE bookmark_id = ?')->execute([$bookmarkId]);

        $insertTag = $this->pdo->prepare('INSERT INTO tags (name) VALUES (?) ON CONFLICT (name) DO NOTHING');
        $link = $this->pdo->prepare(
            'INSERT INTO bookmark_tags (bookmark_id, tag_id) SELECT ?, id FROM tags WHERE name = ?',
        );
        foreach ($tags as $tag) {
            $insertTag->execute([$tag]);
            $link->execute([$bookmarkId, $tag]);
        }
    }

    private function deleteUnusedTags(): void
    {
        $this->pdo->exec('DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM bookmark_tags)');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Bookmark>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn(array $row): int => self::int($row, 'id'), $rows);
        $tagsById = array_fill_keys($ids, []);

        $statement = $this->pdo->prepare(
            'SELECT bt.bookmark_id, t.name FROM bookmark_tags bt JOIN tags t ON t.id = bt.tag_id'
            . ' WHERE bt.bookmark_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')'
            . ' ORDER BY t.name ASC',
        );
        $statement->execute($ids);
        foreach ($this->rows($statement) as $row) {
            $tagsById[self::int($row, 'bookmark_id')][] = self::string($row, 'name');
        }

        return array_map(
            static fn(array $row): Bookmark => new Bookmark(
                self::int($row, 'id'),
                self::string($row, 'url'),
                self::string($row, 'title'),
                self::nullableString($row, 'notes'),
                $tagsById[self::int($row, 'id')] ?? [],
                self::date($row, 'created_at'),
                self::date($row, 'updated_at'),
            ),
            $rows,
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function transactional(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Expected an associative row.');
            }
            $typed = [];
            foreach ($row as $column => $value) {
                $typed[(string) $column] = $value;
            }
            $rows[] = $typed;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(sprintf('Column "%s" is not an integer.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        return self::nullableString($row, $column)
            ?? throw new UnexpectedValueException(sprintf('Column "%s" is null.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new UnexpectedValueException(sprintf('Column "%s" is not a string.', $column));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function date(array $row, string $column): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, self::string($row, $column));

        return $date !== false ? $date : throw new UnexpectedValueException(sprintf('Column "%s" is not a date.', $column));
    }
}
