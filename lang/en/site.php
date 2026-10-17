<?php

declare(strict_types=1);

/**
 * English strings for the demo site pages (home, contact, profile, errors).
 */
return [
    'brand'  => 'Spartan',
    'footer' => '© :year Spartan. Built for AI IDE integration.',

    'nav' => [
        'label'   => 'Main navigation',
        'home'    => 'Home',
        'contact' => 'Contact',
        'profile' => 'Sample Profile',
    ],

    'home' => [
        'title'          => 'Home',
        'heading'        => 'System Booted',
        'badge'          => 'MVC Skills Active',
        'lead'           => 'Welcome to your clean Spartan MVC boilerplate. The workspace is ready for development, and the AGENTS.md guide lets your AI IDE build features that follow this architecture.',
        'router_title'   => 'Router Mapping',
        'router_body'    => 'Map GET and POST request patterns to closures or controller actions in routes/web.php.',
        'mvc_title'      => 'Controllers & Views',
        'mvc_body'       => 'Extend Spartan\\Controller to validate input, render Blade views and return JSON responses.',
        'db_title'       => 'Database Status',
        'db_status'      => 'Status:',
        'db_connected'   => 'Connected',
        'db_missing'     => 'Not connected (check your .env settings)',
        'db_error'       => 'Connection error (see the application log)',
        'users_found'    => 'Users found in database:',
        'routes_title'   => 'Registered Route Examples',
        'routes_body'    => 'Try these demo routes configured in the router:',
        'try_profile'    => 'Test Dynamic Route (/profile/admin)',
        'try_contact'    => 'Test Form Handler (/contact)',
    ],

    'contact' => [
        'title'               => 'Contact Us',
        'lead'                => 'Submit this form to see how the router handles POST requests and how a FormRequest validates the input.',
        'name'                => 'Full Name',
        'name_placeholder'    => 'e.g. Jane Doe',
        'email'               => 'Email Address',
        'email_placeholder'   => 'e.g. jane@example.com',
        'message'             => 'Message',
        'message_placeholder' => 'Type your message here...',
        'submit'              => 'Send Message',
        'cancel'              => 'Cancel',
        'sent'                => 'Thank you, :name! Your message has been received.',
        'invalid'             => 'Please correct the highlighted fields.',
    ],

    'profile' => [
        'title'    => 'User Profile',
        'heading'  => 'Profile View',
        'lead'     => 'This page is loaded from a route parameter. Change the identifier in the address bar and the value below follows it.',
        'capture'  => 'URL route variable capture',
        'user_id'  => 'User ID:',
        'back'     => '← Back Home',
        'another'  => 'Load Another User Profile',
    ],

    'errors' => [
        'home'      => 'Return Home',
        '403_title' => 'Access Denied',
        '403_body'  => 'You do not have permission to perform this action.',
        '404_title' => 'Page Not Found',
        '404_body'  => 'The page you are looking for does not exist.',
        '500_title' => 'Something Went Wrong',
        '500_body'  => 'An unexpected error occurred. Please try again later.',
    ],
];
