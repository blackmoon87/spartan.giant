<div class="glass-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h1 class="hero-title" style="margin-bottom: 0;">System Booted</h1>
        <span class="status-pill success">MVC Skills Active</span>
    </div>
    
    <p class="lead">Welcome to your clean PHP MVC boilerplate! This workspace is ready for development. The custom <code>.cursorrules</code> file loaded in the root directory enables your AI IDE to write, expand, and structure features exactly according to this architecture.</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2.5rem;">
        <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 12px; transition: background 0.3s;" onmouseover="this.style.background='rgba(99,102,241,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <h3 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 6px; height: 6px; background: var(--primary); border-radius: 50%;"></span>
                Router Mapping
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">Map incoming GET and POST request patterns cleanly to closure functions or controller actions inside <code>public/index.php</code>.</p>
        </div>

        <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 12px; transition: background 0.3s;" onmouseover="this.style.background='rgba(99,102,241,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <h3 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 6px; height: 6px; background: var(--primary); border-radius: 50%;"></span>
                Controllers & Views
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">Extend <code>Spartan\Controller</code> to sanitize data, access requests, render layout/view templates, and return JSON responses.</p>
        </div>

        <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 12px; transition: background 0.3s;" onmouseover="this.style.background='rgba(99,102,241,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <h3 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 6px; height: 6px; background: var(--primary); border-radius: 50%;"></span>
                Database Status
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; margin-bottom: 0.75rem;">
                Status: <strong style="color: #fff;"><?= htmlspecialchars($dbStatus) ?></strong>
            </p>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                Users found in database: <code style="color: var(--accent); background: rgba(16, 185, 129, 0.1);"><?= (int)$userCount ?></code>
            </p>
        </div>
    </div>

    <div style="margin-top: 2.5rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
        <h3 style="margin-bottom: 1rem; color: #fff;">Registered Route Examples</h3>
        <p style="color: var(--text-secondary); margin-bottom: 1rem;">Try clicking these demo routes configured in the Router:</p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="/profile/admin" style="text-decoration: none;"><button type="button" style="background: rgba(255,255,255,0.05); box-shadow: none; border: 1px solid var(--border-color);">Test Dynamic Route (/profile/admin)</button></a>
            <a href="/contact" style="text-decoration: none;"><button type="button">Test Form Handler (/contact)</button></a>
        </div>
    </div>
</div>
