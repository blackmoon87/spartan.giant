<?php

declare(strict_types=1);

namespace Spartan;

class View implements ViewInterface
{
    /**
     * Bump whenever compileString() output changes: it is part of the
     * compiled-file key, so templates compiled by an older compiler are never
     * reused just because their source file did not change.
     */
    private const COMPILER_VERSION = '4';

    private string $layout = 'main';
    private string $viewsPath;
    private array $sections = [];
    private ?string $activeSection = null;
    private ?string $extendedLayout = null;
    private array $shared = [];

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?: Paths::base('src/Views');
    }

    /**
     * Reset transient per-render state (worker mode).
     * Shared variables and the views path are configuration and are kept.
     */
    public function resetState(): void
    {
        $this->sections       = [];
        $this->activeSection  = null;
        $this->extendedLayout = null;
    }

    /**
     * Share a variable globally with all views.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Get the current views directory path.
     */
    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    /**
     * Set the current views directory path.
     */
    public function setViewsPath(string $path): void
    {
        $this->viewsPath = rtrim($path, '/\\');
    }

    /**
     * Set the current rendering layout name.
     */
    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Render the view merged with the layout content.
     */
    public function render(string $view, array $params = []): string
    {
        $view = str_replace('.', '/', $view);
        if (!preg_match('#^[a-zA-Z0-9_/]+$#', $view)) {
            throw new \InvalidArgumentException(
                "Invalid view name [{$view}]. Only alphanumeric characters, underscores, and forward slashes are allowed."
            );
        }

        $params = array_merge($this->shared, $params);
        if (!array_key_exists('authUser', $params)) {
            $params['authUser'] = Gate::resolveUser();
        }

        $bladeFile = $this->viewsPath . "/{$view}.blade.php";
        if (file_exists($bladeFile)) {
            return $this->compileAndRender($view, $params);
        }

        $viewContent = $this->renderOnlyView($view, $params);
        $layoutContent = $this->layoutContent($params);
        return str_replace('{{content}}', $viewContent, $layoutContent);
    }

    /**
     * Render only the view template content without wrapping it in a layout.
     */
    public function renderViewOnly(string $view, array $params = []): string
    {
        $view = str_replace('.', '/', $view);
        if (!preg_match('#^[a-zA-Z0-9_/]+$#', $view)) {
            throw new \InvalidArgumentException(
                "Invalid view name [{$view}]. Only alphanumeric characters, underscores, and forward slashes are allowed."
            );
        }

        $params = array_merge($this->shared, $params);
        if (!array_key_exists('authUser', $params)) {
            $params['authUser'] = Gate::resolveUser();
        }

        $bladeFile = $this->viewsPath . "/{$view}.blade.php";
        if (file_exists($bladeFile)) {
            return $this->compileAndRender($view, $params);
        }

        return $this->renderOnlyView($view, $params);
    }

    /**
     * Compile a Blade file and render it with nested layout support.
     * Shared by render() and renderViewOnly() to eliminate duplication.
     */
    private function compileAndRender(string $view, array $params): string
    {
        $compiledFile = $this->compile($view);

        // Backup state for nested rendering protection
        $previousLayout   = $this->extendedLayout;
        $previousSections = $this->sections;

        $this->extendedLayout = null;
        $this->sections       = [];

        try {
            $viewContent = $this->renderCompiled($compiledFile, $params);

            // The compiled template calls $this->extend() while rendering, so this
            // property is not necessarily still null here.
            /** @phpstan-ignore-next-line notIdentical.alwaysFalse */
            if ($this->extendedLayout !== null) {
                $layoutCompiled = $this->compile($this->extendedLayout);
                $viewContent    = $this->renderCompiled($layoutCompiled, $params);
            }

            return $viewContent;
        } finally {
            // Restore previous state — also when the template throws.
            $this->extendedLayout = $previousLayout;
            $this->sections       = $previousSections;
        }
    }

    /**
     * Compile a Blade template.
     */
    protected function compile(string $view): string
    {
        $viewFile = str_replace('.', '/', $view);
        $sourcePath = $this->viewsPath . "/{$viewFile}.blade.php";

        $viewsConfig = isset(Application::$app) ? (Application::$app->config['views'] ?? []) : [];
        $cacheDir = $viewsConfig['cache_path'] ?? Paths::storage('views');

        // Key on the FULL source path: two apps (or two view roots) sharing a
        // cache directory previously collided on md5('home').
        $compiledPath = $cacheDir . '/' . md5(self::COMPILER_VERSION . $sourcePath) . '.php';

        // cache_enabled (production): an existing compiled file is trusted as
        // is — no stat of the source template on every render. Run
        // `php spartan view:clear` after deploying template changes.
        if (($viewsConfig['cache_enabled'] ?? false) && is_file($compiledPath)) {
            return $compiledPath;
        }

        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Blade template not found [{$view}]");
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Development: recompile whenever the source is newer than its compiled file.
        if (!file_exists($compiledPath) || filemtime($sourcePath) > filemtime($compiledPath)) {
            $content = file_get_contents($sourcePath);
            $compiledContent = $this->compileString($content);

            // Compile atomically so a concurrent request can never `require` a partially written template.
            $tmp = $compiledPath . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $compiledContent, LOCK_EX) !== false) {
                if (!@rename($tmp, $compiledPath)) {
                    // On Windows, if destination is open by another thread, copy fallback
                    @copy($tmp, $compiledPath);
                }
                @unlink($tmp);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($compiledPath, true);
                }
            } elseif (!file_exists($compiledPath)) {
                throw new \RuntimeException("Unable to write compiled view cache for [{$view}].");
            }
        }

        return $compiledPath;
    }

    /**
     * Translate Blade directives to PHP.
     */
    protected function compileString(string $content): string
    {
        // Strip Blade comments: {{-- comment --}} before processing variable interpolations
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // Escapes: "@@if" prints "@if", "@{{ x }}" prints "{{ x }}" (JS frameworks).
        $content = str_replace(['@@', '@{{'], ["\0SPARTAN_AT\0", "\0SPARTAN_LB\0"], $content);

        // An '@' between plain text and a domain is an email address, not a
        // directive: "info@authority.com" used to compile "@auth" into an
        // unclosed if() and fatal. Text glued to a directive ("yes@endif")
        // has no domain after it and still compiles.
        $content = preg_replace(
            '/(?<=[A-Za-z0-9._%+-])@(?=[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}\b)/',
            "\0SPARTAN_AT\0",
            $content
        );

        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars(($1) ?? \'\', ENT_QUOTES, \'UTF-8\'); ?>', $content);
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $content);
        $content = preg_replace('/@extends\s*\((.*?)\)/', '<?php $this->extend($1); ?>', $content);
        // Inline section: @section('name', expression) — no @endsection needed
        // Uses callback to pre-compile any Blade output directives (e.g. @lang,
        // {{ }}) found in the value to their PHP expression equivalents, preventing
        // nested PHP open/close tags when @lang is processed later in the pipeline.
        $content = preg_replace_callback(
            '/@section\s*\(\s*([\'"][^\'"]+[\'"])\s*,\s*(.+?)\s*\)\s*$/m',
            [$this, 'compileInlineSection'],
            $content
        );
        // Block section: @section('name') ... @endsection
        $content = preg_replace('/@section\s*\((.*?)\)/', '<?php $this->startSection($1); ?>', $content);
        $content = preg_replace('/@endsection\b/', '<?php $this->endSection(); ?>', $content);
        $content = preg_replace('/@yield\s*\((.*?)\)/', '<?php echo $this->yieldContent($1); ?>', $content);
        $content = preg_replace('/@include\s*\((.*?)\)/', '<?php echo $this->include($1, get_defined_vars()); ?>', $content);
        $content = preg_replace('/@csrf\b/', '<?php echo $this->csrfToken(); ?>', $content);
        $content = preg_replace('/@method\s*\(\s*[\'"]([A-Za-z]+)[\'"]\s*\)/', '<input type="hidden" name="_method" value="$1">', $content);
        
        $content = preg_replace('/@flash\s*\((.*?)\)/', '<?php if($flashMsg = $this->flash($1)): ?>', $content);
        $content = preg_replace('/@endflash\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/@escape\s*(\((?>[^()]+|(?1))*\))/', '<?php echo $this->escape$1; ?>', $content);
        
        $content = preg_replace('/@if\s*(\((?>[^()]+|(?1))*\))/', '<?php if$1: ?>', $content);
        $content = preg_replace('/@elseif\s*(\((?>[^()]+|(?1))*\))/', '<?php elseif$1: ?>', $content);
        $content = preg_replace('/@else\b/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif\b/', '<?php endif; ?>', $content);

        $content = preg_replace('/@foreach\s*(\((?>[^()]+|(?1))*\))/', '<?php foreach$1: ?>', $content);
        $content = preg_replace('/@endforeach\b/', '<?php endforeach; ?>', $content);

        $content = preg_replace('/@for\s*(\((?>[^()]+|(?1))*\))/', '<?php for$1: ?>', $content);
        $content = preg_replace('/@endfor\b/', '<?php endfor; ?>', $content);

        $content = preg_replace('/@while\s*(\((?>[^()]+|(?1))*\))/', '<?php while$1: ?>', $content);
        $content = preg_replace('/@endwhile\b/', '<?php endwhile; ?>', $content);

        $content = preg_replace('/@empty\s*(\((?>[^()]+|(?1))*\))/', '<?php if(empty$1): ?>', $content);
        $content = preg_replace('/@endempty\b/', '<?php endif; ?>', $content);

        // Authentication Directives (@auth, @guest)
        $content = preg_replace('/@auth\b/', '<?php if(\Spartan\Gate::resolveUser() !== null): ?>', $content);
        $content = preg_replace('/@endauth\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/@guest\b/', '<?php if(\Spartan\Gate::resolveUser() === null): ?>', $content);
        $content = preg_replace('/@endguest\b/', '<?php endif; ?>', $content);

        // Custom Authorization Directives (order matters: @cannot before @can to prevent partial match)
        $content = preg_replace('/@cannot\s*(\((?>[^()]+|(?1))*\))/', '<?php if(\Spartan\Gate::denies$1): ?>', $content);
        $content = preg_replace('/@endcannot\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/@can\s*(\((?>[^()]+|(?1))*\))/', '<?php if(\Spartan\Gate::check$1): ?>', $content);
        $content = preg_replace('/@endcan\b/', '<?php endif; ?>', $content);

        $content = preg_replace('/@role\s*(\((?>[^()]+|(?1))*\))/', '<?php if(($__user = \Spartan\Gate::resolveUser()) && method_exists($__user, \'hasRole\') && $__user->hasRole$1): ?>', $content);
        $content = preg_replace('/@endrole\b/', '<?php endif; ?>', $content);

        // Translation Directives
        // @lang('key') → HTML-escaped translated string
        $content = preg_replace('/@lang\s*\((.+?)\)/', '<?php echo htmlspecialchars(trans($1), ENT_QUOTES, \'UTF-8\'); ?>', $content);

        // Form Helper Directives with balanced parenthesis support
        // @selected($condition) → selected="selected"
        $content = preg_replace('/@selected\s*(\((?>[^()]+|(?1))*\))/', '<?php if$1: ?> selected="selected"<?php endif; ?>', $content);
        // @checked($condition) → checked="checked"
        $content = preg_replace('/@checked\s*(\((?>[^()]+|(?1))*\))/', '<?php if$1: ?> checked="checked"<?php endif; ?>', $content);
        // @disabled($condition) → disabled="disabled"
        $content = preg_replace('/@disabled\s*(\((?>[^()]+|(?1))*\))/', '<?php if$1: ?> disabled="disabled"<?php endif; ?>', $content);

        // Raw PHP block directive: @php ... @endphp
        $content = preg_replace('/@php\b(.*?)@endphp\b/s', '<?php $1 ?>', $content);

        return str_replace(["\0SPARTAN_AT\0", "\0SPARTAN_LB\0"], ['@', '{{'], $content);
    }

    /**
     * Compile an inline @section('name', expression) match.
     *
     * Pre-compiles Blade output directives (@lang, {{ }}) found in the value
     * to their PHP expression equivalents so the later @lang compilation pass
     * does not produce nested PHP open/close tags.
     *
     * @param  array $m  Regex match: [1] = quoted name, [2] = value expression
     */
    private function compileInlineSection(array $m): string
    {
        $name  = $m[1];
        $value = $m[2];

        // A plain expression — @section('title', $post['title']) — is escaped,
        // as in Laravel: @yield prints sections raw, so an unescaped value
        // from user input was XSS. @lang / {{ }} values are escaped below.
        if (!preg_match('/@lang\s*\(|\{\{/', $value)) {
            $key = var_export(trim($name, '\'"'), true);
            return '<?php $this->sections[' . $key . '] = htmlspecialchars((string) (' . $value . '), ENT_QUOTES, \'UTF-8\'); ?' . '>';
        }

        // @lang('key') → expression form (not echo form)
        $value = preg_replace(
            '/@lang\s*\((.+?)\)/',
            'htmlspecialchars(trans($1), ENT_QUOTES, \'UTF-8\')',
            $value
        );

        // {{ $expr }} → expression form (not echo form)
        $value = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            'htmlspecialchars(($1) ?? \'\', ENT_QUOTES, \'UTF-8\')',
            $value
        );

        // The name is a quoted literal: resolve it now. The previous runtime
        // trim($name, "'"") emitted an unescaped quote — every inline
        // @section compiled to a ParseError.
        $key = var_export(trim($name, '\'"'), true);
        return '<?php $this->sections[' . $key . '] = ' . $value . '; ?' . '>';
    }

    /**
     * Run compiled template inside local Closure.
     */
    protected function renderCompiled(string $compiledFile, array $params): string
    {
        $renderer = \Closure::bind(function() use ($compiledFile, $params) {
            extract($params, EXTR_SKIP);
            ob_start();
            require $compiledFile;
            return (string) ob_get_clean();
        }, $this, static::class);

        return $this->runIsolated($renderer);
    }

    /**
     * Run a template renderer, discarding any output buffers it left open if
     * it throws: otherwise partial template output leaked into the error
     * page, and in worker mode the buffers piled up request after request.
     */
    private function runIsolated(\Closure $renderer): string
    {
        $level = ob_get_level();
        try {
            return $renderer();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $this->activeSection = null;
            throw $e;
        }
    }

    /**
     * Section layout helpers.
     */
    public function extend(string $layout): void
    {
        $this->extendedLayout = trim($layout, '\'"');
    }

    public function startSection(string $name): void
    {
        $this->activeSection = trim($name, '\'"');
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->activeSection === null) {
            return;
        }
        $this->sections[$this->activeSection] = ob_get_clean();
        $this->activeSection = null;
    }

    public function yieldContent(string $name): string
    {
        $name = trim($name, '\'"');
        return $this->sections[$name] ?? '';
    }

    public function include(string $view, array $params = [], array $localVars = []): string
    {
        $view = trim($view, '\'"');
        unset($localVars['this']);
        return $this->renderViewOnly($view, array_merge($localVars, $params));
    }

    /**
     * Expose a secure CSRF hidden input field for forms.
     */
    public function csrfToken(): string
    {
        $token = isset(Application::$app) ? (Application::$app->session->get('_csrf_token') ?? '') : '';
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    /**
     * Retrieve the current CSRF token string.
     */
    public function csrfTokenValue(): string
    {
        return isset(Application::$app) ? (Application::$app->session->get('_csrf_token') ?? '') : '';
    }

    /**
     * Escape output HTML content safely.
     */
    public function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Fetch a temporary flash message.
     */
    public function flash(string $key, ?string $default = null): ?string
    {
        $key = trim($key, '\'"');
        return isset(Application::$app) ? Application::$app->session->getFlash($key, $default) : $default;
    }

    /**
     * Load layout contents.
     */
    protected function layoutContent(array $params = []): string
    {
        $renderer = \Closure::bind(function() use ($params) {
            extract($params, EXTR_SKIP);
            $layoutPath = $this->viewsPath . "/layouts/{$this->layout}.php";
            if (!file_exists($layoutPath)) {
                return '{{content}}';
            }
            ob_start();
            include $layoutPath;
            return (string) ob_get_clean();
        }, $this, static::class);

        return $this->runIsolated($renderer);
    }

    /**
     * Load page-specific view contents.
     */
    protected function renderOnlyView(string $view, array $params): string
    {
        if (!preg_match('#^[a-zA-Z0-9_/]+$#', $view)) {
            throw new \InvalidArgumentException(
                "Invalid view name [{$view}]. Only alphanumeric characters, underscores, and forward slashes are allowed."
            );
        }

        $renderer = \Closure::bind(function() use ($view, $params) {
            extract($params, EXTR_SKIP);
            $viewPath = $this->viewsPath . "/{$view}.php";

            $viewsBase = realpath($this->viewsPath);
            $resolvedPath = realpath($viewPath);
            if ($resolvedPath === false || $viewsBase === false || !str_starts_with($resolvedPath, $viewsBase)) {
                return "View [{$view}] not found.";
            }

            ob_start();
            include $resolvedPath;
            return (string) ob_get_clean();
        }, $this, static::class);

        return $this->runIsolated($renderer);
    }
}
