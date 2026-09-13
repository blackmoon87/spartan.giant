@extends('layouts.blog')

@section('content')
<article class="glass-card" style="margin-bottom: 2.5rem; padding: 3rem 2.5rem;">
    <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.2;">{{ $post->title }}</h1>
    <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span>Published {{ $post->created_at }}</span> &bull; <span>👁️ {{ $post->views }} views</span>
        </div>
        
        <!-- Live HTMX Clap/Like Button -->
        <button hx-post="{{ url('/like/toggle') }}" 
                hx-vals='{"post_id": {{ $post->id }}, "_csrf": "{{ $_SESSION["_csrf_token"] ?? "" }}"}'
                hx-swap="outerHTML" 
                class="btn" 
                style="background: rgba(168, 85, 247, 0.2); border: 1px solid var(--primary); color: #fff; padding: 0.4rem 1rem; font-size: 0.9rem;">
            👏 Clap Article
        </button>
    </div>

    <div style="font-size: 1.1rem; line-height: 1.8; color: #e2e8f0; margin-bottom: 2rem;">
        <p style="font-size: 1.2rem; color: #94a3b8; margin-bottom: 1.5rem; font-weight: 500;">{{ $post->excerpt }}</p>
        <div style="white-space: pre-line;">{{ $post->content }}</div>
    </div>
</article>

<!-- Newsletter Subscription Card -->
<div class="glass-card" style="margin-bottom: 2.5rem; text-align: center; background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(56, 189, 248, 0.15) 100%);">
    <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Enjoyed this article?</h3>
    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Subscribe to get the latest systems architecture & AI insights delivered straight to your inbox.</p>
    
    <div id="newsletter-form-container" style="max-width: 500px; margin: 0 auto;">
        <form hx-post="{{ url('/newsletter/subscribe') }}" hx-target="#newsletter-form-container" hx-swap="innerHTML">
            @csrf
            <div style="display: flex; gap: 0.5rem;">
                <input type="email" name="email" placeholder="Enter your email..." required style="flex: 1; padding: 0.75rem 1rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;">
                <button type="submit" class="btn">Subscribe</button>
            </div>
        </form>
    </div>
</div>

<!-- Comments Section -->
<section class="glass-card">
    <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem;">Discussion & Comments ({{ count($comments) }})</h3>

    <div id="comments-container">
        @foreach($comments as $comment)
            @include('blog.partials.comment_item', ['comment' => $comment])
        @endforeach
    </div>

    <!-- Post Comment Form with HTMX dynamic append -->
    <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
        <h4 style="margin-bottom: 1rem;">Leave a Comment</h4>
        <form action="{{ url('/comment/store') }}" 
              method="POST" 
              hx-post="{{ url('/comment/store') }}" 
              hx-target="#comments-container" 
              hx-swap="beforeend"
              hx-on::after-request="if(event.detail.successful) this.reset()">
            @csrf
            <input type="hidden" name="post_id" value="{{ $post->id }}">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <input type="text" name="author_name" placeholder="Your Name" required style="padding: 0.75rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;">
                <input type="email" name="author_email" placeholder="Your Email Address" required style="padding: 0.75rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;">
            </div>

            <div style="margin-bottom: 1rem;">
                <textarea name="content" rows="3" placeholder="Share your thoughts..." required style="width: 100%; padding: 0.75rem; background: rgba(9,13,22,0.8); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; outline: none;"></textarea>
            </div>

            <button type="submit" class="btn">Post Comment</button>
        </form>
    </div>
</section>
@endsection
