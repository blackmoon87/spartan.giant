@AGENTS.md

## Frontend & Backend Translation Directive
- Never hardcode user-facing strings, button texts, titles, form labels, or notices in Blade views (`.blade.php`).
- Never hardcode flash messages (`$this->session->setFlash(...)`) or backend-to-frontend API/JSON response strings (`$this->json(...)`).
- Always use Spartan's translation engine: `trans('group.key', [...])` / `__('group.key', [...])` in PHP and `@lang('group.key')` / `{{ __('group.key') }}` in views.
- Define translation strings in `lang/{locale}/{group}.php`.
