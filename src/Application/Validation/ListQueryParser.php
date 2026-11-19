<?php

declare(strict_types=1);

namespace Bookmarks\Application\Validation;

use Bookmarks\Application\Cursor;
use Bookmarks\Domain\BookmarkCriteria;
use Bookmarks\Domain\TagName;

/**
 * Turns raw query parameters (?tag=&q=&limit=&cursor=) into listing criteria.
 */
final class ListQueryParser
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAX_LIMIT = 100;
    public const int SEARCH_MAX_LENGTH = 200;

    /**
     * @param array<mixed> $query
     *
     * @throws ValidationFailed
     */
    public function parse(array $query): BookmarkCriteria
    {
        $violations = [];
        $limit = self::DEFAULT_LIMIT;
        $tag = null;
        $search = null;
        $beforeId = null;

        if (array_key_exists('limit', $query)) {
            $raw = $query['limit'];
            if (is_string($raw) && preg_match('/^[0-9]{1,3}$/', $raw) === 1 && (int) $raw >= 1 && (int) $raw <= self::MAX_LIMIT) {
                $limit = (int) $raw;
            } else {
                $violations[] = new Violation('limit', 'validation.limit.range', ['min' => 1, 'max' => self::MAX_LIMIT]);
            }
        }

        if (array_key_exists('tag', $query)) {
            $raw = $query['tag'];
            $normalized = is_string($raw) ? TagName::normalize($raw) : null;
            if ($normalized === null || !TagName::isValid($normalized)) {
                $violations[] = new Violation('tag', 'validation.tag.invalid', ['max' => TagName::MAX_LENGTH]);
            } else {
                $tag = $normalized;
            }
        }

        if (array_key_exists('q', $query)) {
            $raw = $query['q'];
            if (!is_string($raw)) {
                $violations[] = new Violation('q', 'validation.type.string');
            } elseif (mb_strlen(trim($raw)) > self::SEARCH_MAX_LENGTH) {
                $violations[] = new Violation('q', 'validation.too_long', ['max' => self::SEARCH_MAX_LENGTH]);
            } elseif (trim($raw) !== '') {
                $search = trim($raw);
            }
        }

        if (array_key_exists('cursor', $query)) {
            $raw = $query['cursor'];
            $beforeId = is_string($raw) ? Cursor::decode($raw) : null;
            if ($beforeId === null) {
                $violations[] = new Violation('cursor', 'validation.cursor.invalid');
            }
        }

        if ($violations !== []) {
            throw ValidationFailed::inQuery($violations);
        }

        return new BookmarkCriteria($limit, $tag, $search, $beforeId);
    }
}
