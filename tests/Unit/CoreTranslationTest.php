<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Middlewares\AuthMiddleware;
use Spartan\Router;
use Spartan\Tests\TestCase;
use Spartan\Translation\Translator;
use Spartan\Validator;

/**
 * Core messages come from framework/lang/{locale}/spartan.php and can be
 * overridden by the application's lang/{locale}/spartan.php.
 */
final class CoreTranslationTest extends TestCase
{
    private Translator $previousTranslator;
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTranslator = Translator::getInstance();
        $this->langPath = $this->work . '/core-lang-' . uniqid();
        mkdir($this->langPath . '/en', 0777, true);
        Translator::setInstance(new Translator($this->langPath));
        $this->app->response->reset();
    }

    protected function tearDown(): void
    {
        Translator::setInstance($this->previousTranslator);
        $this->app->response->reset();

        parent::tearDown();
    }

    public function test_bundled_english_messages_need_no_app_files(): void
    {
        $v = new Validator();
        $v->validate(['email' => 'nope', 'name' => 'a'], ['email' => 'required|email', 'name' => 'min:3']);

        $this->assertSame('The email field must be a valid email address.', $v->error('email'));
        $this->assertSame('The name must be at least 3 characters.', $v->error('name'));
    }

    public function test_bundled_arabic_messages_follow_the_locale(): void
    {
        Translator::getInstance()->setLocale('ar');

        $v = new Validator();
        $v->validate(['email' => ''], ['email' => 'required']);

        $this->assertSame('حقل email مطلوب.', $v->error('email'));
        $this->assertSame('غير مصرح.', trans('spartan.http.unauthorized'));
    }

    public function test_app_file_overrides_single_keys_and_attribute_names(): void
    {
        file_put_contents($this->langPath . '/en/spartan.php', "<?php return [
            'validation' => ['required' => 'Please fill in :field.'],
            'attributes' => ['email' => 'your email address'],
        ];");

        $v = new Validator();
        $v->validate(['email' => '', 'age' => 'x'], ['email' => 'required', 'age' => 'integer']);

        $this->assertSame('Please fill in your email address.', $v->error('email'));
        // Keys the app leaves out still come from the framework.
        $this->assertSame('The age field must be an integer.', $v->error('age'));
    }

    public function test_in_rule_lists_the_allowed_values(): void
    {
        $v = new Validator();
        $v->validate(['status' => 'x'], ['status' => 'in:draft,published']);

        $this->assertSame('The status must be one of: draft, published.', $v->error('status'));
    }

    public function test_auth_middleware_answers_ajax_with_json_401(): void
    {
        [, $request, $response] = $this->router('GET', '/dashboard', ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        (new AuthMiddleware())->execute($request, $response);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull($response->getRedirectUrl());
        $this->assertSame(['error' => 'Unauthorized.'], json_decode((string) $response->getContent(), true));
    }

    public function test_auth_middleware_redirects_browsers_to_login(): void
    {
        [, $request, $response] = $this->router('GET', '/dashboard');

        (new AuthMiddleware())->execute($request, $response);

        $this->assertSame('/login', $response->getRedirectUrl());
        $this->assertSame('You must be logged in to access this page.', $this->app->session->getFlash('error'));
    }

    public function test_plain_error_page_is_translated_and_escaped(): void
    {
        file_put_contents($this->langPath . '/en/spartan.php', "<?php return [
            'http' => ['title_403' => '<b>403</b>'],
        ];");

        $html = Router::plainErrorPage('spartan.http.title_403', 'spartan.http.csrf_page');

        $this->assertSame(
            '<h1>&lt;b&gt;403&lt;/b&gt;</h1><p>Invalid or expired security token. Please reload the page and try again.</p>',
            $html
        );
    }
}
