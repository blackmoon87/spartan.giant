<?php

declare(strict_types=1);

/**
 * English language file.
 *
 * Usage in views:   @lang('app.welcome', ['name' => $user->name])
 * Usage in PHP:     trans('app.welcome', ['name' => $user->name])
 */
return [
    'app_name' => 'Spartan',

    'welcome' => 'Welcome, :name!',
    'goodbye' => 'Goodbye, :name. See you next time!',

    'nav' => [
        'home'       => 'Home',
        'dashboard'  => 'Dashboard',
        'courses'    => 'Courses',
        'calendar'   => 'Calendar',
        'settings'   => 'Settings',
        'logout'     => 'Logout',
        'login'      => 'Login',
        'register'   => 'Register',
    ],

    'actions' => [
        'save'    => 'Save',
        'cancel'  => 'Cancel',
        'delete'  => 'Delete',
        'edit'    => 'Edit',
        'create'  => 'Create',
        'update'  => 'Update',
        'confirm' => 'Confirm',
        'back'    => 'Go Back',
        'submit'  => 'Submit',
        'search'  => 'Search',
    ],

    'messages' => [
        'saved'   => 'Changes saved successfully.',
        'deleted' => 'Record deleted successfully.',
        'error'   => 'An error occurred. Please try again.',
        'not_found' => 'The requested resource was not found.',
        'unauthorized' => 'You must be logged in to access this page.',
        'forbidden' => 'You do not have permission to perform this action.',
    ],

    'pagination' => [
        'previous' => '« Previous',
        'next'     => 'Next »',
        'showing'  => 'Showing :from to :to of :total results',
    ],
];
