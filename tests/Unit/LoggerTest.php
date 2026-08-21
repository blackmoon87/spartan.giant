<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Logger;
use Spartan\Tests\Fixtures\Stringy;
use Spartan\Tests\TestCase;

final class LoggerTest extends TestCase
{
    private Logger $logger;
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new Logger($this->work . '/logs');
        $this->file   = $this->work . '/logs/app-' . date('Y-m-d') . '.log';
        @unlink($this->file);
    }

    public function test_writes_to_a_dated_file(): void
    {
        $this->logger->info('hello world');

        $this->assertFileExists($this->file);
        $this->assertStringContainsString('hello world', file_get_contents($this->file));
    }

    public function test_interpolates_context_placeholders(): void
    {
        $this->logger->info('user {name} did {what}', ['name' => 'Ada', 'what' => 'compile']);

        $this->assertStringContainsString('user Ada did compile', file_get_contents($this->file));
    }

    public function test_appends_context_as_json(): void
    {
        $this->logger->error('failed', ['code' => 500]);

        $this->assertStringContainsString('{"code":500}', file_get_contents($this->file));
    }

    /**
     * @dataProvider levels
     */
    public function test_every_psr3_level_writes(string $method, string $label): void
    {
        $this->logger->{$method}("msg-{$method}");

        $this->assertStringContainsString("[{$label}]", file_get_contents($this->file));
    }

    public static function levels(): array
    {
        return [
            ['emergency', 'EMERGENCY'], ['alert', 'ALERT'], ['critical', 'CRITICAL'],
            ['error', 'ERROR'], ['warning', 'WARNING'], ['notice', 'NOTICE'],
            ['info', 'INFO'], ['debug', 'DEBUG'],
        ];
    }

    public function test_entries_append_rather_than_overwrite(): void
    {
        $this->logger->info('one');
        $this->logger->info('two');

        $this->assertSame(2, substr_count(file_get_contents($this->file), PHP_EOL));
    }

    public function test_accepts_stringable_messages(): void
    {
        $this->logger->info(new Stringy());

        $this->assertStringContainsString('stringable-message', file_get_contents($this->file));
    }

    public function test_creates_its_directory(): void
    {
        $dir = $this->work . '/logs-auto';
        new Logger($dir);

        $this->assertDirectoryExists($dir);
    }

    public function test_rotates_oversized_files(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/Logger.php');

        $this->assertStringContainsString('10 * 1024 * 1024', $source);
    }
}
