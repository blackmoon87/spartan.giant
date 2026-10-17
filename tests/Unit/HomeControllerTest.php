<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use App\Models\User;
use Spartan\Request;
use Spartan\Tests\TestCase;
use Spartan\Translation\Translator;

/**
 * The demo site pages, rendered from the project's real views and lang files.
 */
final class HomeControllerTest extends TestCase
{
    private string $previousViewsPath;
    private Translator $previousTranslator;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2);
        $this->previousViewsPath = $this->app->view->getViewsPath();
        $this->app->view->setViewsPath($root . '/src/Views');
        $this->previousTranslator = Translator::getInstance();
        Translator::setInstance(new Translator($root . '/lang'));
        $this->app->response->reset();
    }

    protected function tearDown(): void
    {
        $this->app->view->setViewsPath($this->previousViewsPath);
        Translator::setInstance($this->previousTranslator);
        $this->app->response->reset();

        parent::tearDown();
    }

    public function test_home_page_is_translated_and_reports_the_database(): void
    {
        $html = (string) $this->dispatch('GET', '/');

        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html);
        $this->assertStringContainsString(trans('site.home.heading'), $html);
        $this->assertStringContainsString(trans('site.home.db_connected'), $html);
        $this->assertStringContainsString('href="/css/app.css"', $html);
        $this->assertStringNotContainsString('site.home.', $html, 'every key resolves');
    }

    public function test_arabic_locale_renders_right_to_left(): void
    {
        Translator::getInstance()->setRtlLocales(['ar']);
        Translator::getInstance()->setLocale('ar');

        $html = (string) $this->dispatch('GET', '/contact');

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString(trans('site.contact.submit', [], 'ar'), $html);
    }

    public function test_every_page_key_exists_in_every_locale(): void
    {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($lines as $key => $value) {
                $keys = array_merge($keys, is_array($value) ? $flatten($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"]);
            }
            return $keys;
        };

        $root = dirname(__DIR__, 2) . '/lang';
        $this->assertSame(
            $flatten(require $root . '/en/site.php'),
            $flatten(require $root . '/ar/site.php')
        );
    }

    public function test_valid_contact_submission_flashes_and_redirects(): void
    {
        $this->dispatch('POST', '/contact', [
            'name'    => 'Jane',
            'email'   => 'jane@example.com',
            'message' => 'Hello there',
        ]);

        $this->assertSame('/contact', $this->app->response->getRedirectUrl());
        $this->assertSame(
            trans('site.contact.sent', ['name' => 'Jane']),
            $this->app->session->getFlash('success')
        );
    }

    public function test_invalid_contact_submission_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Validation Failed.*email/');

        $this->dispatch('POST', '/contact', [
            'name'    => 'Jane',
            'email'   => 'not-an-email',
            'message' => 'Hello there',
        ]);
    }

    public function test_error_pages_are_translated(): void
    {
        $html = $this->app->view->render('error_404');
        $this->assertStringContainsString(trans('site.errors.404_title'), $html);
        $this->assertStringContainsString(trans('site.errors.404_body'), $html);

        $html = $this->app->view->render('error_403', ['message' => trans('spartan.http.csrf_page')]);
        $this->assertStringContainsString(trans('spartan.http.csrf_page'), $html);
    }

    public function test_user_count_is_zero_without_a_users_table(): void
    {
        $hasUsers = (bool) $this->app->db
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")
            ->fetch();

        if ($hasUsers) {
            $this->app->db->exec('ALTER TABLE users RENAME TO users_backup');
        }
        try {
            $this->assertSame(0, (new User())->getCount());
        } finally {
            if ($hasUsers) {
                $this->app->db->exec('ALTER TABLE users_backup RENAME TO users');
            }
        }
    }

    /** Run a request through the app's real routes and return the action's result. */
    private function dispatch(string $method, string $uri, array $post = []): mixed
    {
        $this->request($method, $uri, $post);
        $app = $this->app;
        require __DIR__ . '/../../routes/web.php';
        $app->router->setRequest(new Request());
        $app->router->setResponse($app->response);
        return $app->router->resolve();
    }
}
