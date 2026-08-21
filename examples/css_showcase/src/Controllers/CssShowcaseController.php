<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Controller;

class CssShowcaseController extends Controller
{
    public function index(): string
    {
        return $this->render('showcase/index', [
            'title' => 'Spartan CSS Engine Hub — High-End Design Systems',
            'activeEngine' => 'Hub',
            'stats' => [
                'rps' => '1,827',
                'boot' => '1.8 ms',
                'memory' => '4.5 MB',
                'tests' => '102/102',
            ]
        ]);
    }

    public function tailwind(): string
    {
        return $this->render('showcase/tailwind', [
            'title' => 'Tailwind CSS High-End Component Suite',
            'activeEngine' => 'Tailwind',
            'metrics' => [
                ['label' => 'Total Revenue', 'value' => '$128,450', 'change' => '+14.2%', 'isUp' => true],
                ['label' => 'Active Subscribers', 'value' => '3,420', 'change' => '+8.1%', 'isUp' => true],
                ['label' => 'Server Latency', 'value' => '1.2 ms', 'change' => '-18.5%', 'isUp' => true],
                ['label' => 'Error Rate', 'value' => '0.001%', 'change' => '-0.02%', 'isUp' => true],
            ],
            'projects' => [
                [
                    'name' => 'Spartan Core v2.0',
                    'category' => 'Framework Core',
                    'status' => 'Production',
                    'progress' => 94,
                    'assignees' => ['Alexei Volkov', 'Sarah Chen'],
                    'budget' => '$45,000',
                ],
                [
                    'name' => 'AI Code Assistant Engine',
                    'category' => 'Agentic Systems',
                    'status' => 'In Review',
                    'progress' => 78,
                    'assignees' => ['Michael Scott'],
                    'budget' => '$32,000',
                ],
                [
                    'name' => 'Zero-Dependency ORM Dialects',
                    'category' => 'Database Engine',
                    'status' => 'Production',
                    'progress' => 100,
                    'assignees' => ['Dev User', 'Alexei Volkov'],
                    'budget' => '$18,500',
                ],
            ],
            'pricing' => [
                [
                    'name' => 'Developer',
                    'price' => '$0',
                    'period' => 'forever',
                    'desc' => 'Pure zero-dependency PHP MVC framework for high performance projects.',
                    'features' => ['Zero Dependencies', '2 ms Cold Boot', 'Blade View Engine', 'SQLite & MySQL Dialects'],
                    'popular' => false,
                ],
                [
                    'name' => 'Enterprise Pro',
                    'price' => '$99',
                    'period' => '/ month',
                    'desc' => 'Dedicated background workers, Redis clustering, and 24/7 priority SLAs.',
                    'features' => ['All Developer Features', 'Redis Cluster Support', 'Async Worker Daemon', 'Priority Security Audits', 'RBAC Attribute Inspection'],
                    'popular' => true,
                ],
            ]
        ]);
    }

    public function openprops(): string
    {
        return $this->render('showcase/openprops', [
            'title' => 'Open Props Design System & Token Suite',
            'activeEngine' => 'Open Props',
            'tokens' => [
                'Typography' => ['--font-sans' => 'system-ui', '--font-mono' => 'monospace', '--font-size-5' => '2rem'],
                'Elevation' => ['--shadow-1' => 'Subtle Drop', '--shadow-3' => 'Medium Elevation', '--shadow-5' => 'Hero Floating'],
                'Gradients' => ['--gradient-1' => 'Sunset Magenta', '--gradient-3' => 'Ocean Cyan', '--gradient-8' => 'Neon Emerald'],
                'Radii' => ['--radius-1' => '2px', '--radius-3' => '8px', '--radius-round' => '1e5px'],
            ]
        ]);
    }

    public function vanilla(): string
    {
        return $this->render('showcase/vanilla', [
            'title' => 'Awwwards / Godly Style Glassmorphic Showcase',
            'activeEngine' => 'Vanilla Glassmorphism',
            'highlights' => [
                ['title' => 'Ultra-Low TTFB', 'badge' => '2 ms', 'desc' => 'Sub-millisecond routing and direct view compilation without autoloader drag.'],
                ['title' => 'Zero Vulnerability Surface', 'badge' => '0 Deps', 'desc' => 'No vendor package tree means zero third-party supply chain risks.'],
                ['title' => 'Awwwards-Grade UI', 'badge' => '60 FPS', 'desc' => 'Hardware-accelerated CSS backdrop filters, glowing borders, and fluid grids.'],
            ]
        ]);
    }

    public function interactive(): string
    {
        return $this->render('showcase/interactive', [
            'title' => 'Interactive Animations, Image Sliders & Motion Showcase',
            'activeEngine' => 'Interactive & Motion',
            'slides' => [
                [
                    'title' => 'Next-Gen Agentic AI Coding Engine',
                    'tagline' => 'Autonomous Code Generation & Multi-File Orchestration',
                    'gradient' => 'linear-gradient(135deg, #4f46e5, #06b6d4)',
                    'tag' => 'AI Architecture',
                ],
                [
                    'title' => 'Zero-Dependency PHP 8.1+ Framework Kernel',
                    'tagline' => 'Sub-Millisecond Routing & 2ms Cold Boot Performance',
                    'gradient' => 'linear-gradient(135deg, #ec4899, #8b5cf6)',
                    'tag' => 'High Performance',
                ],
                [
                    'title' => 'Hardware-Accelerated CSS3 Glassmorphism',
                    'tagline' => 'Awwwards & Godly Aesthetic Motion Design Standard',
                    'gradient' => 'linear-gradient(135deg, #10b981, #3b82f6)',
                    'tag' => 'Motion & Design',
                ],
            ],
            'gallery' => [
                ['title' => 'Neural Vector Search System', 'category' => 'AI Engine', 'aspect' => '16/9', 'color' => '#6366f1'],
                ['title' => 'Distributed Job Queue Daemon', 'category' => 'Infrastructure', 'aspect' => '16/9', 'color' => '#10b981'],
                ['title' => 'Blade Template Compiler', 'category' => 'View Layer', 'aspect' => '16/9', 'color' => '#ec4899'],
                ['title' => 'Dialect SQL Auto-Translator', 'category' => 'Database', 'aspect' => '16/9', 'color' => '#f59e0b'],
            ]
        ]);
    }
}
