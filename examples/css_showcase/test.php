<?php

declare(strict_types=1);

namespace App\Examples\CssShowcase;

use Spartan\Application;
use Spartan\Request;
use Spartan\Response;

define('SPARTAN_TESTING', true);

require_once __DIR__ . '/../../framework/src/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

$testsPassed = 0;
$testsFailed = 0;

function assertCondition(bool $condition, string $testName, string $details = ''): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$testName}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $testsFailed++;
        echo "  [FAIL] {$testName}" . ($details ? " ({$details})" : "") . "\n";
    }
}

echo "═══════════════════════════════════════════════════════════════════\n";
echo "   SPARTAN CSS MULTI-SUPPORT SHOWCASE TEST SUITE                  \n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$config = require __DIR__ . '/config/config.php';
$app = new Application($config);

require_once __DIR__ . '/routes/web.php';

// Test 1: Hub Index Route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$app->router->setRequest(new Request());
$htmlHub = $app->router->resolve();
assertCondition(
    str_contains($htmlHub, 'Multi-CSS Framework Engine Hub') && str_contains($htmlHub, 'Vanilla Glassmorphism'),
    '1. CSS Engine Hub Homepage Rendering',
    'Rendered hub layout with glassmorphic cards'
);

// Test 2: Tailwind CSS Integration Route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/tailwind';
$app->router->setRequest(new Request());
$htmlTailwind = $app->router->resolve();
assertCondition(
    str_contains($htmlTailwind, 'Enterprise Dashboard Component Suite') && str_contains($htmlTailwind, 'Total Revenue') && str_contains($htmlTailwind, 'Developer'),
    '2. Tailwind CSS Layout & High-End Component Suite Compilation',
    'Stats grid, interactive project table & pricing cards compiled'
);

// Test 3: Open Props CSS Integration Route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/openprops';
$app->router->setRequest(new Request());
$htmlOpenProps = $app->router->resolve();
assertCondition(
    str_contains($htmlOpenProps, 'Open Props Tokens Engine') && str_contains($htmlOpenProps, 'Typography Tokens') && str_contains($htmlOpenProps, '--shadow-5'),
    '3. Open Props Custom Properties & Shadow Tokens Compilation',
    'Open Props token matrix evaluated inside Blade directives'
);

// Test 4: Vanilla Glassmorphism Engine Route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/vanilla';
$app->router->setRequest(new Request());
$htmlVanilla = $app->router->resolve();
assertCondition(
    str_contains($htmlVanilla, 'Awwwards & Godly Aesthetic Standard') && str_contains($htmlVanilla, 'Ultra-High Precision Glassmorphic Design System'),
    '4. Vanilla Glassmorphism Awwwards-Grade UI Rendering',
    'Rendered glowing glass cards, backdrop filters & code snippets'
);

// Test 5: Asset Helper CSS Path Generation
$assetCssPath = asset('css/app.css');
assertCondition(
    $assetCssPath === '/css/app.css',
    '5. Asset Helper CSS Resolution',
    'Resolved relative path: ' . $assetCssPath
);

// Test 6: Interactive Carousel Slider, Gallery & Shimmer Keyframes Route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/interactive';
$app->router->setRequest(new Request());
$htmlInteractive = $app->router->resolve();
assertCondition(
    str_contains($htmlInteractive, 'Interactive Sliders, Carousels & CSS Keyframe Motion') && str_contains($htmlInteractive, 'slider-container') && str_contains($htmlInteractive, 'shimmer-box'),
    '6. Interactive Image Slider Carousel & Shimmer Skeleton Compilation',
    'Rendered auto-advancing slider, aspect-ratio gallery & shimmer keyframe loaders'
);

echo "\n───────────────────────────────────────────────────────────────────\n";
echo " TEST RESULTS: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "───────────────────────────────────────────────────────────────────\n\n";

if ($testsFailed > 0) {
    exit(1);
}
