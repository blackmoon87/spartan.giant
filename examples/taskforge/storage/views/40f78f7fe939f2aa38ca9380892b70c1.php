<?php $this->extend('layouts/app'); ?>

<?php $this->sections[trim('title', "'\"")] = $title ?? 'Dashboard'; ?>
<?php $this->startSection('content'); ?>
<h1 style="margin-bottom: 24px;">📊 Dashboard</h1>

<div class="stats">
    <div class="stat-card">
        <div class="num"><?php echo htmlspecialchars(($stats['total_projects'] ?? 0) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="label">Projects</div>
    </div>
    <div class="stat-card">
        <div class="num"><?php echo htmlspecialchars(($stats['total_tasks'] ?? 0) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="label">Total Tasks</div>
    </div>
    <div class="stat-card">
        <div class="num"><?php echo htmlspecialchars(($stats['completed_tasks'] ?? 0) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="num"><?php echo htmlspecialchars(($stats['active_users'] ?? 0) ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="label">Team Members</div>
    </div>
</div>

<?php if(!empty($topProjects)): ?>
<div class="card">
    <h2>🏆 Top Projects by Task Count</h2>
    <table>
        <thead><tr><th>Project</th><th>Tasks</th></tr></thead>
        <tbody>
        <?php foreach($topProjects as $proj): ?>
        <tr>
            <td><a href="/project/<?php echo htmlspecialchars(($proj['slug']) ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($proj['name']) ?? '', ENT_QUOTES, 'UTF-8'); ?></a></td>
            <td><?php echo htmlspecialchars(($proj['task_count']) ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if(!empty($recentTasks)): ?>
<div class="card">
    <h2>🕐 Recent Tasks</h2>
    <table>
        <thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Priority</th></tr></thead>
        <tbody>
        <?php foreach($recentTasks as $task): ?>
        <tr>
            <td><?php echo htmlspecialchars(($task['title']) ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(($task['assignee'] ?? 'Unassigned') ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php if($task['status'] === 'done'): ?>
                <span class="badge badge-done">✓ Done</span>
                <?php elseif($task['status'] === 'in_progress'): ?>
                <span class="badge badge-progress">⏳ In Progress</span>
                <?php else: ?>
                <span class="badge badge-todo">📋 Todo</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge-<?php echo htmlspecialchars(($task['priority']) ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((ucfirst($task['priority'])) ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if(\Spartan\Gate::check('manage_projects')): ?>
<div class="card" style="border-color: var(--primary);">
    <h2>🔧 Manager Actions</h2>
    <p style="color:var(--text-muted); font-size:.9rem;">You have project management permissions.</p>
</div>
<?php endif; ?>

<?php if(\Spartan\Gate::denies('manage_users')): ?>
<div class="card" style="border-color: var(--border); opacity: .6;">
    <p style="color:var(--text-muted); font-size:.85rem;">🔒 Admin panel access requires <code>manage_users</code> permission.</p>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
