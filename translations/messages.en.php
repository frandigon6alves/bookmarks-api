<?php

declare(strict_types=1);

return [
    'validation.required' => 'This field is required.',
    'validation.blank' => 'This field must not be blank.',
    'validation.too_long' => 'This field must be at most %max% characters long.',
    'validation.type.string' => 'This field must be a string.',
    'validation.type.string_or_null' => 'This field must be a string or null.',
    'validation.type.list' => 'This field must be an array.',
    'validation.unknown_field' => 'This field is not recognized. Allowed fields: %fields%.',
    'validation.body.not_object' => 'The request body must be a JSON object.',
    'validation.url.invalid' => 'This field must be an absolute http or https URL.',
    'validation.tag.invalid' => 'Tags may only contain lowercase letters, digits and single hyphens, up to %max% characters.',
    'validation.tags.too_many' => 'A bookmark can have at most %max% tags.',
    'validation.limit.range' => 'The limit must be an integer between %min% and %max%.',
    'validation.cursor.invalid' => 'The cursor is not valid. Use the next_cursor value from a previous response.',

    'problem.validation.title' => 'Validation failed',
    'problem.validation.detail' => 'The request body contains invalid data. See "errors" for details.',
    'problem.invalid_query.title' => 'Invalid query parameters',
    'problem.invalid_query.detail' => 'One or more query parameters are invalid. See "errors" for details.',
    'problem.malformed_json.title' => 'Malformed JSON',
    'problem.malformed_json.detail' => 'The request body could not be parsed as JSON.',
    'problem.bookmark_not_found.title' => 'Bookmark not found',
    'problem.bookmark_not_found.detail' => 'There is no bookmark with id %id%.',
    'problem.duplicate_url.title' => 'Duplicate URL',
    'problem.duplicate_url.detail' => 'This URL is already saved as bookmark %id%.',
    'problem.not_found.title' => 'Not found',
    'problem.not_found.detail' => 'No resource exists at this path.',
    'problem.method_not_allowed.title' => 'Method not allowed',
    'problem.method_not_allowed.detail' => 'This resource only supports: %methods%.',
    'problem.http_error.title' => 'Request error',
    'problem.http_error.detail' => 'The request could not be processed.',
    'problem.internal.title' => 'Internal server error',
    'problem.internal.detail' => 'Something went wrong on our side. Please try again later.',
];
