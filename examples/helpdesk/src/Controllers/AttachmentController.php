<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;

/**
 * Attachments live outside the web root; this is the only way to read one,
 * and it runs the same 'view' policy as the ticket page.
 */
class AttachmentController extends AppController
{
    public function __construct(private AttachmentService $files)
    {
        parent::__construct();
    }

    public function download(string $id): string
    {
        $attachment = self::isId($id) ? (new Attachment())->find((int) $id) : null;
        if ($attachment === null) {
            return $this->notFound();
        }

        $ticket = $this->ticketFor((string) $attachment['ticket_id'], 'view');
        if (is_string($ticket)) {
            return $ticket;
        }

        $path = $this->files->path((string) $attachment['stored_name']);
        if ($path === null) {
            return $this->notFound();
        }

        // Images and PDFs open inline; everything is sent with nosniff so the
        // browser keeps the type we verified with finfo at upload time.
        $inline = str_starts_with($attachment['mime'], 'image/') || $attachment['mime'] === 'application/pdf';
        $name   = str_replace(['"', "\r", "\n"], '', (string) $attachment['original_name']);
        $this->response->setHeader('Content-Type', $attachment['mime']);
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        $this->response->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        $this->response->setHeader('Cache-Control', 'private, max-age=0');

        return (string) file_get_contents($path);
    }
}
