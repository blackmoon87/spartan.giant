<?php

declare(strict_types=1);

return [
    'title'          => 'Notifications',
    'empty'          => 'Nothing new. Notifications appear here once the queue worker has run.',
    'worker_hint'    => 'Notifications are delivered by a queued job: keep `php spartan worker --loop` running.',
    'mark_read'      => 'Mark all as read',
    'marked_read'    => 'All notifications marked as read.',
    'new'            => 'New',

    'ticket_created'   => ':name opened “:subject”.',
    'agent_replied'    => ':name replied to “:subject”.',
    'customer_replied' => ':name replied to “:subject”.',
    'status_changed'   => '“:subject” is now :status.',
    'assigned'         => ':name assigned “:subject” to you.',
];
