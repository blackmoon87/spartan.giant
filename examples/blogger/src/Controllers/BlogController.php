<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\PostService;

class BlogController extends Controller
{
    public function __construct(private PostService $postService)
    {
        parent::__construct();
    }

    public function index(): string
    {
        $categories = (new Category())->all();
        
        // Eager load posts for categories
        $categories = (new Category())->posts()->loadFor($categories, as: 'posts');
        
        $featuredPosts = (new Post())->table()
            ->where('featured', 1)
            ->orderBy('created_at', 'DESC')
            ->limit(3)
            ->get();

        $latestPosts = (new Post())->table()
            ->orderBy('created_at', 'DESC')
            ->limit(6)
            ->get();

        return $this->render('blog/index', [
            'title'         => 'Spartan Blogger — Systems Architecture & AI Insights',
            'categories'    => $categories,
            'featuredPosts' => $featuredPosts,
            'latestPosts'   => $latestPosts,
        ]);
    }

    public function category(string $slug): string
    {
        $category = (new Category())->findInstanceBy('slug', $slug);
        if (!$category) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['message' => 'Category not found.']);
        }

        $posts = (new Post())->table()
            ->where('category_id', (int)$category->id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return $this->render('blog/category', [
            'title'    => "Category: {$category->name}",
            'category' => $category,
            'posts'    => $posts,
        ]);
    }

    public function search(): string
    {
        $keyword = trim((string)$this->request->post('query', ''));
        
        $qb = (new Post())->table();
        if ($keyword !== '') {
            $qb->where('title', "%{$keyword}%", 'LIKE');
        }

        $posts = $qb->get();

        // HTMX Partial Fragment Render
        return $this->renderViewOnly('blog/partials/post_list', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): string
    {
        $post = (new Post())->findInstanceBy('slug', $slug);
        if (!$post) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['message' => 'Article not found.']);
        }

        // Increment view count
        $this->postService->incrementViews($post);

        // Fetch comments
        $comments = (new Post())->comments()->for($post);

        return $this->render('blog/show', [
            'title'    => $post->title,
            'post'     => $post,
            'comments' => $comments,
        ]);
    }
}
