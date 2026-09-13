@extends('layouts.blog')

@section('content')
<div class="glass-card" style="max-width: 480px; margin: 3rem auto; padding: 2.5rem;">
    <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem; text-align: center;">Author Login</h2>
    <p style="color: var(--text-secondary); text-align: center; margin-bottom: 2rem; font-size: 0.95rem;">
        Access the Spartan Blogger Publishing Portal to draft and publish system articles.
    </p>

    <form action="{{ url('/login') }}" method="POST">
        @csrf

        <div style="margin-bottom: 1.25rem;">
            <label style="display: block; margin-bottom: 0.4rem; font-size: 0.9rem; color: var(--text-secondary);">Email Address</label>
            <input type="email" name="email" value="marcus@blogger.com" required style="width: 100%; padding: 0.75rem 1rem; background: rgba(9, 13, 22, 0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;">
        </div>

        <div style="margin-bottom: 1.75rem;">
            <label style="display: block; margin-bottom: 0.4rem; font-size: 0.9rem; color: var(--text-secondary);">Password</label>
            <input type="password" name="password" value="password" required style="width: 100%; padding: 0.75rem 1rem; background: rgba(9, 13, 22, 0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;">
        </div>

        <button type="submit" class="btn" style="width: 100%; padding: 0.85rem; font-size: 1rem;">Sign In &rarr;</button>
    </form>

    <!-- Quick Demo Accounts -->
    <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: center;">
        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.75rem;">Quick Demo One-Click Login:</p>
        <div style="display: flex; gap: 0.5rem; justify-content: center;">
            <form action="{{ url('/login') }}" method="POST" style="display: inline;">
                @csrf
                <input type="hidden" name="email" value="marcus@blogger.com">
                <input type="hidden" name="password" value="password">
                <button type="submit" class="btn" style="background: rgba(168,85,247,0.2); border: 1px solid var(--primary); font-size: 0.8rem; padding: 0.4rem 0.8rem;">Login as Author (Marcus)</button>
            </form>
            <form action="{{ url('/login') }}" method="POST" style="display: inline;">
                @csrf
                <input type="hidden" name="email" value="elena@blogger.com">
                <input type="hidden" name="password" value="password">
                <button type="submit" class="btn" style="background: rgba(56,189,248,0.2); border: 1px solid var(--accent); font-size: 0.8rem; padding: 0.4rem 0.8rem;">Login as Admin (Elena)</button>
            </form>
        </div>
    </div>
</div>
@endsection
