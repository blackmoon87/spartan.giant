<?php $this->extend('layouts/app'); ?>

<?php $this->sections[trim('title', "'\"")] = 'Projects — TaskForge'; ?>
<?php $this->startSection('content'); ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h1>📁 Projects</h1>
    <?php if(($__user = \Spartan\Gate::resolveUser()) && method_exists($__user, 'hasRole') && $__user->hasRole('admin', 'manager')): ?>
    <a href="#new-project" class="btn" onclick="document.getElementById('new-project-form').style.display='block'">+ New Project</a>
    <?php endif; ?>
</div>

<div id="new-project-form" style="display:none; margin-bottom:24px;">
    <div class="card" style="border-color: var(--primary);">
        <h2>Create Project</h2>
        <form method="POST" action="/projects/store">
            <?php echo $this->csrfToken(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="proj-name">Project Name</label>
                    <input type="text" id="proj-name" name="name" required minlength="3">
                </div>
                <div class="form-group">
                    <label for="proj-priority">Priority</label>
                    <select id="proj-priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="proj-desc">Description</label>
                <textarea id="proj-desc" name="description" rows="3" required minlength="10"></textarea>
            </div>
            <div class="form-group">
                <label for="proj-deadline">Deadline (optional)</label>
                <input type="date" id="proj-deadline" name="deadline">
            </div>
            <button type="submit">Create Project</button>
        </form>
    </div>
</div>

<?php if(empty($projects)): ?>
<div class="card"><p style="color:var(--text-muted);">No projects yet.</p></div>
<?php else: ?>
<div class="grid">
    <?php foreach($projects as $project): ?>
    <div class="card">
        <h2><a href="/project/<?php echo htmlspecialchars(($project['slug']) ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($project['name']) ?? '', ENT_QUOTES, 'UTF-8'); ?></a></h2>
        <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:12px;"><?php echo htmlspecialchars(($project['description']) ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <div style="display:flex; gap:8px; align-items:center;">
            <span class="badge badge-<?php echo htmlspecialchars(($project['priority']) ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((ucfirst($project['priority'])) ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="badge" style="background:var(--border);"><?php echo htmlspecialchars(($project['status']) ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if($project['deadline']): ?>
            <span style="font-size:.8rem; color:var(--text-muted);">📅 <?php echo htmlspecialchars(($project['deadline']) ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
