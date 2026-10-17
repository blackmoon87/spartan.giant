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
            $response->json(['error' => trans('spartan.http.action_unauthorized')], 403);
            throw new HttpResponseException($response);
        }

        // Previously echoed the body and exited without ever sending the
        // 403, so the client received "200 OK".
        $response->setStatusCode(403);
        $response->setContent(htmlspecialchars(trans('spartan.http.action_unauthorized'), ENT_QUOTES, 'UTF-8'));
        throw new HttpResponseException($response);
    }

    /**
     * Handle failed validation.
     */
    protected function failedValidation(array $errors): void
    {
        $response = Application::$app->response;
        $session = Application::$app->session;

        // Flash old input and errors. Password fields are never written to
        // session storage in plaintext.
        $session->setFlash('validation_errors', $errors);
        $session->setFlash('old_input', array_filter(
            $this->getBody(),
            fn($key) => !str_contains(strtolower((string) $key), 'password'),
            ARRAY_FILTER_USE_KEY
        ));

        if (defined('SPARTAN_TESTING') && SPARTAN_TESTING) {
            throw new \RuntimeException("Validation Failed: " . json_encode($errors));
        }

        if ($this->isAjax() || str_contains($this->header('Content-Type') ?? '', 'application/json')) {
            $response->json([
                'message' => trans('spartan.validation.invalid'),
                'errors' => $errors
            ], 422);
            throw new HttpResponseException($response);
        }

        // Back to the form. Without a Referer (stripped by a privacy setting
        // or proxy) fall back to this request's own path — usually the form
        // page itself — instead of '/', where errors() would never be shown.
        $referer = $this->header('Referer') ?? $this->getPath();
        $response->redirect($referer);
        throw new HttpResponseException($response);
    }
}
