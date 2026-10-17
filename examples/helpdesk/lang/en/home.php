<?php

declare(strict_types=1);

return [
    'title'    => 'Welcome',
    'heading'  => 'A helpdesk built on Spartan',
    'lead'     => 'Customers open tickets, agents answer them, admins manage the team. A small, complete application that uses most of the framework.',
    'cta'      => 'Log in to try it',
    'features' => [
        'auth'     => 'Registration, login with rate limiting, roles and permissions',
        'policies' => 'A ticket policy decides who may view, reply and manage',
        'uploads'  => 'Attachments checked with finfo, stored outside the web root',
        'events'   => 'Activity log (sync events) and notifications (queued jobs)',
        'cache'    => 'Dashboard numbers cached with tags, flushed on every change',
        'api'      => 'A JSON API with personal bearer tokens',
    ],
];
