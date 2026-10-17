<?php

declare(strict_types=1);

return [
    'index_title'   => 'Tickets',
    'create_title'  => 'New ticket',
    'create_lead'   => 'Describe the problem; an agent will get back to you.',
    'filter_status' => 'Status',
    'any_status'    => 'Any status',
    'search_label'  => 'Search subject and description',
    'sort_label'    => 'Sort',
    'sort'          => [
        'newest'  => 'Newest first',
        'oldest'  => 'Oldest first',
        'updated' => 'Recently updated',
    ],
    'only_mine'     => 'Assigned to me',
    'empty'         => 'No tickets match these filters.',

    'col_subject'   => 'Subject',
    'col_status'    => 'Status',
    'col_priority'  => 'Priority',
    'col_owner'     => 'Customer',
    'col_assignee'  => 'Agent',
    'col_updated'   => 'Updated',

    'subject'       => 'Subject',
    'body'          => 'Description',
    'priority'      => 'Priority',
    'attachment'    => 'Attachment (optional)',
    'attachment_hint' => 'PNG, JPEG, GIF, WebP, PDF or plain text, up to 2 MB.',
    'submit'        => 'Open ticket',

    'opened_by'     => 'Opened by :name on :date',
    'assigned_to'   => 'Agent',
    'attachments'   => 'Attachments',
    'replies'       => 'Conversation',
    'no_replies'    => 'No replies yet.',
    'internal_badge' => 'Internal note',
    'reply_label'   => 'Your reply',
    'reply_internal' => 'Internal note (hidden from the customer)',
    'reply_submit'  => 'Post reply',
    'closed_notice' => 'This ticket is closed. Ask an agent to reopen it if you need more help.',
    'manage'        => 'Manage ticket',
    'update_submit' => 'Update ticket',
    'history'       => 'History',
    'activity'      => [
        'created'        => ':name opened the ticket',
        'replied'        => ':name replied',
        'noted'          => ':name added an internal note',
        'status_changed' => ':name set the status to :details',
        'assigned'       => ':name assigned it to :details',
        'unassigned'     => ':name removed the agent',
    ],
    'someone'       => 'Someone',

    'created'       => 'Ticket #:id is open. We will be in touch.',
    'updated'       => 'Ticket updated.',
    'reply_posted'  => 'Reply posted.',
    'upload' => [
        'failed'    => 'The attachment could not be uploaded. Please try again.',
        'too_large' => 'The attachment is too large (maximum :max).',
        'bad_type'  => 'That file type is not allowed.',
    ],
];
