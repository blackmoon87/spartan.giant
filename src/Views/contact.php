<div class="glass-card">
    <h1 class="hero-title">Contact Us</h1>
    <p class="lead">Submit this form to test how the router handles POST requests and how the <code>Request</code> component sanitizes user input parameters.</p>
    
    <?php if (!empty($message)): ?>
        <?php $isSuccess = (strpos($message, 'Success') === 0); ?>
        <div style="padding: 1rem; border-radius: 8px; margin-bottom: 2rem; background: <?= $isSuccess ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; border: 1px solid <?= $isSuccess ? 'var(--accent)' : '#ef4444' ?>; color: <?= $isSuccess ? '#10b981' : '#f87171' ?>; font-weight: 500;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form action="/contact" method="post">
        <?= $this->csrfToken() ?>
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($body['name'] ?? '') ?>" placeholder="e.g. Jane Doe" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($body['email'] ?? '') ?>" placeholder="e.g. jane@example.com" required>
        </div>
        
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" placeholder="Type your message here..." required><?= htmlspecialchars($body['message'] ?? '') ?></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem; align-items: center;">
            <button type="submit">Submit Form</button>
            <a href="/" style="text-decoration: none; color: var(--text-secondary); font-size: 0.95rem;">Cancel</a>
        </div>
    </form>
</div>
