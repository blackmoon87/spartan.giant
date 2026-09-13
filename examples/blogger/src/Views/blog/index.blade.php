@extends('layouts.blog')

@section('content')
<div class="glass-card" style="margin-bottom: 2.5rem; text-align: center; padding: 3.5rem 2rem;">
    <h1 style="font-size: 2.8rem; font-weight: 800; margin-bottom: 1rem; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Systems Architecture & Agentic AI Insights
    </h1>
    <p style="font-size: 1.15rem; color: var(--text-secondary); max-width: 650px; margin: 0 auto 2rem;">
        In-depth technical publications on zero-dependency PHP frameworks, autonomous AI coding agents, and production security engineering.
    </p>

    <!-- Live HTMX Search Input -->
    <div style="max-width: 500px; margin: 0 auto;">
        <input type="text" 
               name="query" 
               hx-post="{{ url('/blog/search') }}" 
               hx-trigger="keyup changed delay:250ms" 
               hx-target="#post-list-container" 
               hx-include="[name=_csrf]"
               placeholder="🔍 Search articles live with HTMX..." 
               style="width: 100%; padding: 0.85rem 1.25rem; background: rgba(9, 13, 22, 0.8); border: 1px solid var(--border-color); border-radius: 10px; color: #fff; outline: none; font-size: 1rem;">
        <input type="hidden" name="_csrf" value="{{ $_SESSION['_csrf_token'] ?? '' }}">
    </div>
</div>

<h2 style="margin-bottom: 1rem;">Categories</h2>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 3rem;">
    @foreach($categories as $category)
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--primary);">{{ $category['name'] }}</h3>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">{{ $category['description'] }}</p>
            <a href="{{ url('/category/' . $category['slug']) }}" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; font-weight: 600;">Explore Articles &rarr;</a>
        </div>
    @endforeach
</div>

<h2 style="margin-bottom: 1.5rem;">Latest Articles</h2>
<div id="post-list-container">
    @include('blog.partials.post_list', ['posts' => $latestPosts])
</div>
@endsection
