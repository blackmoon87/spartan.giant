<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Stops the current request with a response that is already prepared
 * (validation redirect, 403, 422 JSON...).
 *
 * Replaces exit(): Application::run() catches it and sends the response, so
 * the session is still saved and a worker-mode process (FrankenPHP,
 * RoadRunner, Swoole) keeps serving instead of dying with the request.
 */
class HttpResponseException extends \RuntimeException
{
    public function __construct(private readonly Response $response)
    {
        parent::__construct('HTTP response already prepared.', $response->getStatusCode());
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
