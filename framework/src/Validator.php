<?php

declare(strict_types=1);

namespace Spartan;

use Spartan\Translation\Translator;

class Validator
{
    private array  $errors = [];
    private ?\PDO  $db     = null;

    /**
     * Inject a PDO instance to enable the `unique` rule.
     * Called automatically by Controller::validate().
     */
    public function setDb(?\PDO $db): void
    {
        $this->db = $db;
    }

    /**
     * Run validation rules against a data array.
     *
     * Supported rules (pipe-separated):
     *   required         — field must be present and non-empty
     *   string           — must be a string
     *   integer          — must be a numeric integer
     *   min:N            — string length or numeric value >= N
     *   max:N            — string length or numeric value <= N
     *   email            — must be a valid email address
     *   confirmed        — must match a field named {field}_confirmation
     *   in:a,b,c         — value must be one of the listed options
     *   unique:table,column[,ignoreId[,idColumn]]
     *
     * Rules may also be given as an array — required when a rule's parameter
     * itself contains '|', e.g. ['required', 'regex:/^(a|b)$/'].
     * An unknown rule name throws: a typo must never silently pass.
     *
     * @param array $data   Raw input array (e.g. $this->request->getBody())
     * @param array $rules  ['field' => 'rule1|rule2:param', ...]
     * @return bool         True if all rules pass, false if any fail
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $value    = $data[$field] ?? null;
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            // nullable short-circuit: if the field is absent or empty and 'nullable'
            // is in the rule list, skip all remaining rules for this field.
            if (in_array('nullable', $ruleList, true) && ($value === null || trim((string) $value) === '')) {
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'nullable') {
                    continue; // already handled above, skip in-loop
                }

                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

                match ($ruleName) {
                    'required'  => $this->checkRequired($field, $value),
                    'string'    => $this->checkString($field, $value),
                    'integer'   => $this->checkInteger($field, $value),
                    'boolean'   => $this->checkBoolean($field, $value),
                    'numeric'   => $this->checkNumeric($field, $value),
                    'email'     => $this->checkEmail($field, $value),
                    'url'       => $this->checkUrl($field, $value),
                    'date'      => $this->checkDate($field, $value),
                    'alpha'     => $this->checkAlpha($field, $value),
                    'alpha_num' => $this->checkAlphaNum($field, $value),
                    'confirmed' => $this->checkConfirmed($field, $value, $data),
                    'min'       => $this->checkMin($field, $value, (int) $param, $ruleList),
                    'max'       => $this->checkMax($field, $value, (int) $param, $ruleList),
                    'in'        => $this->checkIn($field, $value, explode(',', (string) $param)),
                    'regex'     => $this->checkRegex($field, $value, (string) $param),
                    'unique'    => $this->checkUnique($field, $value, (string) $param),
                    default     => throw new \InvalidArgumentException(
                        "Validator: unknown rule [{$ruleName}] for field [{$field}]."
                        . (str_contains((string) $param, '/') ? " A regex containing '|' must be passed in array form." : '')
                    ),
                };
            }
        }

        return empty($this->errors);
    }

    /**
     * Return all validation error messages.
     * Format: ['field' => 'First error message for field']
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Return the first error message for a given field.
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Return true if the validation produced any errors.
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    // ─── Private Rule Checks ─────────────────────────────────────────────────

    private function checkRequired(string $field, mixed $value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $this->addError($field, $this->message('required', $field));
        }
    }

    private function checkString(string $field, mixed $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, $this->message('string', $field));
        }
    }

    private function checkInteger(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT) && !ctype_digit((string) $value)) {
            $this->addError($field, $this->message('integer', $field));
        }
    }

    private function checkEmail(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $this->message('email', $field));
        }
    }

    private function checkConfirmed(string $field, mixed $value, array $data): void
    {
        $confirmKey = $field . '_confirmation';
        if (($data[$confirmKey] ?? null) !== $value) {
            $this->addError($field, $this->message('confirmed', $field));
        }
    }

    private function checkMin(string $field, mixed $value, int $min, array $ruleList = []): void
    {
        if ($value === null) return;
        $isNumeric = in_array('integer', $ruleList, true) || in_array('numeric', $ruleList, true);
        if ($isNumeric && is_numeric($value)) {
            if ((float) $value < $min) {
                $this->addError($field, $this->message('min_numeric', $field, ['min' => $min]));
            }
        } else {
            if (mb_strlen((string) $value) < $min) {
                $this->addError($field, $this->message('min_string', $field, ['min' => $min]));
            }
        }
    }

    private function checkMax(string $field, mixed $value, int $max, array $ruleList = []): void
    {
        if ($value === null) return;
        $isNumeric = in_array('integer', $ruleList, true) || in_array('numeric', $ruleList, true);
        if ($isNumeric && is_numeric($value)) {
            if ((float) $value > $max) {
                $this->addError($field, $this->message('max_numeric', $field, ['max' => $max]));
            }
        } else {
            if (mb_strlen((string) $value) > $max) {
                $this->addError($field, $this->message('max_string', $field, ['max' => $max]));
            }
        }
    }

    private function checkIn(string $field, mixed $value, array $allowed): void
    {
        if ($value !== null && !in_array((string) $value, $allowed, true)) {
            $this->addError($field, $this->message('in', $field, ['values' => implode(', ', $allowed)]));
        }
    }

    private function checkBoolean(string $field, mixed $value): void
    {
        $valid = [true, false, 1, 0, '1', '0', 'true', 'false'];
        if ($value !== null && !in_array($value, $valid, true)) {
            $this->addError($field, $this->message('boolean', $field));
        }
    }

    private function checkNumeric(string $field, mixed $value): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, $this->message('numeric', $field));
        }
    }

    private function checkUrl(string $field, mixed $value): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $this->message('url', $field));
        }
    }

    private function checkDate(string $field, mixed $value): void
    {
        if ($value === null) return;
        $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
        if (!$d || $d->format('Y-m-d') !== (string) $value) {
            $this->addError($field, $this->message('date', $field));
        }
    }

    private function checkAlpha(string $field, mixed $value): void
    {
        if ($value !== null && !ctype_alpha((string) $value)) {
            $this->addError($field, $this->message('alpha', $field));
        }
    }

    private function checkAlphaNum(string $field, mixed $value): void
    {
        if ($value !== null && !ctype_alnum((string) $value)) {
            $this->addError($field, $this->message('alpha_num', $field));
        }
    }

    /**
     * Validate that the value matches a regular expression.
     * Rule syntax: regex:/^[A-Z]+$/i
     * The pattern must be a full PCRE pattern including delimiters.
     */
    private function checkRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value === null) {
            return;
        }
        if ($pattern === '' || @preg_match($pattern, '') === false) {
            // Invalid pattern — treat as a developer error, not a user error
            throw new \InvalidArgumentException("Validator: invalid regex pattern [{$pattern}] for field [{$field}].");
        }
        if (!preg_match($pattern, (string) $value)) {
            $this->addError($field, $this->message('regex', $field));
        }
    }

    /**
     * Validate that the value does not already exist in the given DB table/column.
     * Rule syntax: unique:table,column
     *
     * Requires setDb() to have been called with a valid PDO instance.
     * Controller::validate() does this automatically.
     */
    private function checkUnique(string $field, mixed $value, string $param): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        if ($this->db === null) {
            throw new \RuntimeException(
                "Validator: 'unique' rule requires a DB connection. Ensure validate() is called from a Controller or setDb() is called manually."
            );
        }

        [$table, $column, $ignoreId, $idColumn] = array_pad(explode(',', $param, 4), 4, null);
        $column = $column ?: $field;

        if (empty($table)) {
            throw new \InvalidArgumentException("Validator: 'unique' rule requires a table name. Use 'unique:table,column'.");
        }

        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $quote = ($driver === 'sqlite') ? '"' : '`';
        $q = fn(string $identifier) => $quote . str_replace($quote, '', $identifier) . $quote;

        $sql      = "SELECT COUNT(*) as cnt FROM {$q($table)} WHERE {$q($column)} = ?";
        $bindings = [(string) $value];

        // unique:users,email,5 — ignore the record being updated.
        if ($ignoreId !== null && $ignoreId !== '' && strtoupper($ignoreId) !== 'NULL') {
            $sql       .= " AND {$q($idColumn ?: 'id')} <> ?";
            $bindings[] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $count = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);

        if ($count > 0) {
            $this->addError($field, $this->message('unique', $field));
        }
    }

    /**
     * Translated message for a failed rule (spartan.validation.{rule}).
     * :field is the field's display name from spartan.attributes.{field}
     * when the application defines one, else the raw field name.
     */
    private function message(string $rule, string $field, array $params = []): string
    {
        $translator   = Translator::getInstance();
        $attributeKey = "spartan.attributes.{$field}";
        $attribute    = $translator->get($attributeKey);

        return $translator->get(
            "spartan.validation.{$rule}",
            ['field' => $attribute === $attributeKey ? $field : $attribute] + $params
        );
    }

    private function addError(string $field, string $message): void
    {
        // Only store the first error per field
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
