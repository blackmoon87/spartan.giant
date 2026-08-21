<?php

declare(strict_types=1);

namespace Spartan;

interface ViewInterface
{
    public function render(string $view, array $params = []): string;
    public function renderViewOnly(string $view, array $params = []): string;
    public function share(string $key, mixed $value): void;
    public function setLayout(string $layout): void;
    public function getViewsPath(): string;
    public function setViewsPath(string $path): void;
}
