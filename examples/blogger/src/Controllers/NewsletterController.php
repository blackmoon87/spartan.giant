<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\SubscribeNewsletterRequest;
use Spartan\Controller;
use App\Services\AuditService;
use App\Services\NewsletterService;

class NewsletterController extends Controller
{
    public function __construct(
        private NewsletterService $newsletterService,
        private AuditService $auditService
    ) {
        parent::__construct();
    }

    public function subscribe(SubscribeNewsletterRequest $request): void
    {
        $email = (string)$request->post('email');
        $sub   = $this->newsletterService->subscribe($email);

        $this->auditService->logAction(null, 'newsletter.subscribed', "Email: {$email}");

        $this->event('newsletter.subscribed', ['email' => $email]);

        if ($this->request->isAjax()) {
            echo '<div style="color: #34d399; font-weight: 600; padding: 0.5rem 0;">🎉 Thank you for subscribing to Spartan Blogger!</div>';
            return;
        }

        $this->session->setFlash('success', 'Thank you for subscribing to our newsletter!');
        $this->redirect('/');
    }
}
