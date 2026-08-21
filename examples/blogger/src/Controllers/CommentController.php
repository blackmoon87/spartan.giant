<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StoreCommentRequest;
use Spartan\Controller;
use App\Models\Post;
use App\Services\CommentService;

class CommentController extends Controller
{
    public function __construct(private CommentService $commentService)
    {
        parent::__construct();
    }

    public function store(StoreCommentRequest $request): void
    {
        $postId      = (int)$request->post('post_id');
        $authorName  = (string)$request->post('author_name');
        $authorEmail = (string)$request->post('author_email');
        $content     = (string)$request->post('content');

        $post = (new Post())->findInstance($postId);
        if (!$post) {
            $this->response->setStatusCode(404);
            return;
        }

        $comment = $this->commentService->addComment($postId, $authorName, $authorEmail, $content);

        if ($this->request->isAjax()) {
            // HTMX partial return for newly appended comment
            echo $this->renderViewOnly('blog/partials/comment_item', ['comment' => $comment->toArray()]);
            return;
        }

        $this->session->setFlash('success', 'Comment posted successfully!');
        $this->redirect("/post/{$post->slug}");
    }
}
