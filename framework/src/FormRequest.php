<?php

declare(strict_types=1);

namespace Spartan;

abstract class FormRequest extends Request
{
    public SessionInterface $session;
    public AuthInterface $auth;

    public function __construct()
    {
        if (isset(Application::$app)) {
            $this->session = Application::$app->session;
            $this->auth    = Application::$app->auth;
        } else {
            $this->session = new Session();
            $this->auth    = new Auth($this->session);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Validate the request.
     */
    public function validate(): void
    {
        if (!$this->authorize()) {
            $this->failedAuthorization();
        }

        $rules = $this->rules();
        if (empty($rules)) {
            return;
        }

        $validator = new Validator();
        if (Application::$app->db) {
            $validator->setDb(Application::$app->db);
        }

        $validationPassed = $validator->validate($this->getBody(), $rules);

        if (!$validationPassed) {
            $this->failedValidation($validator->errors());
        }
    }

    protected function failedAuthorization(): void
    {
        if (defined('SPARTAN_TESTING') && SPARTAN_TESTING) {
            throw new \RuntimeException("Authorization Failed.");
        }

        $response = Application::$app->response;
        if ($this->isAjax() || str_contains($this->header('Content-Type') ?? '', 'application/json')) {
            $response->json(['error' => 'This action is unauthorized.'], 403);
            $response->send();
            exit;
        }

        $response->setStatusCode(403);
        echo "This action is unauthorized.";
        exit;
    }

    /**
     * Handle failed validation.
     */
    protected function failedValidation(array $errors): void
    {
        $response = Application::$app->response;
        $session = Application::$app->session;

        // Flash old input and errors
        $session->setFlash('validation_errors', $errors);
        $session->setFlash('old_input', $this->getBody());

        if (defined('SPARTAN_TESTING') && SPARTAN_TESTING) {
            throw new \RuntimeException("Validation Failed: " . json_encode($errors));
        }

        if ($this->isAjax() || str_contains($this->header('Content-Type') ?? '', 'application/json')) {
            $response->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors
            ], 422);
            $response->send();
            exit;
        }

        $referer = $this->header('Referer') ?? '/';
        $response->redirect($referer);
        $response->send();
        exit;
    }
}
