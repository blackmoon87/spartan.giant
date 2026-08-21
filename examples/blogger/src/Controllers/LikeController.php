<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\ToggleLikeRequest;
use Spartan\Controller;
use App\Services\PostLikeService;

class LikeController extends Controller
{
    public function __construct(private PostLikeService $likeService)
    {
        parent::__construct();
    }

    public function toggle(ToggleLikeRequest $request): void
    {
        $postId    = (int)$request->post('post_id');
        $userId    = $this->session->get('user_id') ? (int)$this->session->get('user_id') : null;
        $ipAddress = $this->request->getIp();

        $count = $this->likeService->toggleLike($postId, $userId, $ipAddress);

        if ($this->request->isAjax()) {
            echo "👏 {$count} Claps";
            return;
        }

        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
