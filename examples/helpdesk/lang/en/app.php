<?php

declare(strict_types=1);

return [
    'name'    => 'Spartan Helpdesk',
    'tagline' => 'Support tickets, done simply.',
    'footer'  => 'Spartan Helpdesk — an example application for the Spartan PHP framework.',

    'nav' => [
        'label'         => 'Main navigation',
        'dashboard'     => 'Dashboard',
        'tickets'       => 'Tickets',
        'new_ticket'    => 'New ticket',
        'notifications' => 'Notifications',
        'api_tokens'    => 'API tokens',
        'admin'         => 'Users',
        'login'         => 'Log in',
        'register'      => 'Register',
        'logout'        => 'Log out',
        'unread'        => ':count unread',
        'signed_in_as'  => 'Signed in as :name',
    ],

    'status' => [
        'open'     => 'Open',
        'pending'  => 'Pending',
        'resolved' => 'Resolved',
        'closed'   => 'Closed',
    ],

    'priority' => [
        'low'    => 'Low',
        'normal' => 'Normal',
        'high'   => 'High',
        'urgent' => 'Urgent',
    ],

    'role' => [
        'admin'    => 'Administrator',
        'agent'    => 'Support agent',
        'customer' => 'Customer',
        'none'     => 'No role',
    ],

    'pagination' => [
        'label'    => 'Pagination',
        'previous' => '← Previous',
        'next'     => 'Next →',
        'summary'  => 'Page :page of :last · :total results',
    ],

    'errors' => [
        'home'       => 'Back to the dashboard',
        'not_found'  => 'The page or record you asked for does not exist.',
        'forbidden'  => 'You do not have permission to do that.',
        'server'     => 'Something went wrong on our side. Please try again.',
        '403_title'  => 'Access denied',
        '404_title'  => 'Not found',
        '500_title'  => 'Server error',
    ],

    'unassigned' => 'Unassigned',
    'nobody'     => 'Nobody',
    'save'       => 'Save',
    'cancel'     => 'Cancel',
    'search'     => 'Search',
    'clear'      => 'Clear',
];
