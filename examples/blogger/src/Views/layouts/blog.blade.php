<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Spartan Blogger' }}</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- HTMX & Alpine.js -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(20, 26, 46, 0.65);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --primary: #a855f7;
            --accent: #38bdf8;
            --gradient: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(168, 85, 247, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.15) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(56, 189, 248, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(11, 15, 25, 0.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
        }

        .nav-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        nav ul { display: flex; list-style: none; gap: 2rem; align-items: center; }
        nav a { color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: color 0.3s; }
        nav a:hover { color: var(--text-primary); }

        main { flex: 1; max-width: 1100px; width: 100%; margin: 0 auto; padding: 2.5rem 2rem; }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
        }

        .article-card {
            background: rgba(15, 21, 37, 0.7);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s, border-color 0.3s;
        }
        .article-card:hover { transform: translateY(-3px); border-color: rgba(168, 85, 247, 0.4); }

        .btn {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 0.7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        footer { border-top: 1px solid var(--border-color); padding: 2rem 0; text-align: center; color: var(--text-secondary); font-size: 0.85rem; margin-top: auto; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <a href="{{ url('/') }}" class="logo">✍️ Spartan Blogger</a>
            <nav>
                <ul>
                    <li><a href="{{ url('/') }}">Home</a></li>
                    <li><a href="{{ url('/author/posts') }}" style="color: var(--primary); font-weight: 700;">Publishing Portal</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        @flash('success')
            <div class="alert">{{ $flashMsg }}</div>
        @endflash

        @yield('content')
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} Spartan Blogger. Zero-dependency PHP MVC Content Platform.</p>
    </footer>
</body>
</html>
