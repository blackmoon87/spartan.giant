# Validation — rules and FormRequests

Source: `framework/src/Validator.php`, `FormRequest.php`.

## FormRequest (preferred for every form)

`src/Controllers/Requests/StorePostRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StorePostRequest extends FormRequest
{
    /** Optional — return false to answer 403. $this->auth / $this->session are available. */
    public function authorize(): bool
    {
        return $this->auth->check();
    }

    public function rules(): array
    {
        return [
            'title'  => 'required|string|min:5|max:200',
            'body'   => 'required|string|min:20',
            'status' => 'required|in:draft,published',
            'email'  => 'nullable|email',
        ];
    }
}
```

Type-hint it in the action — validation runs before the action body:

```php
public function store(StorePostRequest $request): void
{
    $data = $request->getBody();   // validated input (GET + POST + JSON merged)
    …
}
```

On failure the action does not run:

| Request | Response |
|---|---|
| normal form post | redirect to the `Referer` page; `errors()` and `old()` hold the messages and input (password fields are never stored) |
| AJAX / `Content-Type: application/json` | `422` with `{"message": "The given data was invalid.", "errors": {"field": "message"}}` |
| `authorize()` returned false | `403` |

## Inline validation in a controller

`$this->validate()` returns the Validator and does **not** redirect — handle the
result yourself:

```php
$v = $this->validate($this->request->getBody(), [
    'q' => 'required|string|min:2',
]);
if ($v->fails()) {
    return $this->render('search', ['errors' => $v->errors()]);   // ['q' => 'message']
}
```

Validator API: `validate(array $data, array $rules): bool`, `fails(): bool`,
`errors(): array` (first message per field), `error(string $field): ?string`.
Standalone: `$v = new \Spartan\Validator(); $v->setDb(Application::$app->db);`
(`setDb` is needed only for `unique`).

## Rules — this is the complete list

Write rules as a `|`-separated string, or as an **array** (required when a rule
parameter contains `|`, e.g. a regex).

| Rule | Passes when |
|---|---|
| `required` | present and not empty after trimming |
| `nullable` | field absent or empty → **all other rules are skipped** |
| `string` | is a string |
| `integer` | integer value (`"42"`, `42`, `"-5"`) |
| `numeric` | any number (`"4.5"`) |
| `boolean` | one of `true false 1 0 "1" "0" "true" "false"` |
| `email` | valid email address |
| `url` | valid URL |
| `date` | exactly `YYYY-MM-DD` and a real date |
| `alpha` · `alpha_num` | ASCII letters only · ASCII letters and digits only |
| `confirmed` | equals the field `{name}_confirmation` |
| `min:N` · `max:N` | number ≥ / ≤ N when the field also has `integer` or `numeric`; otherwise text length (characters) |
| `in:a,b,c` | equals one of the listed values |
| `regex:/pattern/flags` | matches the full PCRE pattern |
| `unique:table` · `unique:table,column` · `unique:table,column,ignoreId` · `unique:table,column,ignoreId,idColumn` | no other row has this value (`ignoreId` skips the row being updated) |

Examples:

```php
'email'    => 'required|email|unique:users,email',
'email'    => "required|email|unique:users,email,{$userId}",        // update form
'password' => 'required|string|min:8|confirmed',
'age'      => 'nullable|integer|min:18|max:120',
'code'     => ['required', 'regex:/^(AB|CD)-\d{4}$/'],              // array form for |
```

- `N` in `min`/`max` is an integer.
- An unknown rule name throws `InvalidArgumentException`: check spelling
  against the table above.
- Messages are English, one per field, e.g. `The email field must be a valid email address.`
- Nested/array fields (`items.*`) are not supported: validate each value yourself.
