This repository is a Spartan PHP application. Read and follow `AGENTS.md` at the
repository root before writing code, and the topic guide in `docs/ai/` that it
names for the kind of code you are writing.

### Mandatory Translation Engine Requirement
Always use Spartan's translation engine for all user-facing text:
- In views (`.blade.php`): `@lang('group.key')` or `{{ __('group.key') }}`. Never hardcode text.
- In backend controllers & services: `trans('group.key', [...])` or `__('group.key', [...])` for flash messages (`$this->session->setFlash(...)`), redirects with messages, and JSON/API responses sent to the frontend.
- Translation strings live in `lang/{locale}/{group}.php`.
