<?php

declare(strict_types=1);

namespace Bookmarks\Application\Validation;

use Bookmarks\Domain\BookmarkData;
use Bookmarks\Domain\TagName;
use stdClass;

/**
 * Validates and normalizes a decoded JSON body (objects decoded as stdClass).
 */
final class BookmarkValidator
{
    public const int URL_MAX_LENGTH = 2048;
    public const int TITLE_MAX_LENGTH = 200;
    public const int NOTES_MAX_LENGTH = 5000;
    public const int MAX_TAGS = 10;

    private const array FIELDS = ['url', 'title', 'notes', 'tags'];
    private const array REQUIRED = ['url', 'title'];

    /**
     * @throws ValidationFailed
     */
    public function validateNew(mixed $input): BookmarkData
    {
        $fields = $this->fields($input);
        $violations = [];

        foreach (self::REQUIRED as $name) {
            if (!array_key_exists($name, $fields)) {
                $violations[] = new Violation($name, 'validation.required');
            }
        }

        return $this->apply($fields, new BookmarkData('', '', null, []), $violations);
    }

    /**
     * Validates a partial update: only the fields present in the input are changed.
     *
     * @throws ValidationFailed
     */
    public function validateChanges(mixed $input, BookmarkData $current): BookmarkData
    {
        return $this->apply($this->fields($input), $current, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(mixed $input): array
    {
        if (!$input instanceof stdClass) {
            throw ValidationFailed::inBody([new Violation(null, 'validation.body.not_object')]);
        }

        $fields = [];
        foreach (get_object_vars($input) as $name => $value) {
            $fields[(string) $name] = $value;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<Violation> $violations
     */
    private function apply(array $fields, BookmarkData $base, array $violations): BookmarkData
    {
        $url = $base->url;
        $title = $base->title;
        $notes = $base->notes;
        $tags = $base->tags;

        foreach ($fields as $name => $value) {
            switch ($name) {
                case 'url':
                    $url = $this->url($value, $violations);
                    break;
                case 'title':
                    $title = $this->title($value, $violations);
                    break;
                case 'notes':
                    $notes = $this->notes($value, $violations);
                    break;
                case 'tags':
                    $tags = $this->tags($value, $violations);
                    break;
                default:
                    $violations[] = new Violation($name, 'validation.unknown_field', ['fields' => implode(', ', self::FIELDS)]);
            }
        }

        if ($violations !== []) {
            throw ValidationFailed::inBody($violations);
        }

        return new BookmarkData($url, $title, $notes, $tags);
    }

    /**
     * @param list<Violation> $violations
     *
     * @param-out list<Violation> $violations
     */
    private function url(mixed $value, array &$violations): string
    {
        $url = $this->requiredString('url', $value, self::URL_MAX_LENGTH, $violations);
        if ($url === null) {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
        ) {
            $violations[] = new Violation('url', 'validation.url.invalid');
        }

        return $url;
    }

    /**
     * @param list<Violation> $violations
     *
     * @param-out list<Violation> $violations
     */
    private function title(mixed $value, array &$violations): string
    {
        return $this->requiredString('title', $value, self::TITLE_MAX_LENGTH, $violations) ?? '';
    }

    /**
     * @param list<Violation> $violations
     *
     * @param-out list<Violation> $violations
     */
    private function notes(mixed $value, array &$violations): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            $violations[] = new Violation('notes', 'validation.type.string_or_null');

            return null;
        }

        $notes = trim($value);
        if (mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
            $violations[] = new Violation('notes', 'validation.too_long', ['max' => self::NOTES_MAX_LENGTH]);
        }

        return $notes === '' ? null : $notes;
    }

    /**
     * @param list<Violation> $violations
     *
     * @param-out list<Violation> $violations
     *
     * @return list<string>
     */
    private function tags(mixed $value, array &$violations): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            $violations[] = new Violation('tags', 'validation.type.list');

            return [];
        }

        $tags = [];
        foreach ($value as $index => $raw) {
            $field = sprintf('tags[%d]', $index);
            if (!is_string($raw)) {
                $violations[] = new Violation($field, 'validation.type.string');
                continue;
            }

            $tag = TagName::normalize($raw);
            if (!TagName::isValid($tag)) {
                $violations[] = new Violation($field, 'validation.tag.invalid', ['max' => TagName::MAX_LENGTH]);
                continue;
            }

            $tags[$tag] = $tag;
        }

        if (count($tags) > self::MAX_TAGS) {
            $violations[] = new Violation('tags', 'validation.tags.too_many', ['max' => self::MAX_TAGS]);
        }

        sort($tags);

        return $tags;
    }

    /**
     * @param list<Violation> $violations
     *
     * @param-out list<Violation> $violations
     */
    private function requiredString(string $field, mixed $value, int $maxLength, array &$violations): ?string
    {
        if (!is_string($value)) {
            $violations[] = new Violation($field, 'validation.type.string');

            return null;
        }

        $string = trim($value);
        if ($string === '') {
            $violations[] = new Violation($field, 'validation.blank');

            return null;
        }
        if (mb_strlen($string) > $maxLength) {
            $violations[] = new Violation($field, 'validation.too_long', ['max' => $maxLength]);

            return null;
        }

        return $string;
    }
}
