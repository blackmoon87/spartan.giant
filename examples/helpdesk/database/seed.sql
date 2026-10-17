-- Demo data. Every account's password is: password
INSERT INTO roles (id, name, slug) VALUES
    (1, 'Administrator', 'admin'),
    (2, 'Support agent', 'agent'),
    (3, 'Customer', 'customer');

INSERT INTO permissions (id, name, slug) VALUES
    (1, 'View every ticket', 'view_all_tickets'),
    (2, 'Change status, assign, write internal notes', 'manage_tickets'),
    (3, 'Manage users and roles', 'manage_users');

INSERT INTO role_permissions (role_id, permission_id) VALUES
    (1, 1), (1, 2), (1, 3),
    (2, 1), (2, 2);

INSERT INTO users (id, name, email, password, created_at, updated_at) VALUES
    (1, 'Ada Admin',     'admin@helpdesk.test', '$2y$12$hQUkcqtdsCE7cex2/28t8.AGvzZZRs34Zg7.HUNbTPrm0Bzc5TizC', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
    (2, 'Alex Agent',    'agent@helpdesk.test', '$2y$12$hQUkcqtdsCE7cex2/28t8.AGvzZZRs34Zg7.HUNbTPrm0Bzc5TizC', '2026-09-01 08:05:00', '2026-09-01 08:05:00'),
    (3, 'Riley Agent',   'riley@helpdesk.test', '$2y$12$hQUkcqtdsCE7cex2/28t8.AGvzZZRs34Zg7.HUNbTPrm0Bzc5TizC', '2026-09-01 08:10:00', '2026-09-01 08:10:00'),
    (4, 'Maria Lopez',   'maria@helpdesk.test', '$2y$12$hQUkcqtdsCE7cex2/28t8.AGvzZZRs34Zg7.HUNbTPrm0Bzc5TizC', '2026-09-02 09:00:00', '2026-09-02 09:00:00'),
    (5, 'Sam Carter',    'sam@helpdesk.test',   '$2y$12$hQUkcqtdsCE7cex2/28t8.AGvzZZRs34Zg7.HUNbTPrm0Bzc5TizC', '2026-09-03 10:00:00', '2026-09-03 10:00:00');

INSERT INTO user_roles (user_id, role_id) VALUES (1, 1), (2, 2), (3, 2), (4, 3), (5, 3);

INSERT INTO tickets (id, user_id, assignee_id, subject, body, status, priority, created_at, updated_at) VALUES
    (1, 4, 2,    'Cannot log in after password change', 'Since I changed my password yesterday the login page says my credentials are wrong.', 'pending',  'high',   '2026-10-10 09:12:00', '2026-10-11 14:30:00'),
    (2, 4, NULL, 'Invoice shows the wrong company name', 'The PDF invoice for September lists our old company name.', 'open',     'normal', '2026-10-12 11:00:00', '2026-10-12 11:00:00'),
    (3, 5, 3,    'Export to CSV times out',              'Exporting more than 5,000 rows never finishes; the browser gives up after a minute.', 'open',     'urgent', '2026-10-13 16:45:00', '2026-10-14 08:20:00'),
    (4, 5, 2,    'How do I add a second user?',          'We hired a colleague. Where do I invite them?', 'resolved', 'low',    '2026-10-05 10:00:00', '2026-10-06 09:00:00'),
    (5, 4, 3,    'Dark mode request',                    'Would love a dark theme for late shifts.', 'closed',   'low',    '2026-09-20 18:00:00', '2026-09-25 12:00:00'),
    (6, 5, NULL, 'Email notifications arrive late',      'Notification emails show up 20–30 minutes after the event.', 'open',     'high',   '2026-10-15 07:30:00', '2026-10-15 07:30:00');

INSERT INTO replies (ticket_id, user_id, body, is_internal, created_at, updated_at) VALUES
    (1, 2, 'Thanks Maria — could you tell us which browser you use?', 0, '2026-10-10 10:00:00', '2026-10-10 10:00:00'),
    (1, 4, 'Firefox 131 on Windows.', 0, '2026-10-10 10:20:00', '2026-10-10 10:20:00'),
    (1, 2, 'Probably the stale session cookie bug; asked engineering to confirm.', 1, '2026-10-11 14:30:00', '2026-10-11 14:30:00'),
    (3, 3, 'Reproduced with 6,000 rows. Escalating to the backend team.', 1, '2026-10-14 08:20:00', '2026-10-14 08:20:00'),
    (4, 2, 'Go to Settings → Team → Invite. Let us know if anything is unclear!', 0, '2026-10-05 12:00:00', '2026-10-05 12:00:00'),
    (4, 5, 'Found it, thank you!', 0, '2026-10-06 09:00:00', '2026-10-06 09:00:00');

INSERT INTO activities (ticket_id, user_id, action, details, created_at) VALUES
    (1, 4, 'created', NULL, '2026-10-10 09:12:00'),
    (1, 2, 'assigned', 'Alex Agent', '2026-10-10 09:40:00'),
    (1, 2, 'status_changed', 'pending', '2026-10-11 14:30:00'),
    (2, 4, 'created', NULL, '2026-10-12 11:00:00'),
    (3, 5, 'created', NULL, '2026-10-13 16:45:00'),
    (3, 3, 'assigned', 'Riley Agent', '2026-10-14 08:00:00'),
    (4, 5, 'created', NULL, '2026-10-05 10:00:00'),
    (4, 2, 'status_changed', 'resolved', '2026-10-06 09:00:00'),
    (5, 4, 'created', NULL, '2026-09-20 18:00:00'),
    (5, 3, 'status_changed', 'closed', '2026-09-25 12:00:00'),
    (6, 5, 'created', NULL, '2026-10-15 07:30:00');

INSERT INTO notifications (user_id, ticket_id, message_key, params, event_key, read_at, created_at) VALUES
    (4, 1, 'notifications.agent_replied', '{"subject":"Cannot log in after password change","name":"Alex Agent"}', 'seed:1', NULL, '2026-10-10 10:00:00'),
    (5, 4, 'notifications.status_changed', '{"subject":"How do I add a second user?","status":"resolved"}', 'seed:2', '2026-10-06 10:00:00', '2026-10-06 09:00:00');
