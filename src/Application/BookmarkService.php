<?php

declare(strict_types=1);

namespace Bookmarks\Application;

use Bookmarks\Application\Exception\BookmarkNotFound;
use Bookmarks\Application\Exception\DuplicateUrl;
use Bookmarks\Application\Validation\BookmarkValidator;
use Bookmarks\Application\Validation\ListQueryParser;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Domain\Bookmark;
use Bookmarks\Domain\BookmarkPage;
use Bookmarks\Domain\BookmarkRepository;
use Bookmarks\Domain\TagCount;

/**
 * Use cases for bookmarks. Accepts raw decoded input, validates it and talks
 * to the repository; knows nothing about HTTP or translations.
 */
final readonly class BookmarkService
{
    public function __construct(
        private BookmarkRepository $repository,
        private BookmarkValidator $validator,
        private ListQueryParser $queryParser,
        private Clock $clock,
    ) {}

    /**
     * @param array<mixed> $query
     *
     * @throws ValidationFailed
     */
    public function list(array $query): BookmarkPage
    {
        return $this->repository->search($this->queryParser->parse($query));
    }

    /**
     * @throws BookmarkNotFound
     */
    public function get(int $id): Bookmark
    {
        return $this->repository->find($id) ?? throw new BookmarkNotFound($id);
    }

    /**
     * @throws ValidationFailed
     * @throws DuplicateUrl
     */
    public function create(mixed $input): Bookmark
    {
        $data = $this->validator->validateNew($input);
        $this->assertUrlIsFree($data->url);

        return $this->repository->add($data, $this->clock->now());
    }

    /**
     * Partial update: fields missing from the input keep their current value.
     *
     * @throws BookmarkNotFound
     * @throws ValidationFailed
     * @throws DuplicateUrl
     */
    public function update(int $id, mixed $input): Bookmark
    {
        $current = $this->get($id);
        $data = $this->validator->validateChanges($input, $current->data());

        if ($data->equals($current->data())) {
            return $current;
        }
        if ($data->url !== $current->url) {
            $this->assertUrlIsFree($data->url);
        }

        return $this->repository->update($id, $data, $this->clock->now());
    }

    /**
     * @throws BookmarkNotFound
     */
    public function delete(int $id): void
    {
        if (!$this->repository->remove($id)) {
            throw new BookmarkNotFound($id);
        }
    }

    /**
     * @return list<TagCount>
     */
    public function tags(): array
    {
        return $this->repository->tagCounts();
    }

    private function assertUrlIsFree(string $url): void
    {
        $existing = $this->repository->findByUrl($url);
        if ($existing !== null) {
            throw new DuplicateUrl($url, $existing->id);
        }
    }
}
