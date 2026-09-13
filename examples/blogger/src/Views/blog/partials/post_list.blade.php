@foreach($posts as $post)
    <div class="article-card">
        <h3 style="font-size: 1.4rem; margin-bottom: 0.5rem;">
            <a href="{{ url('/post/' . $post['slug']) }}" style="color: var(--text-primary); text-decoration: none;">{{ $post['title'] }}</a>
        </h3>
        <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem;">{{ $post['excerpt'] }}</p>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: var(--text-secondary);">
            <span>👁️ {{ $post['views'] }} views &bull; 🕒 {{ class_exists('\Carbon\Carbon') ? \Carbon\Carbon::parse($post['created_at'] ?? 'now')->diffForHumans() : date('M d, Y', strtotime($post['created_at'] ?? 'now')) }}</span>
            <a href="{{ url('/post/' . $post['slug']) }}" class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Read Full Post &rarr;</a>
        </div>
    </div>
@endforeach

@empty($posts)
    <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
        <p>No articles matching your search criteria were found.</p>
    </div>
@endempty
