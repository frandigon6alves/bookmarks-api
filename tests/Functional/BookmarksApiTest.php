<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Functional;

use Psr\Http\Message\ResponseInterface;

final class BookmarksApiTest extends ApiTestCase
{
    public function testHealth(): void
    {
        $response = $this->request('GET', '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok'], self::json($response));
    }

    public function testCreateReturnsTheBookmarkWithLocation(): void
    {
        $response = $this->request('POST', '/bookmarks', [
            'url' => 'https://www.rfc-editor.org/rfc/rfc9457',
            'title' => 'RFC 9457: Problem Details for HTTP APIs',
            'notes' => 'Error format used by this API.',
            'tags' => ['HTTP', 'standards'],
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('/bookmarks/1', $response->getHeaderLine('Location'));
        self::assertSame([
            'data' => [
                'id' => 1,
                'url' => 'https://www.rfc-editor.org/rfc/rfc9457',
                'title' => 'RFC 9457: Problem Details for HTTP APIs',
                'notes' => 'Error format used by this API.',
                'tags' => ['http', 'standards'],
                'created_at' => '2026-01-15T10:00:00+00:00',
                'updated_at' => '2026-01-15T10:00:00+00:00',
            ],
        ], self::json($response));
    }

    public function testShowReturnsAStoredBookmark(): void
    {
        $id = $this->createBookmark(['url' => 'https://www.slimframework.com', 'title' => 'Slim']);

        $response = $this->request('GET', '/bookmarks/' . $id);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Slim', self::json($response, 'data.title'));
        self::assertNull(self::json($response, 'data.notes'));
    }

    public function testPatchUpdatesOnlyTheGivenFields(): void
    {
        $id = $this->createBookmark(['url' => 'https://www.slimframework.com', 'title' => 'Slim', 'tags' => ['php']]);
        $this->clock->advance('+2 hours');

        $response = $this->request('PATCH', '/bookmarks/' . $id, ['title' => 'Slim Framework', 'tags' => ['php', 'framework']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Slim Framework', self::json($response, 'data.title'));
        self::assertSame('https://www.slimframework.com', self::json($response, 'data.url'));
        self::assertSame(['framework', 'php'], self::json($response, 'data.tags'));
        self::assertSame('2026-01-15T10:00:00+00:00', self::json($response, 'data.created_at'));
        self::assertSame('2026-01-15T12:00:00+00:00', self::json($response, 'data.updated_at'));
    }

    public function testDeleteRemovesTheBookmark(): void
    {
        $id = $this->createBookmark(['url' => 'https://example.com', 'title' => 'Example']);

        $response = $this->request('DELETE', '/bookmarks/' . $id);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());

        self::assertProblem($this->request('GET', '/bookmarks/' . $id), 404, '/problems/bookmark-not-found');
        self::assertProblem($this->request('DELETE', '/bookmarks/' . $id), 404, '/problems/bookmark-not-found');
    }

    public function testListFiltersByTagAndSearchText(): void
    {
        $this->createBookmark(['url' => 'https://www.php.net', 'title' => 'PHP manual', 'tags' => ['php', 'docs']]);
        $this->createBookmark(['url' => 'https://phpstan.org', 'title' => 'PHPStan', 'notes' => 'Static analysis', 'tags' => ['php', 'tooling']]);
        $this->createBookmark(['url' => 'https://developer.mozilla.org', 'title' => 'MDN Web Docs', 'tags' => ['docs']]);

        $byTag = $this->request('GET', '/bookmarks?tag=PHP');
        self::assertSame(200, $byTag->getStatusCode());
        self::assertSame(['PHPStan', 'PHP manual'], $this->titles($byTag));

        self::assertSame(['PHPStan'], $this->titles($this->request('GET', '/bookmarks?q=static+analysis')));
        self::assertSame(['MDN Web Docs'], $this->titles($this->request('GET', '/bookmarks?tag=docs&q=web')));
        self::assertSame([], $this->titles($this->request('GET', '/bookmarks?tag=unused')));
    }

    public function testListPaginatesWithCursors(): void
    {
        foreach (range(1, 5) as $i) {
            $this->createBookmark(['url' => "https://example.com/{$i}", 'title' => "Bookmark {$i}"]);
        }

        $seen = [];
        $uri = '/bookmarks?limit=2';
        $pages = 0;
        do {
            $response = $this->request('GET', $uri);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame(2, self::json($response, 'meta.limit'));
            $seen = [...$seen, ...$this->titles($response)];
            $cursor = self::json($response, 'meta.next_cursor');
            $uri = '/bookmarks?limit=2&cursor=' . (is_string($cursor) ? $cursor : '');
            ++$pages;
        } while ($cursor !== null && $pages < 10);

        self::assertSame(3, $pages);
        self::assertSame(['Bookmark 5', 'Bookmark 4', 'Bookmark 3', 'Bookmark 2', 'Bookmark 1'], $seen);
    }

    public function testTagsAreListedWithCounts(): void
    {
        $this->createBookmark(['url' => 'https://a.example', 'title' => 'A', 'tags' => ['php', 'web']]);
        $this->createBookmark(['url' => 'https://b.example', 'title' => 'B', 'tags' => ['php']]);
        $id = $this->createBookmark(['url' => 'https://c.example', 'title' => 'C', 'tags' => ['temporary']]);
        $this->request('DELETE', '/bookmarks/' . $id);

        $response = $this->request('GET', '/tags');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['data' => [['name' => 'php', 'count' => 2], ['name' => 'web', 'count' => 1]]], self::json($response));
    }

    /**
     * @return list<string>
     */
    private function titles(ResponseInterface $response): array
    {
        $items = self::json($response, 'data');
        self::assertIsArray($items);

        $titles = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            self::assertIsString($item['title'] ?? null);
            $titles[] = $item['title'];
        }

        return $titles;
    }
}
