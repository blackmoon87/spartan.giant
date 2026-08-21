<?php

declare(strict_types=1);

namespace Spartan;

abstract class Controller
{
    protected Request $request;
    protected Response $response;
    protected SessionInterface $session;

    public function __construct()
    {
        $this->request  = Application::$app->request;
        $this->response = Application::$app->response;
        $this->session  = Application::$app->session;
    }

    /**
     * Set the layout for the view rendering.
     */
    public function setLayout(string $layout): void
    {
        Application::$app->view->setLayout($layout);
    }

    /**
     * Share a variable globally with all views.
     */
    public function share(string $key, mixed $value): void
    {
        Application::$app->view->share($key, $value);
    }

    /**
     * Render the given view with dynamic parameters.
     */
    public function render(string $view, array $params = []): string
    {
        return Application::$app->view->render($view, $params);
    }

    /**
     * Render only the view template content without wrapping it in a layout.
     * Useful for HTMX / AJAX partial responses.
     */
    public function renderViewOnly(string $view, array $params = []): string
    {
        return Application::$app->view->renderViewOnly($view, $params);
    }

    /**
     * Shortcut: redirect the client to a URL.
     */
    public function redirect(string $url): void
    {
        $this->response->redirect($url);
    }

    /**
     * Shortcut: send a JSON response.
     */
    public function json(mixed $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    /**
     * Shortcut: validate request data against a set of rules.
     * Returns the Validator instance so you can call ->errors() or ->fails().
     *
     * Usage:
     *   $v = $this->validate($this->request->getBody(), [
     *       'email'    => 'required|email',
     *       'password' => 'required|min:8',
     *   ]);
     *   if ($v->fails()) { ... }
     */
    public function validate(array $data, array $rules): Validator
    {
        $validator = new Validator();
        // Inject the PDO instance so the `unique` rule can query the database.
        $validator->setDb(Application::$app->db);
        $validator->validate($data, $rules);
        return $validator;
    }

    /**
     * Shortcut: resolve a service from the Container.
     * Equivalent to Application::$app->container->make($abstract).
     *
     * Usage:
     *   $mailer = $this->make(MailService::class);
     */
    public function make(string $abstract): mixed
    {
        return Application::$app->container->make($abstract);
    }

    /**
     * Shortcut: dispatch an event via the EventDispatcher.
     * Equivalent to Application::$app->events->dispatch($event, $payload).
     *
     * Usage:
     *   $this->event('order.placed', $order);
     */
    public function event(string $event, mixed $payload = null): void
    {
        Application::$app->events->dispatch($event, $payload);
    }
}
