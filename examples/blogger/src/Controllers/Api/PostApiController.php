<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Spartan\Controller;
use App\Models\Post;

class PostApiController extends Controller
{
    public function index(): void
    {
        $posts = (new Post())->all();

        $this->json([
            'status' => 'success',
            'count'  => count($posts),
            'data'   => $posts,
        ]);
    }

    public function show(string $slug): void
    {
        $post = (new Post())->findInstanceBy('slug', $slug);
        if (!$post) {
            $this->json(['error' => 'Article not found.'], 404);
            return;
        }

        $this->json([
            'status' => 'success',
            'data'   => $post->toArray(),
        ]);
    }
}
