<?php

declare(strict_types=1);

namespace Spartan;

class View implements ViewInterface
{
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

        $viewContent = $this->renderCompiled($compiledFile, $params);

        // The compiled template calls $this->extend() while rendering, so this
        // property is not necessarily still null here.
        /** @phpstan-ignore-next-line notIdentical.alwaysFalse */
        if ($this->extendedLayout !== null) {
            $layoutCompiled = $this->compile($this->extendedLayout);
            $viewContent    = $this->renderCompiled($layoutCompiled, $params);
        }

        // Restore previous state
        $this->extendedLayout = $previousLayout;
        $this->sections       = $previousSections;

        return $viewContent;
    }

    /**
     * Compile a Blade template.
     */
    protected function compile(string $view): string
    {
        $viewFile = str_replace('.', '/', $view);
        $sourcePath = $this->viewsPath . "/{$viewFile}.blade.php";
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Blade template not found [{$view}]");
        }

        $viewsConfig = isset(Application::$app) ? (Application::$app->config['views'] ?? []) : [];
        $cacheDir = $viewsConfig['cache_path'] ?? Paths::storage('views');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Key on the FULL source path: two apps (or two view roots) sharing a
        // cache directory previously collided on md5('home').
        $compiledPath = $cacheDir . '/' . md5($sourcePath) . '.php';

        $cacheEnabled = $viewsConfig['cache_enabled'] ?? false;

        if ($cacheEnabled && file_exists($compiledPath)) {
            return $compiledPath;
        }

        $debugMode = getenv('APP_DEBUG') === 'true' || ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if (!file_exists($compiledPath) || $debugMode || filemtime($sourcePath) > filemtime($compiledPath)) {
            $content = file_get_contents($sourcePath);
            $compiledContent = $this->compileString($content);

            // Compile atomically so a concurrent request can never `require`
            // a partially written template.
            $tmp = $compiledPath . '.' . getmypid() . '.tmp';
            if (file_put_contents($tmp, $compiledContent, LOCK_EX) !== false && rename($tmp, $compiledPath)) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($compiledPath, true);
                }
            } else {
                @unlink($tmp);
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
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars(($1) ?? \'\', ENT_QUOTES, \'UTF-8\'); ?>', $content);
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $content);
        $content = preg_replace('/@extends\s*\((.*?)\)/', '<?php $this->extend($1); ?>', $content);
        // Inline section: @section('name', expression) — no @endsection needed
        $content = preg_replace('/@section\s*\(\s*([\'"][^\'"]+[\'"])\s*,\s*(.+?)\s*\)\s*$/m', '<?php $this->sections[trim($1, "\'\\"")] = $2; ?>', $content);
        // Block section: @section('name') ... @endsection
        $content = preg_replace('/@section\s*\((.*?)\)/', '<?php $this->startSection($1); ?>', $content);
        $content = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $content);
        $content = preg_replace('/@yield\s*\((.*?)\)/', '<?php echo $this->yieldContent($1); ?>', $content);
        $content = preg_replace('/@include\s*\((.*?)\)/', '<?php echo $this->include($1, get_defined_vars()); ?>', $content);
        $content = preg_replace('/@csrf/', '<?php echo $this->csrfToken(); ?>', $content);
        
        $content = preg_replace('/@flash\s*\((.*?)\)/', '<?php if($flashMsg = $this->flash($1)): ?>', $content);
        $content = preg_replace('/@endflash/', '<?php endif; ?>', $content);
        $content = preg_replace('/@escape\s*(\((?>[^()]+|(?1))*\))/', '<?php echo $this->escape$1; ?>', $content);
        
        $content = preg_replace('/@if\s*(\((?>[^()]+|(?1))*\))/', '<?php if$1: ?>', $content);
        $content = preg_replace('/@elseif\s*(\((?>[^()]+|(?1))*\))/', '<?php elseif$1: ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        $content = preg_replace('/@foreach\s*(\((?>[^()]+|(?1))*\))/', '<?php foreach$1: ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        $content = preg_replace('/@for\s*(\((?>[^()]+|(?1))*\))/', '<?php for$1: ?>', $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        $content = preg_replace('/@while\s*(\((?>[^()]+|(?1))*\))/', '<?php while$1: ?>', $content);
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        $content = preg_replace('/@empty\s*(\((?>[^()]+|(?1))*\))/', '<?php if(empty$1): ?>', $content);
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        // Custom Authorization Directives (order matters: @cannot before @can to prevent partial match)
        $content = preg_replace('/@cannot\s*(\((?>[^()]+|(?1))*\))/', '<?php if(\Spartan\Gate::denies$1): ?>', $content);
        $content = preg_replace('/@endcannot/', '<?php endif; ?>', $content);
        $content = preg_replace('/@can\s*(\((?>[^()]+|(?1))*\))/', '<?php if(\Spartan\Gate::check$1): ?>', $content);
        $content = preg_replace('/@endcan/', '<?php endif; ?>', $content);

        $content = preg_replace('/@role\s*(\((?>[^()]+|(?1))*\))/', '<?php if(($__user = \Spartan\Gate::resolveUser()) && method_exists($__user, \'hasRole\') && $__user->hasRole$1): ?>', $content);
        $content = preg_replace('/@endrole/', '<?php endif; ?>', $content);

        return $content;
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

        return $renderer();
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

        return $renderer();
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

        return $renderer();
    }
}
