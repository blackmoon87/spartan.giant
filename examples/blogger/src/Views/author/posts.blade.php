@extends('layouts.blog')

@section('content')
<h2 style="margin-bottom: 1.5rem;">Author Publishing Portal</h2>

@role('author')
<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem;">
    <!-- Published Articles -->
    <div class="glass-card">
        <h3 style="margin-bottom: 1rem;">Published Articles ({{ count($posts) }})</h3>
        @foreach($posts as $p)
            <div style="border-bottom: 1px solid var(--border-color); padding: 1rem 0;">
                <h4 style="font-size: 1.1rem; color: var(--text-primary);"><a href="{{ url('/post/' . $p['slug']) }}" style="color: inherit; text-decoration: none;">{{ $p['title'] }}</a></h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Slug: /post/{{ $p['slug'] }} &bull; Views: {{ $p['views'] }}</p>
            </div>
        @endforeach
    </div>

    <!-- Create Article Form -->
    <div class="glass-card">
        <h3 style="margin-bottom: 1rem;">Publish New Article</h3>
        <form action="{{ url('/author/posts/store') }}" method="POST">
            @csrf

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Title</label>
                <input type="text" name="title" required style="width: 100%; padding: 0.7rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Category</label>
                <select name="category_id" required style="width: 100%; padding: 0.7rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff;">
                    @foreach($categories as $cat)
                        <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Excerpt Summary</label>
                <textarea name="excerpt" rows="2" required style="width: 100%; padding: 0.7rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff;"></textarea>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Full Content</label>
                <textarea name="content" rows="6" required style="width: 100%; padding: 0.7rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff;"></textarea>
            </div>

            <button type="submit" class="btn" style="width: 100%;">Publish Article</button>
        </form>
    </div>
</div>
@endrole
@endsection
