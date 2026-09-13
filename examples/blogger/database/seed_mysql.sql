-- Seed Roles
INSERT IGNORE INTO roles (id, name, slug) VALUES (1, 'Administrator', 'admin');
INSERT IGNORE INTO roles (id, name, slug) VALUES (2, 'Author', 'author');

-- Seed Permissions
INSERT IGNORE INTO permissions (id, name, slug) VALUES (1, 'publish_posts', 'publish_posts');
INSERT IGNORE INTO permissions (id, name, slug) VALUES (2, 'moderate_comments', 'moderate_comments');

-- Assign Permissions to Roles
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, 1);
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, 2);
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (2, 1);

-- Seed Users (password: 'password')
INSERT IGNORE INTO users (id, name, email, password) VALUES 
(1, 'Elena Rostova', 'elena@blogger.com', '$2y$12$YBa9h/Kv0PETj7EsruGVx.MuP3C3a9GNLH3U0T7mZCTbRyKxC0xUO');
INSERT IGNORE INTO users (id, name, email, password) VALUES 
(2, 'Marcus Vance', 'marcus@blogger.com', '$2y$12$YBa9h/Kv0PETj7EsruGVx.MuP3C3a9GNLH3U0T7mZCTbRyKxC0xUO');

-- Assign Roles to Users
INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (1, 1);
INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (2, 2);

-- Seed Categories
INSERT IGNORE INTO categories (id, name, slug, description) VALUES
(1, 'Architecture & Systems', 'architecture-systems', 'Deep dives into zero-dependency MVC frameworks, OS kernels, and clean systems architecture.'),
(2, 'AI & Agentic Coding', 'ai-agentic-coding', 'Explorations into autonomous coding agents, LLM tool calling, and self-improving platforms.'),
(3, 'Web Security', 'web-security', 'Production-ready defense strategies, CSRF guards, SQL injection mitigation, and headers.');

-- Seed Tags
INSERT IGNORE INTO tags (id, name, slug) VALUES
(1, 'PHP8', 'php8'),
(2, 'MVC', 'mvc'),
(3, 'Security', 'security'),
(4, 'AI', 'ai');

-- Seed Posts
INSERT IGNORE INTO posts (id, user_id, category_id, title, slug, excerpt, content, status, views, featured) VALUES
(1, 1, 1, 'Building a Zero-Dependency PHP 8.1+ MVC Framework', 'building-zero-dependency-php-mvc', 'Why building your own framework from scratch teaches you more than relying on bloated vendors.', 'In this post, we explore why Spartan was designed with zero external dependencies in core. We cover Reflection-based DI Containers, custom Blade compilers, and driver-aware SQL dialects.', 'published', 1420, 1),
(2, 2, 2, 'Autonomous AI Pair Programming with Antigravity', 'autonomous-ai-pair-programming-antigravity', 'How agentic AI models autonomously execute complex software engineering tasks.', 'Agentic AI assistants possess terminal execution capabilities, persistent memory, and browser context to build complete web applications without manual friction.', 'published', 890, 1),
(3, 1, 3, 'Defense-in-Depth: Mitigating Web Vulnerabilities', 'defense-in-depth-web-vulnerabilities', 'Implementing CSRF protection, parameterized QueryBuilders, and security headers.', 'Security should be baked directly into your application framework kernel. Here is how Spartan enforces strict parameterization and escaping.', 'published', 620, 0);

-- Seed Comments
INSERT IGNORE INTO comments (id, post_id, author_name, author_email, content, status) VALUES
(1, 1, 'Sarah Lin', 'sarah@example.com', 'Fantastic article! The zero-dependency philosophy is super refreshing.', 'approved'),
(2, 1, 'David Miller', 'david@example.com', 'The Reflection DI container explanation was spot on.', 'approved');

