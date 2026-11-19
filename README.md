# bookmarks-api

[![CI](../../actions/workflows/ci.yml/badge.svg)](../../actions/workflows/ci.yml)

A small REST JSON API to save and organize bookmarks. It does the usual CRUD, plus tag filtering,
full-text search and cursor pagination. Every error comes back as
[RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) `application/problem+json`, translated into
**English**, **Spanish** or **Brazilian Portuguese** based on the `Accept-Language` header.

It is deliberately small, but built the way I would build a production service: a thin layered
design, strict types everywhere, PHPStan at level `max`, and tests that run the real HTTP stack
against an in-memory database.

**Stack:** PHP 8.4 · Slim 4 · PDO + SQLite · symfony/translation · PHPUnit 12 · PHPStan 2 · PHP-CS-Fixer (PER-CS) · Docker

## Endpoints

| Method   | Path              | Description                                                     | Success |
|----------|-------------------|-----------------------------------------------------------------|---------|
| `GET`    | `/health`         | Liveness check (also pings the database)                        | `200`   |
| `GET`    | `/bookmarks`      | List bookmarks, newest first. Query: `tag`, `q`, `limit`, `cursor` | `200` |
| `POST`   | `/bookmarks`      | Create a bookmark                                               | `201` + `Location` |
| `GET`    | `/bookmarks/{id}` | Fetch one bookmark                                              | `200`   |
| `PATCH`  | `/bookmarks/{id}` | Partial update; only the fields you send are changed            | `200`   |
| `DELETE` | `/bookmarks/{id}` | Delete a bookmark                                               | `204`   |
| `GET`    | `/tags`           | Tags in use with bookmark counts, most used first               | `200`   |

### Bookmark fields

| Field   | Type               | Rules                                                                  |
|---------|--------------------|------------------------------------------------------------------------|
| `url`   | string, required   | Absolute `http`/`https` URL, max 2048 chars, unique                    |
| `title` | string, required   | Not blank, max 200 characters                                          |
| `notes` | string or `null`   | Max 5000 characters; blank becomes `null`                              |
| `tags`  | array of strings   | Max 10; lowercased, deduplicated and sorted; `a-z`, `0-9` and single hyphens, max 30 chars each |

Unknown fields are rejected rather than silently ignored.

### Listing parameters

| Parameter | Description                                                                    |
|-----------|--------------------------------------------------------------------------------|
| `tag`     | Only bookmarks with this tag (case-insensitive)                                |
| `q`       | Case-insensitive text search in title, URL and notes (max 200 chars). `%` and `_` are matched literally |
| `limit`   | Page size, `1`–`100`, default `20`                                             |
| `cursor`  | Opaque value from `meta.next_cursor` of the previous page                      |

Pagination is keyset-based (`id < last seen id`), so pages stay stable while new bookmarks are
added. `meta.next_cursor` is `null` on the last page.

### Error types

| `type`                         | Status | When                                                 |
|--------------------------------|--------|------------------------------------------------------|
| `/problems/validation-error`   | 422    | The JSON body failed validation (see `errors`)       |
| `/problems/invalid-query`      | 400    | A query parameter is invalid (see `errors`)          |
| `/problems/malformed-json`     | 400    | The body is not valid JSON                           |
| `/problems/bookmark-not-found` | 404    | No bookmark with that id                             |
| `/problems/duplicate-url`      | 409    | The URL is already saved; `existing_id` points to it |
| `about:blank`                  | 404 / 405 / 500 | Unknown route, wrong method (with an `Allow` header), unexpected error |

Each entry in `errors` has a `field` (omitted when the whole body is wrong), a stable,
language-independent `code` for clients to branch on, and a translated `message` for humans.

## Examples

These requests and responses were captured from the API running with `docker compose up`.
Bodies are pretty-printed with `jq`; the `Host`, `Date` and `Connection` headers are left out.

Create a bookmark:

```console
$ curl -i -X POST localhost:8080/bookmarks \
    -H 'Content-Type: application/json' \
    -d '{"url": "https://www.rfc-editor.org/rfc/rfc9457", "title": "RFC 9457: Problem Details for HTTP APIs", "notes": "The error format this API uses.", "tags": ["http", "Standards"]}'
HTTP/1.1 201 Created
Content-Type: application/json
Location: /bookmarks/1
Content-Language: en
Vary: Accept-Language

{
  "data": {
    "id": 1,
    "url": "https://www.rfc-editor.org/rfc/rfc9457",
    "title": "RFC 9457: Problem Details for HTTP APIs",
    "notes": "The error format this API uses.",
    "tags": ["http", "standards"],
    "created_at": "2026-11-19T09:45:48+00:00",
    "updated_at": "2026-11-19T09:45:48+00:00"
  }
}
```

After adding two more bookmarks tagged `php`, list them one per page:

```console
$ curl 'localhost:8080/bookmarks?tag=php&limit=1'
{
  "data": [
    {
      "id": 3,
      "url": "https://phpstan.org/user-guide/rule-levels",
      "title": "PHPStan rule levels",
      "notes": null,
      "tags": ["php", "tooling"],
      "created_at": "2026-11-19T09:45:48+00:00",
      "updated_at": "2026-11-19T09:45:48+00:00"
    }
  ],
  "meta": {
    "limit": 1,
    "next_cursor": "YmVmb3JlOjM"
  }
}

$ curl 'localhost:8080/bookmarks?tag=php&limit=1&cursor=YmVmb3JlOjM'   # next page

$ curl localhost:8080/tags
{
  "data": [
    { "name": "php", "count": 2 },
    { "name": "framework", "count": 1 },
    { "name": "http", "count": 1 },
    { "name": "standards", "count": 1 },
    { "name": "tooling", "count": 1 }
  ]
}
```

A validation error in English (the default):

```console
$ curl -i -X POST localhost:8080/bookmarks \
    -H 'Content-Type: application/json' \
    -d '{"url": "ftp://example.com/file", "tags": ["php", "Not OK!"]}'
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json
Content-Language: en
Vary: Accept-Language

{
  "type": "/problems/validation-error",
  "title": "Validation failed",
  "status": 422,
  "detail": "The request body contains invalid data. See \"errors\" for details.",
  "instance": "/bookmarks",
  "errors": [
    { "field": "title", "code": "required", "message": "This field is required." },
    { "field": "url", "code": "url.invalid", "message": "This field must be an absolute http or https URL." },
    {
      "field": "tags[1]",
      "code": "tag.invalid",
      "message": "Tags may only contain lowercase letters, digits and single hyphens, up to 30 characters."
    }
  ]
}
```

The same request with `Accept-Language: es`:

```console
$ curl -i -X POST localhost:8080/bookmarks \
    -H 'Content-Type: application/json' \
    -H 'Accept-Language: es' \
    -d '{"url": "ftp://example.com/file", "tags": ["php", "Not OK!"]}'
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json
Content-Language: es
Vary: Accept-Language

{
  "type": "/problems/validation-error",
  "title": "La validación ha fallado",
  "status": 422,
  "detail": "El cuerpo de la petición contiene datos no válidos. Consulta \"errors\" para más detalles.",
  "instance": "/bookmarks",
  "errors": [
    { "field": "title", "code": "required", "message": "Este campo es obligatorio." },
    { "field": "url", "code": "url.invalid", "message": "Este campo debe ser una URL absoluta http o https." },
    {
      "field": "tags[1]",
      "code": "tag.invalid",
      "message": "Las etiquetas solo pueden contener letras minúsculas, dígitos y guiones simples, hasta 30 caracteres."
    }
  ]
}
```

## Architecture

```
HTTP handlers  ──►  application service  ──►  repository interface  ◄──  PDO implementation
(Slim, PSR-7)       (validation, rules)       (domain)                   (SQLite)
```

```
src/
├── Domain/            Bookmark, BookmarkData, criteria/page value objects, TagName rules,
│                      and the BookmarkRepository interface. No framework code.
├── Application/       BookmarkService (use cases), BookmarkValidator and ListQueryParser,
│                      opaque Cursor, domain exceptions. Knows nothing about HTTP or languages.
├── Infrastructure/    PdoBookmarkRepository, ConnectionFactory, Migrator, SystemClock.
├── I18n/              Supported locales, Accept-Language negotiation, translator factory.
└── Http/              One invokable class per endpoint, LocaleMiddleware, the problem+json
                       factory and Slim error handler, and ApplicationFactory (the wiring).
migrations/            Numbered SQL files, applied once each by bin/migrate.php.
translations/          messages.<locale>.php catalogues.
```

A few decisions worth calling out:

- **Validation produces message keys, not messages.** The application layer reports a
  `Violation(field, key, parameters)`; only the HTTP layer translates it, using the locale of the
  current request. Services stay testable without a translator.
- **Bodies are decoded with objects as `stdClass`**, so `{}` and `[]` are distinguishable and a JSON
  array body gets a clear "must be an object" error instead of being treated as empty input.
- **No DI container.** `ApplicationFactory` wires the small object graph by hand, which keeps the
  dependencies explicit and lets tests build the app with an in-memory database and a fixed clock.
- **Keyset pagination.** The repository fetches `limit + 1` rows to know whether another page exists,
  and tags for a whole page are loaded in a single query.
- **Tags are normalized tables** (`tags`, `bookmark_tags`) with `ON DELETE CASCADE`; tags that are no
  longer used are removed, so `/tags` only lists live tags.

## Internationalization

Supported locales are `en` (default), `es` and `pt_BR`.

`LocaleMiddleware` picks a locale from `Accept-Language` (quality values respected; ties keep
header order). A range first matches a locale exactly, then by primary language, so `es-MX`
gets Spanish and `pt` or `pt-PT` gets Brazilian Portuguese. `*` or anything unsupported falls back
to English. The chosen locale is sent back as `Content-Language` (`en`, `es`, `pt-BR`) on every
response, along with `Vary: Accept-Language`. It runs outside Slim's error middleware, so error
responses are translated too.

### Adding a language

1. Copy `translations/messages.en.php` to `translations/messages.<locale>.php`
   (for example `messages.fr.php`) and translate every value. Keep the `%placeholder%` tokens.
2. Add the locale to `Locales::SUPPORTED` in `src/I18n/Locales.php`.
3. Run `composer test`. `TranslationCatalogueTest` fails if the new file is missing a key, has an
   extra key, changes a placeholder, or leaves a message empty or identical to English.

## Running with Docker

You only need Docker; PHP and Composer run inside the container.

```console
docker compose up --build        # migrates var/bookmarks.sqlite, serves http://localhost:8080
curl localhost:8080/health       # {"status":"ok"}
docker compose down
```

The compose file mounts the source into the container and keeps `vendor/` in a named volume.
The SQLite database lives in `var/` on the host. Set `APP_DEBUG=1` to include exception details in
500 responses while developing.

## Development

All commands run inside the container:

```console
docker compose run --rm app composer test      # PHPUnit: unit, integration and functional suites
docker compose run --rm app composer analyse   # PHPStan, level max (src, tests, bin, public, translations)
docker compose run --rm app composer cs        # PHP-CS-Fixer in check mode (@PER-CS + strict rules)
docker compose run --rm app composer cs:fix    # apply coding style fixes
docker compose run --rm app composer check     # cs + analyse + test, as run in CI
docker compose run --rm app composer migrate   # apply pending migrations
```

If `composer.lock` changes, refresh the `vendor` volume with
`docker compose run --rm app composer install`.

The test suites:

- **Unit:** validator, query parser, cursor, service (against an in-memory repository), locale
  negotiation, problem details mapping, and the translation catalogue checks.
- **Integration:** `PdoBookmarkRepository` and the migrator against in-memory SQLite.
- **Functional:** requests dispatched in-process through the full Slim app (routing, middleware,
  error handler) on a fresh in-memory database, including Spanish and Portuguese error responses.

CI (`.github/workflows/ci.yml`) runs `composer check` on PHP 8.4 for every push to `main` and every
pull request.

## License

[MIT](LICENSE) © 2026 Franciellen Gonçalves
