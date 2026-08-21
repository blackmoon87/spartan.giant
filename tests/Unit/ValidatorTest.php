<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Tests\TestCase;
use Spartan\Validator;

final class ValidatorTest extends TestCase
{
    /**
     * @dataProvider rules
     */
    public function test_rule(string $rule, mixed $value, bool $expected, array $extra = []): void
    {
        $data = array_merge(['field' => $value], $extra);

        $this->assertSame(
            $expected,
            (new Validator())->validate($data, ['field' => $rule]),
            sprintf('rule "%s" against %s', $rule, var_export($value, true))
        );
    }

    public static function rules(): array
    {
        return [
            'required accepts a value'          => ['required', 'x', true],
            'required rejects whitespace'       => ['required', '   ', false],
            'required rejects null'             => ['required', null, false],
            'string accepts text'               => ['string', 'abc', true],
            'string rejects an array'           => ['string', ['a'], false],
            'integer accepts digits'            => ['integer', '42', true],
            'integer rejects a decimal'         => ['integer', '4.2', false],
            'numeric accepts a decimal'         => ['numeric', '4.2', true],
            'numeric rejects letters'           => ['numeric', 'abc', false],
            'boolean accepts 1'                 => ['boolean', '1', true],
            'email accepts an address'          => ['email', 'a@b.com', true],
            'email rejects a malformed address' => ['email', 'a@@b', false],
            'url accepts https'                 => ['url', 'https://x.io', true],
            'url rejects free text'             => ['url', 'not a url', false],
            'date accepts iso'                  => ['date', '2026-08-19', true],
            'date rejects prose'                => ['date', 'yesterday', false],
            'alpha accepts letters'             => ['alpha', 'abc', true],
            'alpha rejects digits'              => ['alpha', 'ab1', false],
            'alpha_num accepts both'            => ['alpha_num', 'ab1', true],
            'alpha_num rejects punctuation'     => ['alpha_num', 'ab-1', false],
            'min passes at the boundary'        => ['min:3', 'abc', true],
            'min fails below'                   => ['min:3', 'ab', false],
            'max passes at the boundary'        => ['max:3', 'abc', true],
            'max fails above'                   => ['max:3', 'abcd', false],
            'in accepts a listed option'        => ['in:a,b,c', 'b', true],
            'in rejects an unlisted option'     => ['in:a,b,c', 'z', false],
            'regex accepts a match'             => ['regex:/^[A-Z]{2}-\d{2}$/', 'AB-12', true],
            'regex rejects a mismatch'          => ['regex:/^[A-Z]{2}-\d{2}$/', 'nope', false],
            'nullable skips an empty value'     => ['nullable|email', '', true],
            'nullable still checks a value'     => ['nullable|email', 'bad@@', false],
            'chained rules pass together'       => ['required|email|max:50', 'ada@x.io', true],
            'chained rules fail on one'         => ['required|email|max:5', 'ada@x.io', false],
            'unknown rules are ignored'         => ['bogus_rule', 'x', true],
            'confirmed matches'                 => ['confirmed', 'secret', true,  ['field_confirmation' => 'secret']],
            'confirmed mismatches'              => ['confirmed', 'secret', false, ['field_confirmation' => 'other']],
        ];
    }

    public function test_errors_are_reported_per_field(): void
    {
        $validator = new Validator();
        $validator->validate(['email' => 'bad'], ['email' => 'email']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
        $this->assertStringContainsString('email', (string) $validator->error('email'));
    }

    public function test_only_the_first_error_per_field_is_kept(): void
    {
        $validator = new Validator();
        $validator->validate(['email' => ''], ['email' => 'required|email|min:5']);

        $this->assertCount(1, $validator->errors());
    }

    public function test_a_passing_run_reports_no_errors(): void
    {
        $validator = new Validator();

        $this->assertTrue($validator->validate(['email' => 'a@b.co'], ['email' => 'required|email']));
        $this->assertFalse($validator->fails());
        $this->assertSame([], $validator->errors());
        $this->assertNull($validator->error('email'));
    }

    public function test_unique_rule_detects_an_existing_row(): void
    {
        $validator = new Validator();
        $validator->setDb($this->app->db);

        $this->assertFalse($validator->validate(['email' => 'ada@example.com'], ['email' => 'unique:t_users,email']));
    }

    public function test_unique_rule_accepts_a_fresh_value(): void
    {
        $validator = new Validator();
        $validator->setDb($this->app->db);

        $this->assertTrue($validator->validate(['email' => 'new@example.com'], ['email' => 'unique:t_users,email']));
    }

    public function test_unique_rule_requires_a_connection(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Validator())->validate(['email' => 'x@y.z'], ['email' => 'unique:t_users,email']);
    }

    public function test_multiple_fields_are_validated_independently(): void
    {
        $validator = new Validator();
        $passed = $validator->validate(
            ['name' => '', 'email' => 'a@b.co', 'age' => 'abc'],
            ['name' => 'required', 'email' => 'email', 'age' => 'integer']
        );

        $this->assertFalse($passed);
        $this->assertSame(['name', 'age'], array_keys($validator->errors()));
    }
}
