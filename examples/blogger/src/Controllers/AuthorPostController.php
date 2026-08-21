<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StorePostRequest;
use Spartan\Attributes\RequirePermission;
use Spartan\Attributes\RequireRole;
use Spartan\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\PostService;

#[RequireRole('author')]
#[RequirePermission('publish_posts')]
class AuthorPostController extends Controller
{
    public function __construct(private PostService $postService)
    {
        parent::__construct();
    }

    public function index(): string
    {
        $posts      = (new Post())->all();
        $categories = (new Category())->all();

        return $this->render('author/posts', [
            'title'      => 'Author Publishing Portal',
            'posts'      => $posts,
            'categories' => $categories,
        ]);
    }

    public function store(StorePostRequest $request): void
    {
        $userId     = (int)$this->session->get('user_id', 1);
        $categoryId = (int)$request->post('category_id');
        $title      = (string)$request->post('title');
        $excerpt    = (string)$request->post('excerpt');
        $content    = (string)$request->post('content');

        $post = $this->postService->createPost($userId, $categoryId, $title, $excerpt, $content);

        // Dispatch sync & async events
        $this->event('post.published', [
            'id'    => $post->id,
            'title' => $post->title,
            'slug'  => $post->slug,
        ]);

        $this->session->setFlash('success', "Article '{$post->title}' published successfully!");
        $this->redirect('/author/posts');
    }
}
