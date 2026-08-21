<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->yieldContent('title', 'TaskForge'); ?></title>
    <style>
        :root {
            --bg: #0f172a; --surface: #1e293b; --border: #334155;
            --primary: #6366f1; --primary-glow: rgba(99,102,241,.15);
            --success: #22c55e; --warning: #eab308; --danger: #ef4444;
            --text: #e2e8f0; --text-muted: #94a3b8;
            --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        a { color: var(--primary); text-decoration: none; } a:hover { text-decoration: underline; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 0; margin-bottom: 32px; }
        nav .container { display: flex; justify-content: space-between; align-items: center; }
        nav .brand { font-size: 1.3rem; font-weight: 700; color: var(--primary); }
        nav .links a { margin-left: 20px; color: var(--text-muted); font-size: .9rem; }
        nav .links a:hover { color: var(--text); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; transition: box-shadow .2s; }
        .card:hover { box-shadow: 0 0 0 1px var(--primary), 0 8px 25px var(--primary-glow); }
        .card h2 { margin-bottom: 12px; font-size: 1.15rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .stats { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .stat-card { flex: 1; min-width: 140px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; text-align: center; }
        .stat-card .num { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-card .label { font-size: .8rem; color: var(--text-muted); margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-todo { background: var(--border); color: var(--text-muted); }
        .badge-progress { background: rgba(234,179,8,.15); color: var(--warning); }
        .badge-done { background: rgba(34,197,94,.15); color: var(--success); }
        .badge-high { background: rgba(239,68,68,.15); color: var(--danger); }
        .badge-medium { background: rgba(234,179,8,.15); color: var(--warning); }
        .badge-low { background: rgba(99,102,241,.15); color: var(--primary); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: .9rem; }
        th { color: var(--text-muted); font-weight: 600; font-size: .8rem; text-transform: uppercase; }
        input, select, textarea { background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; width: 100%; font-size: .9rem; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        button, .btn { background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-size: .9rem; cursor: pointer; font-weight: 600; transition: opacity .2s; }
        button:hover, .btn:hover { opacity: .85; }
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: .9rem; }
        .flash-success { background: rgba(34,197,94,.1); border: 1px solid var(--success); color: var(--success); }
        .flash-warning { background: rgba(234,179,8,.1); border: 1px solid var(--warning); color: var(--warning); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: .85rem; color: var(--text-muted); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        footer { text-align: center; padding: 32px 0; color: var(--text-muted); font-size: .8rem; border-top: 1px solid var(--border); margin-top: 48px; }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="brand">⚡ TaskForge</a>
            <div class="links">
                <a href="/dashboard">Dashboard</a>
                <a href="/projects">Projects</a>
                <?php if(($__user = \Spartan\Gate::resolveUser()) && method_exists($__user, 'hasRole') && $__user->hasRole('admin')): ?>
                <a href="/admin">Admin</a>
                <?php endif; ?>
                <?php if(auth()->check()): ?>
                <a href="/logout">Logout</a>
                <?php else: ?>
                <a href="/login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if($flashMsg = $this->flash('success')): ?>
        <div class="flash flash-success"><?php echo htmlspecialchars(($message) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if($flashMsg = $this->flash('warning')): ?>
        <div class="flash flash-warning"><?php echo htmlspecialchars(($message) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php echo $this->yieldContent('content'); ?>
    </main>

    <footer>
        <div class="container">
            Built with <strong>Spartan Framework</strong> — Zero Dependencies, Maximum Power.
        </div>
    </footer>
</body>
</html>
