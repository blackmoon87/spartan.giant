# Nimbra Design, Color & Motion System — Complete AI IDE Blueprint

> **Purpose of this Document:**  
> Give this Markdown file to **any AI coding assistant or IDE** (Cursor, Windsurf, Claude Code, Antigravity, Copilot, v0) with the prompt:  
> *"Build [your page/app] following 100% of the design tokens, frosted glass formulas, HTML attribute theming, component classes, and CSS/JS motion choreography specified in `NIMBRA_STYLE_SYSTEM.md`."*

---

## 1. Root HTML Attribute Architecture (Zero-Duplication Dynamic Theming)

All visual state is controlled by **three attributes on the `<html>` element** so the entire application dynamically switches theme, color palette, glass blur intensity, and sidebar layout without duplicating HTML or CSS rules:

```html
<html
  lang="en"
  data-theme="dark"      <!-- "dark" | "light" -->
  data-palette="blue"    <!-- "blue" | "green" | "red" | "yellow" -->
  data-glass="2"         <!-- "2" (26px full glass) | "1" (12px soft glass) | "0" (solid) -->
  class=""               <!-- toggle "is-rail" to collapse sidebar from 256px to 84px -->
>
```

### Google Font Requirement
Always load **Urbanist** (`400..700`) and enforce `font-variant-numeric: tabular-nums` globally so animated counters and financial tables never jitter horizontally:

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400..700&display=swap" rel="stylesheet" />
```

---

## 2. Complete CSS Custom Properties (`:root`, Dark/Light, & 4 Color Palettes)

Copy this exact `:root` and attribute token block into the top of your stylesheet. Every component reads exclusively from these CSS variables:

```css
:root {
  /* Typography Scale */
  --font: "Urbanist", system-ui, -apple-system, "Segoe UI", sans-serif;
  --fs-2xs: 11px;
  --fs-xs: 12px;
  --fs-sm: 12.5px;
  --fs-md: 13.5px;
  --fs-base: 14px;
  --fs-lg: 15px;
  --fs-xl: 17px;
  --fs-2xl: 20px;
  --fs-3xl: 22px;
  --fs-4xl: 26px;
  --fs-display: 34px;

  /* Geometry & Easing */
  --r-sm: 10px;
  --r-md: 14px;
  --r-lg: 20px;
  --r-xl: 26px;
  --ease: cubic-bezier(.22, 1, .36, 1);
  --max: 1480px;
  --gap: 16px;
  --side-w: 256px;
  --side-gap: 14px;

  /* Default Palette: BLUE (Light Mode) */
  --bg: #e9eef8;
  --card: #ffffff9e;
  --card-2: #14285a0a;
  --card-3: #14285a16;
  --panel: #fafcffe6;
  --glass-hi: #fff9;
  --glass-rim: #ffffffbf;
  --glass-top: #fffffff2;
  --blur: 26px;
  --sat: 150%;
  --ink: #0d1630;
  --ink-2: #29344f;
  --muted: #55617c;
  --line: #14285a17;
  --line-2: #14285a29;
  --rim: #14285a29;
  --shadow: 0 1px 2px #14285a0d, 0 14px 34px -18px #14286e42;
  --shadow-lg: 0 2px 6px #14285a14, 0 34px 70px -26px #14286e61;

  /* Primary Accent & Glow */
  --accent: #2f5cf0;
  --accent-from: #4f7bff;
  --accent-to: #2449d8;
  --accent-ink: #fff;
  --accent-hi: #3d68f5;
  --accent-soft: #2f5cf01f;
  --accent-soft-solid: #dbe4ff;
  --accent-text: #2346c4;
  --accent-glow: #2f5cf066;
  --sig: #2f5cf0;
  --sig-text: #2346c4;
  --sig-soft: #2f5cf01f;
  --data: #2f62f0;
  --now: #2f62f0;

  /* Semantic Status Colors */
  --good: #0f7347;
  --warn: #8a5600;
  --bad: #be2639;
  --info: #1563b5;
  --good-soft: #0f8c5a1f;
  --warn-soft: #dc96142b;
  --bad-soft: #d2283c1a;
  --info-soft: #1563b51a;

  /* Tinted KPI Card Surfaces */
  --tile-1: #cddcffb3;
  --tile-2: #c8ecfab3;
  --tile-3: #c4eee0b3;
  --tile-4: #ffe8c4b3;
  --tile-ink: #0d1630;
  --tile-muted: #0d1630ad;

  /* 6-Series Categorical Data Palette */
  --c1: #2f62f0;
  --c2: #0a8fc0;
  --c3: #0e9a78;
  --c4: #c27c0e;
  --c5: #d4503f;
  --c6: #5b8d1f;

  /* Avatar Pastel Hues */
  --h0: #dde4f2;
  --h1: #d3e0ff;
  --h2: #ccecf7;
  --h3: #c9efe2;
  --h4: #fde7c2;
  --h5: #ffd9d4;
  --mono-ink: #0f1830;

  /* Ambient Drifting Aura & Film Grain */
  --aura-a: #6e96ff8c;
  --aura-b: #78c8ff80;
  --aura-c: #82e1d261;
  --aura-d: #a0b9ff73;
  --grain: .05;
  color-scheme: light;
}

/* BLUE (Dark Mode — Default) */
[data-theme="dark"] {
  --bg: #050a18;
  --card: #101a3680;
  --card-2: #96afff0b;
  --card-3: #96afff1a;
  --panel: #0b132ae0;
  --glass-hi: #8caaff14;
  --glass-rim: #96b4ff21;
  --glass-top: #c8d7ff2b;
  --sat: 160%;
  --ink: #eef3ff;
  --ink-2: #cdd6ec;
  --muted: #95a2c2;
  --line: #aabeff17;
  --line-2: #aabeff2b;
  --rim: #aabeff2e;
  --shadow: 0 1px 2px #00000a66, 0 18px 40px -20px #000414cc;
  --shadow-lg: 0 2px 6px #00000a80, 0 34px 80px -26px #000414e6;
  --accent: #3d6bff;
  --accent-from: #6a93ff;
  --accent-to: #2b55f0;
  --accent-ink: #fff;
  --accent-hi: #5580ff;
  --accent-soft: #507dff2e;
  --accent-soft-solid: #16235a;
  --accent-text: #9db9ff;
  --accent-glow: #3c6eff8c;
  --sig: #5b8cff;
  --sig-text: #9db9ff;
  --sig-soft: #5b8cff2b;
  --data: #5b8cff;
  --now: #5b8cff;
  --good: #4fe0a0;
  --warn: #fbc35e;
  --bad: #ff7d8d;
  --info: #6cc8ff;
  --good-soft: #4fe0a024;
  --warn-soft: #fbc35e24;
  --bad-soft: #ff7d8d24;
  --info-soft: #6cc8ff24;
  --tile-1: #4678ff29;
  --tile-2: #38c8f01f;
  --tile-3: #3ddbb01c;
  --tile-4: #f6b84e1c;
  --tile-ink: #eef3ff;
  --tile-muted: #eef3ffb3;
  --c1: #5b8cff;
  --c2: #38c8f0;
  --c3: #3ddbb0;
  --c4: #f6b84e;
  --c5: #ff8a7a;
  --c6: #a9d86a;
  --aura-a: #285fffad;
  --aura-b: #1946dc80;
  --aura-c: #00a0e647;
  --aura-d: #28c8aa29;
  --grain: .06;
  color-scheme: dark;
}

/* GREEN PALETTE (Emerald Forest Glass — Light & Dark) */
html[data-palette="green"] {
  --bg: #e6f4ee; --card: #ffffffa6; --card-2: #0a3c280a; --card-3: #0a3c2816; --panel: #f7fffbe6;
  --ink: #092218; --ink-2: #1e4233; --muted: #4b7060; --line: #0a3c2817; --line-2: #0a3c2829; --rim: #0a3c2829;
  --shadow: 0 1px 2px #0a3c280d, 0 14px 34px -18px #0a462e42;
  --shadow-lg: 0 2px 6px #0a3c2814, 0 34px 70px -26px #0a462e61;
  --accent: #059669; --accent-from: #10b981; --accent-to: #047857; --accent-hi: #10b981;
  --accent-soft: #0596691f; --accent-soft-solid: #d1fae5; --accent-text: #047857; --accent-glow: #05966966;
  --sig: #059669; --sig-text: #047857; --sig-soft: #0596691f; --data: #059669; --now: #059669;
  --tile-1: #bbf7d0b3; --tile-2: #99f6e4b3; --tile-3: #d9f99db3; --tile-4: #fef08ab3;
  --tile-ink: #092218; --tile-muted: #092218ad;
  --c1: #059669; --c2: #0d9488; --c3: #65a30d; --c4: #d97706; --c5: #e11d48; --c6: #0284c7;
  --aura-a: #34d3998c; --aura-b: #2dd4bf80; --aura-c: #a3e63561; --aura-d: #6ee7b773;
}
html[data-palette="green"][data-theme="dark"] {
  --bg: #04120c; --card: #0c261b80; --card-2: #7affc40b; --card-3: #7affc41a; --panel: #071c14e0;
  --glass-hi: #7affc814; --glass-rim: #82ffca21; --glass-top: #b8ffe02b;
  --ink: #ecfff6; --ink-2: #c5e8d8; --muted: #88b09e; --line: #8effce17; --line-2: #8effce2b; --rim: #8effce2e;
  --shadow: 0 1px 2px #000a0566, 0 18px 40px -20px #00140acc;
  --shadow-lg: 0 2px 6px #000a0580, 0 34px 80px -26px #00140ae6;
  --accent: #10b981; --accent-from: #34d399; --accent-to: #059669; --accent-hi: #22c55e;
  --accent-soft: #10b9812e; --accent-soft-solid: #0b3b2a; --accent-text: #6ee7b7; --accent-glow: #10b9818c;
  --sig: #2dd4bf; --sig-text: #6ee7b7; --sig-soft: #10b9812b; --data: #10b981; --now: #10b981;
  --tile-1: #10b98129; --tile-2: #14b8a61f; --tile-3: #84cc161c; --tile-4: #eab3081c;
  --tile-ink: #ecfff6; --tile-muted: #ecfff6b3;
  --c1: #10b981; --c2: #2dd4bf; --c3: #a3e635; --c4: #facc15; --c5: #fb7185; --c6: #38bdf8;
  --aura-a: #10b981ad; --aura-b: #05966980; --aura-c: #14b8a647; --aura-d: #84cc1629;
}

/* RED PALETTE (Crimson Ruby Glass — Light & Dark) */
html[data-palette="red"] {
  --bg: #f8e8ec; --card: #ffffffa6; --card-2: #5a14240a; --card-3: #5a142416; --panel: #fffafbe6;
  --ink: #2c0b12; --ink-2: #4f242e; --muted: #7c525c; --line: #5a142417; --line-2: #5a142429; --rim: #5a142429;
  --shadow: 0 1px 2px #5a14240d, 0 14px 34px -18px #6e142842;
  --shadow-lg: 0 2px 6px #5a142414, 0 34px 70px -26px #6e142861;
  --accent: #e11d48; --accent-from: #f43f5e; --accent-to: #be123c; --accent-hi: #f43f5e;
  --accent-soft: #e11d481f; --accent-soft-solid: #ffe4e6; --accent-text: #be123c; --accent-glow: #e11d4866;
  --sig: #e11d48; --sig-text: #be123c; --sig-soft: #e11d481f; --data: #e11d48; --now: #e11d48;
  --tile-1: #fecdd3b3; --tile-2: #fed7aab3; --tile-3: #fbcfe8b3; --tile-4: #fde68ab3;
  --tile-ink: #2c0b12; --tile-muted: #2c0b12ad;
  --c1: #e11d48; --c2: #ea580c; --c3: #db2777; --c4: #d97706; --c5: #7c3aed; --c6: #059669;
  --aura-a: #fb71858c; --aura-b: #fda4af80; --aura-c: #fdba7461; --aura-d: #f9a8d473;
}
html[data-palette="red"][data-theme="dark"] {
  --bg: #160609; --card: #2e101880; --card-2: #ff8ca10b; --card-3: #ff8ca11a; --panel: #220a10e0;
  --glass-hi: #ff8ca314; --glass-rim: #ff96aa21; --glass-top: #ffc8d22b;
  --ink: #fff0f3; --ink-2: #eccdd3; --muted: #c2959f; --line: #ffaabe17; --line-2: #ffaabe2b; --rim: #ffaabe2e;
  --shadow: 0 1px 2px #0a000266, 0 18px 40px -20px #140004cc;
  --shadow-lg: 0 2px 6px #0a000280, 0 34px 80px -26px #140004e6;
  --accent: #f43f5e; --accent-from: #fb7185; --accent-to: #e11d48; --accent-hi: #ff4d6d;
  --accent-soft: #f43f5e2e; --accent-soft-solid: #4c1120; --accent-text: #fda4af; --accent-glow: #f43f5e8c;
  --sig: #fb7185; --sig-text: #fda4af; --sig-soft: #f43f5e2b; --data: #f43f5e; --now: #f43f5e;
  --tile-1: #f43f5e29; --tile-2: #fb923c1f; --tile-3: #ec48991c; --tile-4: #facc151c;
  --tile-ink: #fff0f3; --tile-muted: #fff0f3b3;
  --c1: #fb7185; --c2: #fb923c; --c3: #f472b6; --c4: #facc15; --c5: #a78bfa; --c6: #34d399;
  --aura-a: #f43f5ead; --aura-b: #e11d4880; --aura-c: #f9731647; --aura-d: #ec489929;
}

/* YELLOW PALETTE (Amber Gold Glass — Light & Dark) */
html[data-palette="yellow"] {
  --bg: #f7f1df; --card: #ffffffa6; --card-2: #543e080a; --card-3: #543e0816; --panel: #fffdf7e6;
  --ink: #291f06; --ink-2: #4d3b13; --muted: #78643a; --line: #543e0817; --line-2: #543e0829; --rim: #543e0829;
  --shadow: 0 1px 2px #543e080d, 0 14px 34px -18px #664b0842;
  --shadow-lg: 0 2px 6px #543e0814, 0 34px 70px -26px #664b0861;
  --accent: #d97706; --accent-from: #f59e0b; --accent-to: #b45309; --accent-hi: #f59e0b;
  --accent-soft: #d977061f; --accent-soft-solid: #fef3c7; --accent-text: #b45309; --accent-glow: #d9770666;
  --sig: #d97706; --sig-text: #b45309; --sig-soft: #d977061f; --data: #d97706; --now: #d97706;
  --tile-1: #fde68ab3; --tile-2: #fed7aab3; --tile-3: #fef08ab3; --tile-4: #bbf7d0b3;
  --tile-ink: #291f06; --tile-muted: #291f06ad;
  --c1: #d97706; --c2: #ea580c; --c3: #65a30d; --c4: #0284c7; --c5: #db2777; --c6: #059669;
  --aura-a: #fbbf248c; --aura-b: #fdba7480; --aura-c: #fde04761; --aura-d: #bef26473;
}
html[data-palette="yellow"][data-theme="dark"] {
  --bg: #141004; --card: #2c220c80; --card-2: #ffd8750b; --card-3: #ffd8751a; --panel: #211907e0;
  --glass-hi: #ffdc7814; --glass-rim: #ffe08221; --glass-top: #ffefb82b;
  --ink: #fffbeb; --ink-2: #ece0c5; --muted: #bca982; --line: #ffdf8e17; --line-2: #ffdf8e2b; --rim: #ffdf8e2e;
  --shadow: 0 1px 2px #0a080066, 0 18px 40px -20px #140f00cc;
  --shadow-lg: 0 2px 6px #0a080080, 0 34px 80px -26px #140f00e6;
  --accent: #f59e0b; --accent-from: #fbbf24; --accent-to: #d97706; --accent-hi: #eab308;
  --accent-soft: #f59e0b2e; --accent-soft-solid: #473008; --accent-text: #fcd34d; --accent-glow: #f59e0b8c;
  --sig: #fbbf24; --sig-text: #fcd34d; --sig-soft: #f59e0b2b; --data: #f59e0b; --now: #f59e0b;
  --tile-1: #f59e0b29; --tile-2: #f973161f; --tile-3: #eab3081c; --tile-4: #10b9811c;
  --tile-ink: #fffbeb; --tile-muted: #fffbebb3;
  --c1: #fbbf24; --c2: #fb923c; --c3: #a3e635; --c4: #38bdf8; --c5: #f472b6; --c6: #34d399;
  --aura-a: #f59e0bad; --aura-b: #d9770680; --aura-c: #eab30847; --aura-d: #84cc1629;
}

/* Glass Intensity Modifiers */
html[data-glass="1"] { --blur: 12px; }
html[data-glass="0"] { --blur: 0px; }
```

---

## 3. The 3 Core Visual Pillars of Nimbra

### Pillar A: Specular Rim Frosted Glass Surface
Never use flat `border: 1px solid`. Nimbra achieves its 3D frosted glass edge using **two inset box-shadows** (`1px` all-around rim `--glass-rim` + `1px` top specular highlight `--glass-top`) combined with a `160deg` diagonal sheen gradient:

```css
.glass, .card, .side, .top__in, .tabbar, .kpi {
  background: linear-gradient(160deg, var(--glass-hi), transparent 46%), var(--card);
  -webkit-backdrop-filter: blur(var(--blur)) saturate(var(--sat));
  backdrop-filter: blur(var(--blur)) saturate(var(--sat));
  box-shadow:
    inset 0 0 0 1px var(--glass-rim),
    inset 0 1px 0 var(--glass-top),
    var(--shadow);
}

/* Elevated Overlays (Drawers, Modals, Popovers, Command Palette) */
.drawer, .modal, .pop, .finder {
  background: linear-gradient(160deg, var(--glass-hi), transparent 40%), var(--panel);
  -webkit-backdrop-filter: blur(30px) saturate(var(--sat));
  backdrop-filter: blur(30px) saturate(var(--sat));
  box-shadow:
    inset 0 0 0 1px var(--glass-rim),
    inset 0 1px 0 var(--glass-top),
    var(--shadow-lg);
}

/* Tinted KPI Tiles (.kpi--1 through .kpi--4) */
.kpi--1 { --tile: var(--tile-1); }
.kpi--2 { --tile: var(--tile-2); }
.kpi--3 { --tile: var(--tile-3); }
.kpi--4 { --tile: var(--tile-4); }
.kpi[class*="kpi--"] {
  background:
    linear-gradient(160deg, var(--glass-hi), transparent 46%),
    linear-gradient(var(--tile), var(--tile)),
    var(--card);
}
```

### Pillar B: 4-Orb Ambient Drifting Aura Background (`.aura`)
Behind all glass surfaces sits `.aura` (`position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden`), containing 4 large elliptical radial gradients drifting on asynchronous loops (`38s`, `46s`, `52s`, `44s`):

```html
<div class="aura" aria-hidden="true">
  <i class="aura__a"></i>
  <i class="aura__b"></i>
  <i class="aura__c"></i>
  <i class="aura__d"></i>
</div>
```

```css
.aura { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.aura i { position: absolute; border-radius: 50%; }
.aura i::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50%;
  animation: 38s ease-in-out infinite alternate drift;
}
.aura__a { width: 120vmax; height: 40vmax; top: -6vmax; left: -30vmax; rotate: -22deg; }
.aura__a::before { background: radial-gradient(ellipse at 50% 50%, var(--aura-a), transparent 64%); }

.aura__b { width: 110vmax; height: 34vmax; bottom: -10vmax; right: -36vmax; rotate: -22deg; }
.aura__b::before {
  background: radial-gradient(ellipse at 50% 50%, var(--aura-b), transparent 64%);
  animation-duration: 46s !important;
  animation-delay: -12s !important;
}

.aura__c { width: 60vmax; height: 50vmax; top: -22vmax; right: -18vmax; }
.aura__c::before {
  background: radial-gradient(ellipse at 50% 50%, var(--aura-c), transparent 62%);
  animation-duration: 52s !important;
  animation-delay: -30s !important;
}

.aura__d { width: 70vmax; height: 50vmax; bottom: -36vmax; left: 18vmax; }
.aura__d::before {
  background: radial-gradient(ellipse at 50% 50%, var(--aura-d), transparent 62%);
  animation-duration: 44s !important;
  animation-delay: -20s !important;
}

@keyframes drift {
  0%   { transform: translate(0, 0) scale(1); }
  50%  { transform: translate(4vmax, 3vmax) scale(1.08); }
  100% { transform: translate(-3vmax, 5vmax) scale(0.96); }
}
```

### Pillar C: Inline SVG Fractal Noise Grain Overlay (`.app::after`)
Adds tactile optical texture over the aura and glass cards without external image dependencies:

```css
.app::after {
  content: "";
  position: fixed;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  opacity: var(--grain);
  mix-blend-mode: overlay;
  background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
```

---

## 4. Layout Shell & Micro-Component Recipes

1. **Floating Sidebar (`.side`) + Main Column (`.col`)**:
   - `.side` is `position: fixed; left: 14px; top: 14px; bottom: 14px; width: var(--side-w); border-radius: 26px; z-index: 20`.
   - `.col` has `margin-left: calc(var(--side-w) + var(--side-gap) * 2)`.
   - Adding `html.is-rail` sets `--side-w: 84px`, hides text labels, and centers sidebar icons.
2. **Sticky Floating Topbar (`.top` / `.top__in`)**:
   - `.top` is `position: sticky; top: 0; z-index: 30; padding: 14px 24px 0 10px`.
   - `.top__in` has `border-radius: 20px; min-height: 62px; padding: 10px 10px 10px 18px`.
3. **Primary Gradient Button (`.btn--primary`) & Brand Logo Mark (`.mark`)**:
   ```css
   .btn--primary, .mark {
     background: linear-gradient(135deg, var(--accent-from), var(--accent) 48%, var(--accent-to));
     color: var(--accent-ink);
     box-shadow: inset 0 1px 0 #ffffff73, 0 10px 22px -10px var(--accent-glow);
   }
   ```
4. **Glowing Display Numbers (`.big.glow-num`)**:
   ```css
   .big { font-size: var(--fs-display); letter-spacing: -0.025em; font-weight: 600; line-height: 1.05; }
   .glow-num { text-shadow: 0 0 28px var(--accent-glow); }
   ```
5. **Breathing Live Dot (`.live`)**:
   ```css
   .live {
     width: 8px; height: 8px; border-radius: 50%;
     background: var(--good); display: inline-block; position: relative;
   }
   .live::after {
     content: ""; position: absolute; inset: -4px; border-radius: 50%;
     background: var(--good); animation: 2s ease-in-out infinite breathe;
   }
   @keyframes breathe { 0%, 100% { opacity: 0.1; } 50% { opacity: 0.45; } }
   ```
6. **Funnel Staircase Bar (`.stair__bar`) with `@property --stair-c`**:
   ```css
   @property --stair-c {
     syntax: "<color>";
     inherits: true;
     initial-value: #3d6bff;
   }
   .stair { transition: --stair-c 0.45s cubic-bezier(.65, 0, .35, 1); }
   .stair__bar {
     background: linear-gradient(
       180deg,
       color-mix(in oklab, var(--stair-c) 92%, #fff) 0%,
       var(--stair-c) 30%,
       color-mix(in oklab, var(--stair-c) 45%, transparent) 100%
     );
     box-shadow: 0 -10px 30px -12px var(--stair-c), inset 0 1px 0 #ffffff73;
   }
   ```

---

## 5. Signature Motion & Animation Engine (JavaScript Patterns)

### Pattern 1: Dual-Speed Liquid Sliding Pill (`clip-path: inset`)
Instead of animating `left`/`width`, Nimbra stretches a full-size overlay `.snav__pill` or `.seg__thumb` using `clip-path: inset(top right bottom left round Rpx)`. The **leading edge** moves faster (`340ms`, `easeOutCubic`) than the **trailing edge** (`520ms`, `easeInOutCubic`), creating a liquid elastic stretch:

```javascript
function moveLiquidPill(pillEl, targetEl, { animate = true, radius = 14 } = {}) {
  if (!pillEl || !targetEl) return;
  const parent = pillEl.parentElement;
  const sw = parent.offsetWidth, sh = parent.offsetHeight;
  const next = {
    l: targetEl.offsetLeft,
    r: sw - (targetEl.offsetLeft + targetEl.offsetWidth),
    t: targetEl.offsetTop,
    b: sh - (targetEl.offsetTop + targetEl.offsetHeight)
  };
  const fmt = (s) => `inset(${s.t}px ${s.r}px ${s.b}px ${s.l}px round ${radius}px)`;
  const prev = pillEl.__rect;
  pillEl.__rect = next;
  pillEl.style.opacity = '1';
  if (!animate || !prev) { pillEl.style.clipPath = fmt(next); return; }

  const start = performance.now(), leadDur = 340, trailDur = 520;
  const forward = Math.abs(next.t - prev.t) > Math.abs(next.l - prev.l) ? next.t > prev.t : next.l > prev.l;
  const out3 = (x) => 1 - Math.pow(1 - x, 3);
  const inOut3 = (x) => x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;

  const tick = (now) => {
    const dt = now - start;
    const pLead = out3(Math.min(1, dt / leadDur));
    const pTrail = inOut3(Math.min(1, dt / trailDur));
    pillEl.style.clipPath = fmt({
      l: prev.l + (next.l - prev.l) * (forward ? pTrail : pLead),
      r: prev.r + (next.r - prev.r) * (forward ? pLead : pTrail),
      t: prev.t + (next.t - prev.t) * (forward ? pTrail : pLead),
      b: prev.b + (next.b - prev.b) * (forward ? pLead : pTrail)
    });
    if (dt < trailDur) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}
```

### Pattern 2: Circular Clip-Path View Transition (Theme & Palette Switching)
When toggling `data-theme` (`dark`/`light`) or `data-palette` (`blue`/`green`/`red`/`yellow`), expand a circular reveal from the clicked button's exact coordinates:

```javascript
function transitionRootAttribute(attr, value, triggerEl) {
  const root = document.documentElement;
  const apply = () => { root.setAttribute(attr, value); };
  if (!document.startViewTransition || !triggerEl) { apply(); return; }

  const r = triggerEl.getBoundingClientRect();
  const x = r.left + r.width / 2, y = r.top + r.height / 2;
  const maxR = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));

  document.startViewTransition(apply).ready.then(() => {
    root.animate(
      { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${maxR}px at ${x}px ${y}px)`] },
      { duration: 480, easing: 'cubic-bezier(.65, 0, .35, 1)', pseudoElement: '::view-transition-new(root)' }
    );
  });
}
```

### Pattern 3: Split-Word & Character Reveal (`.word > .ch`)
Page headings (`<h1>`) split into `<span class="word"><span class="ch">...</span></span>` and animate each character upward with a `6deg` rotation untilt:

```javascript
function animateSplitHeading(el) {
  const text = el.dataset.text || el.textContent;
  el.innerHTML = text.trim().split(/\s+/).map(w =>
    `<span class="word">${[...w].map(c => `<span class="ch">${c}</span>`).join('')}</span>`
  ).join(' ');
  el.querySelectorAll('.ch').forEach((ch, i) => {
    ch.animate(
      [{ transform: 'translateY(115%) rotate(6deg)' }, { transform: 'translateY(0) rotate(0deg)' }],
      { duration: 780, delay: i * 20, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', fill: 'both' }
    );
  });
}
```

### Pattern 4: KPI Tilt-Pop Entrance & Self-Drawing SVG Sparklines
- **KPI Cards**: Animate from `opacity: 0, translateY(38px) rotate(±2.5deg) scale(0.95)` to `1, 0, 0deg, 1` with spring overshoot `cubic-bezier(0.34, 1.56, 0.64, 1)`.
- **SVG Sparklines**: Measure `const len = path.getTotalLength()` and animate `strokeDashoffset` from `len` to `0` over `1150ms`.
- **Magnetic Buttons (`.magnetic`)**: On `pointermove`, offset button by `(dx * 0.22, dy * 0.28)` and reset on `pointerleave`.

---

## 6. How to Apply ANY Custom Color (Universal `--seed` Color Engine)

If a developer or AI IDE wants to use **any brand color** (e.g., Purple `#8b5cf6`, Cyan `#06b6d4`, Sunset Orange `#f97316`, Rose `#ec4899`, or a live `<input type="color">` picker) without manually calculating 40 hex codes, use the **Single-Variable `--seed` Engine** below.

### How It Works
1. Set `data-palette="custom"` on `<html>`.
2. Pass **one single CSS variable** `--seed: #YOUR_HEX_COLOR;` on `<html>` (via CSS or inline `document.documentElement.style.setProperty('--seed', '#8b5cf6')`).
3. Native CSS `color-mix(in oklab, ...)` automatically derives the dark/light background canvas, translucent frosted glass cards, specular 1px glass rims, glowing primary buttons, drifting aura orbs, and KPI tiles from `--seed`.

### Copy-Paste Universal `--seed` CSS Rules (Supports Both Dark & Light Mode)

```css
/* ==========================================================================
   UNIVERSAL SINGLE-COLOR ENGINE: html[data-palette="custom"]
   Change ONLY --seed to any hex/rgb/oklch color!
   ========================================================================== */
html[data-palette="custom"] {
  --seed: #8b5cf6; /* <-- Replace with ANY brand color (or set via JS) */

  /* Light Mode Derived Surfaces */
  --bg: color-mix(in oklab, var(--seed) 10%, #f2f4f8);
  --card: color-mix(in oklab, #ffffff 68%, transparent);
  --card-2: color-mix(in oklab, var(--seed) 6%, transparent);
  --card-3: color-mix(in oklab, var(--seed) 12%, transparent);
  --panel: color-mix(in oklab, var(--seed) 4%, #ffffffeb);
  --glass-hi: #ffffff99;
  --glass-rim: #ffffffbf;
  --glass-top: #fffffff2;

  /* Light Mode Ink & Lines */
  --ink: color-mix(in oklab, var(--seed) 28%, #0a0e17);
  --ink-2: color-mix(in oklab, var(--seed) 22%, #262e3d);
  --muted: color-mix(in oklab, var(--seed) 18%, #556070);
  --line: color-mix(in oklab, var(--seed) 14%, transparent);
  --line-2: color-mix(in oklab, var(--seed) 22%, transparent);
  --rim: color-mix(in oklab, var(--seed) 22%, transparent);
  --shadow: 0 1px 2px color-mix(in oklab, var(--seed) 8%, transparent),
            0 14px 34px -18px color-mix(in oklab, var(--seed) 28%, transparent);
  --shadow-lg: 0 2px 6px color-mix(in oklab, var(--seed) 12%, transparent),
               0 34px 70px -26px color-mix(in oklab, var(--seed) 40%, transparent);

  /* Light Mode Accent & Glow */
  --accent: color-mix(in oklab, var(--seed) 88%, #000000);
  --accent-from: color-mix(in oklab, var(--seed) 82%, #ffffff);
  --accent-to: color-mix(in oklab, var(--seed) 76%, #000000);
  --accent-hi: var(--seed);
  --accent-ink: #ffffff;
  --accent-soft: color-mix(in oklab, var(--seed) 15%, transparent);
  --accent-soft-solid: color-mix(in oklab, var(--seed) 18%, #ffffff);
  --accent-text: color-mix(in oklab, var(--seed) 78%, #000000);
  --accent-glow: color-mix(in oklab, var(--seed) 45%, transparent);
  --sig: var(--accent);
  --sig-text: var(--accent-text);
  --sig-soft: var(--accent-soft);
  --data: var(--accent);
  --now: var(--accent);

  /* Light Mode KPI Tiles & Auras */
  --tile-1: color-mix(in oklab, var(--seed) 22%, #ffffffb3);
  --tile-2: color-mix(in oklab, var(--seed) 14%, #e0f2feb3);
  --tile-3: color-mix(in oklab, var(--seed) 14%, #dcfce7b3);
  --tile-4: color-mix(in oklab, var(--seed) 14%, #fef3c7b3);
  --tile-ink: var(--ink);
  --tile-muted: color-mix(in oklab, var(--ink) 68%, transparent);
  --c1: var(--accent);
  --aura-a: color-mix(in oklab, var(--seed) 52%, transparent);
  --aura-b: color-mix(in oklab, var(--seed) 38%, #38bdf840);
  --aura-c: color-mix(in oklab, var(--seed) 30%, #34d39940);
  --aura-d: color-mix(in oklab, var(--seed) 42%, transparent);
}

/* Dark Mode Derived Surfaces from --seed */
html[data-palette="custom"][data-theme="dark"] {
  --bg: color-mix(in oklab, var(--seed) 14%, #03050a);
  --card: color-mix(in oklab, var(--seed) 18%, #0a0f1d80);
  --card-2: color-mix(in oklab, var(--seed) 8%, transparent);
  --card-3: color-mix(in oklab, var(--seed) 15%, transparent);
  --panel: color-mix(in oklab, var(--seed) 16%, #070b16e6);
  --glass-hi: color-mix(in oklab, var(--seed) 12%, #ffffff0d);
  --glass-rim: color-mix(in oklab, var(--seed) 24%, #ffffff14);
  --glass-top: color-mix(in oklab, var(--seed) 30%, #ffffff24);

  /* Dark Mode Ink & Lines */
  --ink: color-mix(in oklab, var(--seed) 10%, #f4f6fb);
  --ink-2: color-mix(in oklab, var(--seed) 18%, #cdd4e4);
  --muted: color-mix(in oklab, var(--seed) 24%, #8e99b0);
  --line: color-mix(in oklab, var(--seed) 14%, #ffffff0a);
  --line-2: color-mix(in oklab, var(--seed) 24%, #ffffff12);
  --rim: color-mix(in oklab, var(--seed) 26%, #ffffff14);
  --shadow: 0 1px 2px #00000080, 0 18px 40px -20px #000000cc;
  --shadow-lg: 0 2px 6px #00000099, 0 34px 80px -26px #000000e6;

  /* Dark Mode Accent & Glow */
  --accent: var(--seed);
  --accent-from: color-mix(in oklab, var(--seed) 76%, #ffffff);
  --accent-to: color-mix(in oklab, var(--seed) 82%, #000000);
  --accent-hi: color-mix(in oklab, var(--seed) 88%, #ffffff);
  --accent-ink: #ffffff;
  --accent-soft: color-mix(in oklab, var(--seed) 22%, transparent);
  --accent-soft-solid: color-mix(in oklab, var(--seed) 32%, #090d16);
  --accent-text: color-mix(in oklab, var(--seed) 62%, #ffffff);
  --accent-glow: color-mix(in oklab, var(--seed) 58%, transparent);
  --sig: color-mix(in oklab, var(--seed) 85%, #ffffff);
  --sig-text: var(--accent-text);
  --sig-soft: var(--accent-soft);
  --data: var(--sig);
  --now: var(--sig);

  /* Dark Mode KPI Tiles & Auras */
  --tile-1: color-mix(in oklab, var(--seed) 22%, transparent);
  --tile-2: color-mix(in oklab, var(--seed) 12%, #38bdf81a);
  --tile-3: color-mix(in oklab, var(--seed) 12%, #34d3991a);
  --tile-4: color-mix(in oklab, var(--seed) 12%, #fbbf241a);
  --tile-ink: var(--ink);
  --tile-muted: color-mix(in oklab, var(--ink) 72%, transparent);
  --c1: var(--sig);
  --aura-a: color-mix(in oklab, var(--seed) 68%, transparent);
  --aura-b: color-mix(in oklab, var(--seed) 48%, transparent);
  --aura-c: color-mix(in oklab, var(--seed) 32%, #00a0e633);
  --aura-d: color-mix(in oklab, var(--seed) 24%, #28c8aa22);
}
```

---

### How to Apply Any Color in HTML or JavaScript (3 Ways)

#### Way 1: Pure HTML (Inline `--seed` on `<html>`)
To make a page **Royal Purple (`#8b5cf6`)**, **Cyan (`#06b6d4`)**, or **Orange (`#f97316`)**, just set `data-palette="custom"` and `--seed` on `<html>`:

```html
<html lang="en" data-theme="dark" data-palette="custom" style="--seed: #8b5cf6">
```

#### Way 2: Live JavaScript Function (`applyCustomColor(hex)`)
Call this function from any button or color picker to switch the entire app to any hex color with a circular View Transition reveal:

```javascript
function applyCustomColor(hexColor, triggerEl) {
  const root = document.documentElement;
  const apply = () => {
    root.dataset.palette = 'custom';
    root.style.setProperty('--seed', hexColor);
  };
  if (!document.startViewTransition || !triggerEl) {
    apply();
    return;
  }
  const r = triggerEl.getBoundingClientRect();
  const x = r.left + r.width / 2, y = r.top + r.height / 2;
  const maxR = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
  document.startViewTransition(apply).ready.then(() => {
    root.animate(
      { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${maxR}px at ${x}px ${y}px)`] },
      { duration: 480, easing: 'cubic-bezier(.65, 0, .35, 1)', pseudoElement: '::view-transition-new(root)' }
    );
  });
}
```

#### Way 3: Manual Token Recipe Table (If Generating a Static Named Palette)
If an AI IDE is asked to write a static named palette (e.g. `html[data-palette="purple"]`), use this exact token formula relative to the brand hue `H`:

| Token | Dark Mode Formula | Light Mode Formula |
|---|---|---|
| `--bg` | Hue `H`, Saturation `70%`, Lightness `5%` (`#050a18` scale) | Hue `H`, Saturation `45%`, Lightness `94%` |
| `--card` | Hue `H`, Saturation `55%`, Lightness `14%`, Alpha `50%` | `#ffffff` with Alpha `62%–65%` |
| `--panel` | Hue `H`, Saturation `60%`, Lightness `10%`, Alpha `88%` | Hue `H`, Saturation `60%`, Lightness `99%`, Alpha `90%` |
| `--glass-hi` | Hue `H`, Lightness `75%`, Alpha `8%` | `#ffffff` with Alpha `60%` |
| `--glass-rim` | Hue `H`, Lightness `78%`, Alpha `13%` | `#ffffff` with Alpha `75%` |
| `--glass-top` | Hue `H`, Lightness `88%`, Alpha `17%` | `#ffffff` with Alpha `95%` |
| `--accent` | Brand primary color (`L ~ 58%`) | Darker brand primary (`L ~ 48%`) |
| `--accent-from` | Lighter highlight (`L ~ 70%`) | Brand primary (`L ~ 58%`) |
| `--accent-to` | Deeper shade (`L ~ 46%`) | Deep shade (`L ~ 40%`) |
| `--aura-a` | Brand primary at `68%` alpha | Brand primary at `55%` alpha |


---

## 11. Complete 15-Page Architecture & Side Menu Navigation

Nimbra provides 15 distinct functional analytics views, routed client-side via URL hash (`#/route`):

| Page Route | View Component | Companion Stylesheet | Primary Data & Interactions |
|---|---|---|---|
| `#/overview` | `overview-DBuUBU35.js` | `overview-TnYJyOBf.css` | Executive MRR dashboard, NRR ring, plan breakdown, trial funnels, high-risk clinic watchlist, live event feed, and road to \$60k. |
| `#/reports` | `reports-pb6CkA4-.js` | `reports-CkRD1E5L.css` | Reports library (7 reports), custom report builder, scheduled deliveries, star/unstar filters, and country breakdown. |
| `#/funnels` | `funnels-D4eBFVat.js` | `funnels-CgxRU56B.css` | Step-by-step conversion pipeline (`pricing → trial → booking → invite → paid`), channel filter breakdown, period comparison. |
| `#/cohorts` | `cohorts-DjCRkAdo.js` | `cohorts-jBv-yeFG.css` | 12-month retention heatmap matrix, clinic retention vs. revenue retention toggle, and baseline comparison charts. |
| `#/insights` | `insights-Bkb-81GU.js` | `insights-CgZsxXJR.css` | Rule-based AI anomaly & risk detection cards, pin/dismiss controls, and natural language revenue querying console. |
| `#/users` | `users-DxvAsHrh.js` | `users-i4As-hkr.css` | Granular user activity table, seat allocation, role management, and authentication logs. |
| `#/accounts` | `accounts-Bb4C-ITA.js` | `accounts-DfC_6a0I.css` | Complete dental clinic registry (24 accounts), MRR contribution, health scores (0-100), and plan filters. |
| `#/segments` | `segments-B_RDCU9L.js` | `segments-B7Wz2_ns.css` | Dynamic customer cohort rule builder (e.g. "High value clinic with > 2 sites"), rule condition editors. |
| `#/activity` | `activity-BfFMcx2j.js` | `activity-CHLPm8lN.css` | Real-time WebSocket-style streaming event bus with live filter chips (bookings, payments, invites, cancellations). |
| `#/integrations`| `integrations-B4WfbOcE.js`| `integrations-Dn0rjnv9.css`| Third-party dental software connectors (Dentally, Exact, Carestream, Stripe, Xero) with sync status switches. |
| `#/automations` | `automations-Bl8Bv_gc.js` | `automations-Y-6VEjUn.css` | Event-driven trigger workflows (e.g. "When health score < 40, send Slack alert and trigger check-in email"). |
| `#/campaigns`   | `campaigns-BkWaBvIW.js`   | `campaigns-CN6jam3P.css`   | Email & SMS recall marketing campaigns, delivery rates, open rates, and patient re-booking attribution. |
| `#/teams`       | `teams-CosHYjPf.js`       | `teams-BI95e3HX.css`       | Organization member roster, RBAC roles (Admin, Editor, Viewer), pending invitations, and audit log. |
| `#/settings`    | `settings-DiPM6gQF.js`    | `settings-vw8pyd3l.css`    | Organization preferences, currency formatting, timezone, webhook endpoints, and API keys. |
| `#/billing`     | `billing-DKC40_cC.js`     | `billing-BIHt1lRY.css`     | Scale plan subscription management, 4.6M/4.0M tracked events quota bar, invoices history, and payment methods. |

---

## 12. Local Deployment & Verification

To launch and explore the full animated 15-page application with the dynamic color palette switcher:

```bash
cd nimbra-clone
python3 -m http.server 8920
```

Open `http://localhost:8920/app.html` in your browser. All 15 navigation routes, chart canvases, GSAP timeline springs, interactive filters, and the bottom-right theme/palette dock will execute with 100% native fidelity.
