<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Tests\TestCase;
use Spartan\View;

final class ViewTest extends TestCase
{
    private View $view;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = $this->work . '/tpl';
        @mkdir($this->dir . '/layouts', 0777, true);
        @mkdir($this->dir . '/sub', 0777, true);

        file_put_contents($this->dir . '/layouts/main.php', '<html><body>{{content}}</body></html>');
        file_put_contents($this->dir . '/layouts/base.blade.php', '<main>@yield("content")</main>');
        file_put_contents($this->dir . '/plain.php', '<p>Plain <?= htmlspecialchars($name) ?></p>');
        file_put_contents($this->dir . '/partial.blade.php', '<span>partial:{{ $tag }}</span>');
        file_put_contents($this->dir . '/raw.blade.php', '{{ $html }}|{!! $html !!}');
        file_put_contents($this->dir . '/sub/child.php', 'child-view');
        file_put_contents(
            $this->dir . '/page.blade.php',
            '@extends("layouts.base")@section("content")'
            . '<h1>{{ $title }}</h1>'
            . '@if($show)<p>shown</p>@else<p>hidden</p>@endif'
            . '@foreach($items as $item)<li>{{ $item }}</li>@endforeach'
            . '@include("partial")'
            . '@csrf'
            . '@endsection'
        );

        $this->view = new View($this->dir);
    }

    public function test_renders_a_plain_php_view_inside_the_layout(): void
    {
        $html = $this->view->render('plain', ['name' => 'Ada']);

        $this->assertStringContainsString('<html>', $html);
        $this->assertStringContainsString('Plain Ada', $html);
    }

    public function test_render_view_only_skips_the_layout(): void
    {
        $html = $this->view->renderViewOnly('plain', ['name' => 'Ada']);

        $this->assertStringNotContainsString('<html>', $html);
        $this->assertStringContainsString('Plain Ada', $html);
    }

    public function test_blade_layout_inheritance(): void
    {
        $html = $this->view->render('page', ['title' => 'T', 'show' => true, 'items' => [], 'tag' => 'p']);

        $this->assertStringContainsString('<main>', $html);
        $this->assertStringContainsString('<h1>T</h1>', $html);
    }

    public function test_conditional_directives(): void
    {
        $params = ['title' => 'T', 'items' => [], 'tag' => 'p'];

        $this->assertStringContainsString('shown', $this->view->render('page', $params + ['show' => true]));
        $this->assertStringContainsString('hidden', $this->view->render('page', $params + ['show' => false]));
    }

    public function test_foreach_directive(): void
    {
        $html = $this->view->render('page', ['title' => 'T', 'show' => true, 'items' => ['a', 'b'], 'tag' => 'p']);

        $this->assertSame(2, substr_count($html, '<li>'));
    }

    public function test_include_directive(): void
    {
        $html = $this->view->render('page', ['title' => 'T', 'show' => true, 'items' => [], 'tag' => 'X']);

        $this->assertStringContainsString('partial:X', $html);
    }

    public function test_csrf_directive_emits_a_hidden_field(): void
    {
        $html = $this->view->render('page', ['title' => 'T', 'show' => true, 'items' => [], 'tag' => 'p']);

        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    public function test_double_braces_escape_html(): void
    {
        [$escaped] = explode('|', $this->view->renderViewOnly('raw', ['html' => '<script>alert(1)</script>']));

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function test_bang_braces_emit_raw_html(): void
    {
        [, $raw] = explode('|', $this->view->renderViewOnly('raw', ['html' => '<b>bold</b>']));

        $this->assertStringContainsString('<b>bold</b>', $raw);
    }

    public function test_escape_helper(): void
    {
        $this->assertSame('a&quot;b&lt;c', $this->view->escape('a"b<c'));
    }

    public function test_shared_variables_reach_every_view(): void
    {
        file_put_contents($this->dir . '/shared.blade.php', 'v={{ $sharedVal }}');
        $this->view->share('sharedVal', 'GLOBAL');

        $this->assertStringContainsString('v=GLOBAL', $this->view->renderViewOnly('shared'));
    }

    public function test_dot_notation_resolves_subdirectories(): void
    {
        $this->assertStringContainsString('child-view', $this->view->renderViewOnly('sub.child'));
    }

    public function test_path_traversal_is_refused(): void
    {
        $html = $this->view->renderViewOnly('../../../../etc/passwd');

        $this->assertStringContainsString('not found', $html);
        $this->assertStringNotContainsString('root:', $html);
    }

    public function test_illegal_view_names_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->view->renderViewOnly("bad\0name");
    }

    public function test_a_missing_view_renders_a_not_found_notice(): void
    {
        // A missing .blade.php falls through to the plain-PHP resolver, which
        // reports rather than throws; only an explicitly requested Blade file
        // that cannot be compiled raises.
        $this->assertStringContainsString('not found', $this->view->renderViewOnly('no_such_template'));
    }

    public function test_a_missing_blade_template_raises(): void
    {
        $compile = new \ReflectionMethod(View::class, 'compile');
        $compile->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $compile->invoke($this->view, 'no_such_blade_template');
    }

    /** @group slow */
    public function test_templates_recompile_when_the_source_changes(): void
    {
        file_put_contents($this->dir . '/mutable.blade.php', 'v1');
        $first = $this->view->renderViewOnly('mutable');

        sleep(1);
        file_put_contents($this->dir . '/mutable.blade.php', 'v2');
        $second = $this->view->renderViewOnly('mutable');

        $this->assertSame('v1', $first);
        $this->assertSame('v2', $second);
    }

    public function test_the_compiled_cache_key_includes_the_source_path(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/View.php');

        $this->assertStringContainsString('md5($sourcePath)', $source, 'two view roots must not collide');
    }

    public function test_compiled_views_are_written_atomically(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/View.php');

        $this->assertStringContainsString('rename($tmp, $compiledPath)', $source);
    }

    public function test_paths_and_layout_are_configurable(): void
    {
        $this->view->setLayout('main');
        $this->view->setViewsPath($this->dir);

        $this->assertSame($this->dir, $this->view->getViewsPath());
    }

    public function test_reset_state_clears_render_state(): void
    {
        $this->view->render('page', ['title' => 'T', 'show' => true, 'items' => [], 'tag' => 'p']);
        $this->view->resetState();

        $property = new \ReflectionProperty(View::class, 'sections');
        $property->setAccessible(true);

        $this->assertSame([], $property->getValue($this->view));
    }

    public function test_blade_comments_are_stripped(): void
    {
        file_put_contents($this->dir . '/comment.blade.php', '<h1>Hello</h1>{{-- This is a comment --}}<p>World</p>');
        $html = $this->view->renderViewOnly('comment');

        $this->assertStringContainsString('<h1>Hello</h1><p>World</p>', $html);
        $this->assertStringNotContainsString('This is a comment', $html);
    }

    public function test_method_directive_emits_hidden_input(): void
    {
        file_put_contents($this->dir . '/method.blade.php', '@method("PUT")');
        $html = $this->view->renderViewOnly('method');

        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
    }

    public function test_auth_and_guest_directives(): void
    {
        file_put_contents($this->dir . '/auth.blade.php', '@auth<p>Logged In</p>@endauth@guest<p>Guest User</p>@endguest');
        
        // Without user logged in
        $guestHtml = $this->view->renderViewOnly('auth', ['authUser' => null]);
        $this->assertStringNotContainsString('Logged In', $guestHtml);
        $this->assertStringContainsString('Guest User', $guestHtml);
    }

    public function test_raw_php_directive_executes_code_block(): void
    {
        file_put_contents(
            $this->dir . '/raw_php.blade.php',
            '@php $total = 5 * 10; @endphp<p>Total: {{ $total }}</p>'
        );

        $html = $this->view->renderViewOnly('raw_php');

        $this->assertStringContainsString('<p>Total: 50</p>', $html);
    }
}
