<?php

declare(strict_types=1);

/**
 * Spartan core messages (English).
 *
 * Override any line by creating lang/{locale}/spartan.php in the application:
 * the app's keys replace these, every key it leaves out falls back to here.
 * 'attributes' maps field names to display names used as :field in
 * validation messages, e.g. 'attributes' => ['email' => 'email address'].
 */
return [
    'validation' => [
        'required'    => 'The :field field is required.',
        'string'      => 'The :field field must be a string.',
        'integer'     => 'The :field field must be an integer.',
        'boolean'     => 'The :field field must be a boolean.',
        'numeric'     => 'The :field field must be numeric.',
        'email'       => 'The :field field must be a valid email address.',
        'url'         => 'The :field field must be a valid URL.',
        'date'        => 'The :field field must be a valid date (YYYY-MM-DD).',
        'alpha'       => 'The :field field may only contain letters.',
        'alpha_num'   => 'The :field field may only contain letters and numbers.',
        'confirmed'   => 'The :field confirmation does not match.',
        'min_numeric' => 'The :field must be at least :min.',
        'min_string'  => 'The :field must be at least :min characters.',
        'max_numeric' => 'The :field may not exceed :max.',
        'max_string'  => 'The :field may not exceed :max characters.',
        'in'          => 'The :field must be one of: :values.',
        'regex'       => 'The :field format is invalid.',
        'unique'      => 'The :field has already been taken.',
        'invalid'     => 'The given data was invalid.',
    ],

    'attributes' => [],

    'http' => [
        'unauthorized'       => 'Unauthorized.',
        'login_required'     => 'You must be logged in to access this page.',
        'forbidden'          => 'Forbidden.',
        'forbidden_page'     => 'You do not have the required permissions to access this page.',
        'action_unauthorized' => 'This action is unauthorized.',
        'not_found'          => 'The page you requested was not found.',
        'csrf'               => 'Invalid or expired CSRF token.',
        'csrf_page'          => 'Invalid or expired security token. Please reload the page and try again.',
        'too_many_requests'  => 'Too many requests. Please try again later.',
        'server_error'       => '500 Internal Server Error',
        'server_error_page'  => 'An unexpected error occurred.',
        'title_403'          => '403 Forbidden',
        'title_429'          => '429 Too Many Requests',
        'title_500'          => '500 Internal Server Error',
    ],
];
