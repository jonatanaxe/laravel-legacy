{{--
    Single source of the design tokens.
    Included in the <head> of EVERY page (welcome, auth, app).
    No build for this file: the legacy CSS ships inline.

    Direction: "engineering dossier" - neutral, dense, clear type,
    a single accent, hairlines instead of shadows, mono for technical
    data. No decorative gradients, no floating cards.

    RULE: no literal values in the rules below. Everything comes from tokens.
    If a new color/size is needed, it goes into :root with a semantic
    name - not straight into the selector.

    Tokens:
      color   --ink --body --muted
              --surface --surface-2 --surface-3 --bg
              --line --line-strong --line-hover
              --brand --brand-600 --brand-soft --brand-ring
              --ok* --warn* --danger* (color + soft + line + ring)
              --ink-900 --ink-800 --ink-line ... (dark surface)
      space   --sp-1..--sp-11 (4,8,12,16,20,24,32,40,56,72,88)
      radius  --radius-xs --radius-sm --radius --radius-lg --radius-pill
      type    --text-2xs --text-xs --text-sm --text-base --text-md
              --text-lg --text-xl --text-display --text-hero
              --leading-tight --leading-body
              shadow  --shadow-xs --shadow-sm --shadow-lg
      sombra  --shadow-xs --shadow-sm --shadow-lg
--}}
<style>
    :root {
        /* ---- color: text ---- */
        --ink: #0d1526;
        --body: #4a5568;
        --muted: #7b8798;
        --text-on-brand: #ffffff;

        /* ---- color: surface ---- */
        --surface: #ffffff;
        --surface-2: #f7f8fa;
        --surface-3: #edeff4;
        --bg: #f4f5f7;

        /* ---- color: border ---- */
        --line: #e6e9ef;
        --line-strong: #cfd5e0;
        --line-hover: #aeb7c7;

        /* ---- color: accent ---- */
        --brand: #1d4ed8;
        --brand-600: #1e40af;
        --brand-soft: #eef3ff;
        --brand-ring: rgba(29, 78, 216, .13);

        /* ---- color: semantic ---- */
        --ok: #067647;
        --ok-soft: #ecfdf3;
        --ok-line: #a6e9c3;
        --warn: #b54708;
        --warn-soft: #fffaeb;
        --warn-line: #f2d59b;
        --danger: #b42318;
        --danger-soft: #fef3f2;
        --danger-line: #f2cfcb;
        --danger-ring: rgba(180, 35, 24, .12);

        /* ---- color: workflow stages (CFD bands) ----
              Single ramp: neutral = queue, dark blue = active work, light
              blue = waiting on someone, green = left the system. Color is a
              secondary cue - every band has a written label next to it. */
        --stage-backlog: #cdd5e2;
        --stage-doing: #1d4ed8;
        --stage-review: #93a9ea;
        --stage-done: #067647;

        /* ---- color: dark surface (hero, ladder section, footer) ---- */
        --ink-900: #0a0f1a;
        --ink-800: #131a28;
        --ink-line: rgba(255, 255, 255, .1);
        --ink-line-soft: rgba(255, 255, 255, .055);
        --dark-body: #a9b4c5;
        --dark-muted: #8fa0b8;
        --dark-text: #e2e8f0;
        --dark-accent: #9db9ff;
        --dark-ok: #34d399;
        --dark-ok-bright: #6ee7b7;
        --dark-ok-text: #c9f5e1;
        --dark-fill: rgba(255, 255, 255, .035);
        --dark-fill-strong: rgba(255, 255, 255, .07);
        --dark-border: rgba(255, 255, 255, .18);
        --dark-border-strong: rgba(255, 255, 255, .3);
        --ok-on-dark-fill: rgba(52, 211, 153, .07);
        --ok-on-dark-line: rgba(52, 211, 153, .35);
        --ok-on-dark-ring: rgba(52, 211, 153, .14);
        --dot-idle: #334155;
        --brand-on-dark: #2563eb;
        --term-bg: #05080f;
        --term-border: #1a2333;
        --term-text: #b8c4d6;
        --term-muted: #64748b;
        --tab-text: #5b6678;
        --code-text: #344054;
        --alert-ok-text: #054f31;
        --alert-danger-text: #912018;

        /* ---- space ---- */
        --sp-1: 4px;
        --sp-2: 8px;
        --sp-3: 12px;
        --sp-4: 16px;
        --sp-5: 20px;
        --sp-6: 24px;
        --sp-7: 32px;
        --sp-8: 40px;
        --sp-9: 56px;
        --sp-10: 72px;
        --sp-11: 88px;

        /* ---- radius ---- */
        --radius-xs: 4px;
        --radius-sm: 6px;
        --radius: 9px;
        --radius-lg: 12px;
        --radius-pill: 999px;

        /* ---- typography ---- */
        --text-2xs: 10px;
        --text-xs: 11px;
        --text-sm: 12.5px;
        --text-base: 13.5px;
        --text-md: 15px;
        --text-lg: 16.5px;
        --text-xl: 23px;
        --text-display: 30px;
        --text-hero: clamp(33px, 4.6vw, 52px);
        --leading-tight: 1.22;
        --leading-body: 1.65;
        --tracking-tight: -.021em;
        --tracking-label: .11em;

        /* ---- shadow ---- */
        --shadow-xs: 0 1px 2px rgba(16, 24, 40, .05);
        --shadow-sm: 0 1px 3px rgba(16, 24, 40, .07), 0 1px 2px rgba(16, 24, 40, .04);
        --shadow-lg: 0 24px 60px -36px rgba(0, 0, 0, .9);
        --shadow-inset: inset 0 1px 0 rgba(255, 255, 255, .12);

        /* ---- decorative ---- */
        --nav-bg: rgba(255, 255, 255, .94);
        --hero-glow: rgba(37, 99, 235, .22);
        --ok-on-dark-ring-soft: rgba(52, 211, 153, .16);

        /* ---- fonts / width ---- */
        --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, "Helvetica Neue", Arial, sans-serif;
        --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        --maxw: 1140px;
    }

    /* ---------- alternate theme: PALO IT ----------
          Proof that the whole identity lives in the tokens: no screen changes,
          only this block. Colors taken from palo-it.com's public CSS (green
          #008F68 and warm neutrals). Enabled by <html data-brand="palo">,
          which the layout reads from the `brand` cookie (no inline script - CSP). */
    :root[data-brand="palo"] {
        --ink: #282828;
        --body: #464644;
        --muted: #6f706a;

        --surface: #ffffff;
        --surface-2: #f9f9f8;
        --surface-3: #eeeeec;
        --bg: #f6f6f5;

        --line: #e4e4e1;
        --line-strong: #d2d2cf;
        --line-hover: #8a8b85;

        --brand: #008f68;
        --brand-600: #03624b;
        --brand-soft: #ebfef6;
        --brand-ring: rgba(0, 143, 104, .15);

        --ok: #03624b;
        --ok-soft: #ebfef6;
        --ok-line: #c2f0cc;
        --danger: #e12d36;
        --danger-soft: #fdf0f0;
        --danger-line: #f5c9cb;

        --stage-backlog: #d2d2cf;
        --stage-doing: #008f68;
        --stage-review: #8fd9b0;
        --stage-done: #03624b;

        --ink-900: #1f201e;
        --ink-800: #282828;
        --tab-text: #464644;
        --alert-ok-text: #03624b;
        --alert-danger-text: #a31f27;
    }

    /* ---------- base ---------- */
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
        margin: 0;
        font-family: var(--font-sans);
        font-size: var(--text-md);
        color: var(--body);
        background: var(--bg);
        line-height: var(--leading-body);
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }
    h1, h2, h3, h4 {
        color: var(--ink); line-height: var(--leading-tight); margin: 0;
        letter-spacing: var(--tracking-tight); font-weight: 700; text-wrap: balance;
    }
    p { margin: 0; }
    a { color: var(--brand); text-decoration: none; }
    a:hover { text-decoration: underline; text-underline-offset: 2px; }
    img { max-width: 100%; }
    pre code { background: none; border: 0; padding: 0; font-size: inherit; color: inherit; }
    code {
        font-family: var(--font-mono);
        font-size: .84em;
        background: var(--surface-3);
        border: 1px solid var(--line);
        padding: 1px 5px;
        border-radius: var(--radius-xs);
        color: var(--code-text);
    }
    ::selection { background: var(--brand-ring); }
    :focus-visible {
        outline: 2px solid var(--brand);
        outline-offset: 2px;
        border-radius: 3px;
    }
    .sr-only {
        position: absolute; width: 1px; height: 1px;
        padding: 0; margin: -1px; overflow: hidden;
        clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
    }
    .skip {
        position: absolute; left: var(--sp-4); top: -48px;
        background: var(--ink); color: var(--text-on-brand);
        padding: var(--sp-2) var(--sp-3); border-radius: var(--radius-sm);
        z-index: 50; transition: top .15s ease;
    }
    .skip:focus { top: var(--sp-3); text-decoration: none; }

    /* ---------- layout ---------- */
    .wrap { width: 100%; max-width: var(--maxw); margin: 0 auto; padding: 0 var(--sp-6); }
    /* padding-top/bottom, never the shorthand: .page lives on the SAME element
          as .wrap, and a shorthand here would wipe the wrap's side padding. */
    .page { padding-top: var(--sp-9); padding-bottom: var(--sp-11); }
    .page-narrow { max-width: 700px; }
    .section { padding: var(--sp-11) 0; }
    .section-dark { background: var(--ink-900); color: var(--dark-body); }
    .section-dark h2, .section-dark h3 { color: var(--text-on-brand); }
    .section-head { max-width: 640px; margin-bottom: var(--sp-7); }
    .section-head h2 { font-size: var(--text-display); margin-bottom: var(--sp-3); }
    .section-head p { font-size: var(--text-lg); color: var(--body); max-width: 60ch; }
    .section-dark .section-head p { color: var(--dark-muted); }
    .kicker {
        font-family: var(--font-mono);
        font-size: var(--text-xs); letter-spacing: .16em; text-transform: uppercase;
        color: var(--muted); font-weight: 600; margin-bottom: var(--sp-3);
        display: flex; align-items: center; gap: var(--sp-3);
    }
    .kicker::after {
        content: ""; height: 1px; width: 42px; background: var(--line-strong);
    }
    .section-dark .kicker { color: var(--dark-muted); }
    .section-dark .kicker::after { background: var(--ink-line); }
    .sub { font-size: var(--text-base); color: var(--muted); }

    /* ---------- nav ---------- */
    .nav {
        position: sticky; top: 0; z-index: 20;
        background: var(--nav-bg);
        -webkit-backdrop-filter: saturate(180%) blur(10px);
        backdrop-filter: saturate(180%) blur(10px);
        border-bottom: 1px solid var(--line);
    }
    .nav-inner { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-5); min-height: 64px; flex-wrap: wrap; }
    .brand { display: inline-flex; align-items: center; gap: var(--sp-3); font-weight: 650; color: var(--ink); font-size: var(--text-md); letter-spacing: -.01em; }
    .brand:hover { color: var(--ink); text-decoration: none; }
    .brand-tag {
        font-family: var(--font-mono); font-size: var(--text-2xs); font-weight: 500;
        letter-spacing: .06em; color: var(--muted);
        border: 1px solid var(--line); border-radius: var(--radius-xs);
        padding: 1px 5px; margin-left: 2px;
    }
    .glyph {
        width: 28px; height: 28px; border-radius: 7px;
        background: linear-gradient(180deg, var(--ink-800), var(--ink-900));
        display: grid; place-items: center;
        color: var(--text-on-brand); font-size: var(--text-sm); font-weight: 700;
        font-family: var(--font-mono);
        box-shadow: var(--shadow-inset);
    }
    .nav-links { display: flex; align-items: center; gap: var(--sp-1); flex-wrap: wrap; }
    .nav-links form { margin: 0; }
    .nav-text {
        color: var(--body); font-size: var(--text-base); font-weight: 500;
        padding: 6px 10px; border-radius: var(--radius-sm);
        transition: background .12s ease, color .12s ease;
        display: inline-flex; align-items: center; gap: 6px;
        white-space: nowrap;
    }
    .nav-text:hover { color: var(--ink); background: var(--surface-3); text-decoration: none; }
    .nav-text.is-active { color: var(--ink); background: var(--surface-3); font-weight: 600; }
    .nav-user {
        font-size: var(--text-sm); color: var(--body); font-weight: 600;
        background: var(--surface); border: 1px solid var(--line);
        padding: 5px 11px; border-radius: var(--radius-pill);
        max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        margin-left: var(--sp-2);
    }

    /* ---------- buttons ---------- */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        padding: 9px 15px; border-radius: var(--radius-sm);
        font-family: inherit; font-size: var(--text-base); font-weight: 600; line-height: 1.25;
        text-decoration: none; cursor: pointer;
        border: 1px solid transparent; background: transparent; color: var(--ink);
        transition: background .12s ease, border-color .12s ease, color .12s ease, box-shadow .12s ease;
    }
    .btn:hover { text-decoration: none; }
    .btn-primary { background: var(--brand); color: var(--text-on-brand); box-shadow: var(--shadow-inset), var(--shadow-xs); }
    .btn-primary:hover { background: var(--brand-600); color: var(--text-on-brand); }
    .btn-outline { background: var(--surface); border-color: var(--line-strong); color: var(--ink); box-shadow: var(--shadow-xs); }
    .btn-outline:hover { border-color: var(--line-hover); }
    .btn-ghost { color: var(--body); }
    .btn-ghost:hover { background: var(--surface-3); color: var(--ink); }
    .btn-danger { background: var(--surface); border-color: var(--danger-line); color: var(--danger); }
    .btn-danger:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-line); }
    .btn-sm { padding: 6px 11px; font-size: var(--text-sm); }
    .btn-block { width: 100%; }
    .btn[disabled], .btn.is-disabled { opacity: .55; pointer-events: none; }

    /* ---------- page header ---------- */
    .page-head {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: var(--sp-6); flex-wrap: wrap; margin-bottom: var(--sp-6);
        padding-bottom: var(--sp-5); border-bottom: 1px solid var(--line);
    }
    .page-head h1 { font-size: var(--text-display); margin-bottom: 5px; }
    .page-head .kicker { margin-bottom: var(--sp-2); }
    .page-head .sub { margin: 0; }

    /* ---------- panel / list ---------- */
    .panel {
        background: var(--surface); border: 1px solid var(--line);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-xs);
        overflow: hidden;
    }
    .panel-pad { padding: var(--sp-6); }
    .item {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: var(--sp-6); padding: var(--sp-5) var(--sp-6);
        transition: background .12s ease;
    }
    .item:hover { background: var(--surface-2); }
    .item + .item { border-top: 1px solid var(--line); }
    .item-main { min-width: 0; }
    .item-title { font-size: var(--text-lg); font-weight: 650; display: flex; align-items: center; gap: var(--sp-2); flex-wrap: wrap; margin-bottom: 5px; }
    .item-desc { font-size: var(--text-base); color: var(--body); margin-bottom: var(--sp-2); overflow-wrap: anywhere; max-width: 72ch; }
    .item-meta { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--muted); letter-spacing: .01em; }
    .item-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
    .item-actions form { margin: 0; }
    .empty { padding: var(--sp-9) var(--sp-6); text-align: center; }
    .empty h3 { font-size: var(--text-lg); margin-bottom: 6px; }
    .empty p { font-size: var(--text-base); margin-bottom: var(--sp-5); color: var(--muted); }

    /* ---------- badges / alerts ---------- */
    .badge {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: var(--text-xs); font-weight: 600; line-height: 1.5;
        padding: 1px 8px; border-radius: var(--radius-pill);
        background: var(--surface-3); color: var(--tab-text);
        white-space: nowrap;
    }
    .badge-ok { background: var(--ok-soft); color: var(--ok); }
    .badge-muted { background: var(--surface-3); color: var(--tab-text); }
    .badge-info { background: var(--brand-soft); color: var(--brand-600); }
    .badge-danger { background: var(--danger-soft); color: var(--danger); }
    .badge-warn { background: var(--warn-soft); color: var(--warn); }
    .alert {
        border-radius: var(--radius); padding: var(--sp-3) var(--sp-4);
        font-size: var(--text-base);
        border: 1px solid var(--line); border-left-width: 3px;
        background: var(--surface-2); margin-bottom: var(--sp-5);
    }
    .alert-ok { border-color: var(--ok-line); border-left-color: var(--ok); background: var(--ok-soft); color: var(--alert-ok-text); }
    .alert-error { border-color: var(--danger-line); border-left-color: var(--danger); background: var(--danger-soft); color: var(--alert-danger-text); }
    .alert-warn { border-color: var(--warn-line); border-left-color: var(--warn); background: var(--warn-soft); color: var(--warn); }
    .alert-info { border-color: var(--brand-soft); border-left-color: var(--brand); background: var(--brand-soft); color: var(--brand-600); }
    .alert ul { margin: 6px 0 0; padding-left: var(--sp-4); }

    /* ---------- forms ---------- */
    .form .field + .field { margin-top: var(--sp-5); }
    .label { display: block; font-size: var(--text-base); font-weight: 600; color: var(--ink); margin-bottom: 6px; }
    .input, .textarea {
        display: block; width: 100%; padding: 10px var(--sp-3);
        font-family: inherit; font-size: var(--text-base); color: var(--ink);
        background: var(--surface); border: 1px solid var(--line-strong);
        border-radius: var(--radius-sm); transition: border-color .12s ease, box-shadow .12s ease;
    }
    .textarea { resize: vertical; min-height: 100px; }
    .input::placeholder, .textarea::placeholder { color: var(--muted); }
    .input:focus, .textarea:focus {
        outline: none; border-color: var(--brand);
        box-shadow: 0 0 0 3px var(--brand-ring);
    }
    .input.is-invalid, .textarea.is-invalid { border-color: var(--danger-line); }
    .input.is-invalid:focus, .textarea.is-invalid:focus { box-shadow: 0 0 0 3px var(--danger-ring); }
    .hint { font-size: var(--text-sm); color: var(--muted); margin-top: 6px; }
    .field-error { font-size: var(--text-sm); color: var(--danger); margin-top: 6px; font-weight: 600; }
    .check { display: flex; align-items: center; gap: 9px; font-size: var(--text-base); color: var(--body); cursor: pointer; }
    .check input { width: 15px; height: 15px; accent-color: var(--brand); margin: 0; }
    .form-actions { display: flex; align-items: center; gap: var(--sp-2); flex-wrap: wrap; margin-top: var(--sp-6); padding-top: var(--sp-5); border-top: 1px solid var(--line); }
    .form-alt { font-size: var(--text-base); margin-top: var(--sp-4); text-align: center; color: var(--muted); }

    /* ---------- auth ---------- */
    .auth { display: grid; place-items: center; padding: var(--sp-9) var(--sp-6) var(--sp-11); }
    .auth-card {
        width: 100%; max-width: 412px;
        background: var(--surface); border: 1px solid var(--line);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
        padding: var(--sp-7);
    }
    .auth-card h1 { font-size: var(--text-xl); margin-bottom: 6px; }
    .auth-card .sub { margin-bottom: var(--sp-6); }

    /* ---------- pagination ---------- */
    .pager {
        display: flex; align-items: center; justify-content: center; gap: var(--sp-2);
        margin-top: var(--sp-5); font-size: var(--text-base);
    }
    .pager a, .pager .is-disabled {
        padding: 6px var(--sp-3); border-radius: var(--radius-sm);
        border: 1px solid var(--line-strong); background: var(--surface);
        text-decoration: none; color: var(--ink); font-weight: 600;
    }
    .pager a:hover { border-color: var(--line-hover); text-decoration: none; }
    .pager .is-disabled { color: var(--muted); opacity: .55; border-style: dashed; }
    .pager-info { color: var(--muted); font-family: var(--font-mono); font-size: var(--text-xs); }

    /* ---------- hero (home) ---------- */
    .hero {
        position: relative; overflow: hidden;
        background:
            radial-gradient(760px 300px at 72% -18%, var(--hero-glow), transparent 70%),
            radial-gradient(circle at 1px 1px, var(--ink-line-soft) 1px, transparent 0) 0 0 / 22px 22px,
            var(--ink-900);
        color: var(--dark-body);
        border-bottom: 1px solid var(--ink-800);
        padding: var(--sp-10) 0 var(--sp-9);
    }
    .hero-grid {
        display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr);
        gap: var(--sp-9); align-items: center;
    }
    /* a grid item has min-width:auto; without this the terminal (nowrap)
          stretches the column and creates horizontal scroll on mobile. */
    .hero-grid > * { min-width: 0; }
    .eyebrow {
        display: inline-flex; align-items: center; gap: 9px;
        font-family: var(--font-mono);
        font-size: var(--text-xs); letter-spacing: .15em; text-transform: uppercase;
        color: var(--dark-muted); margin-bottom: var(--sp-5);
    }
    .pulse {
        width: 6px; height: 6px; border-radius: 50%; background: var(--dark-ok);
        box-shadow: 0 0 0 3px var(--ok-on-dark-ring-soft);
    }
    .hero h1 { color: var(--text-on-brand); font-size: var(--text-hero); margin-bottom: var(--sp-4); letter-spacing: -.032em; max-width: 17ch; }
    .hero h1 span { color: var(--dark-accent); }
    .hero .lead { font-size: var(--text-lg); max-width: 54ch; color: var(--dark-body); margin-bottom: var(--sp-7); }
    .hero code, .section-dark code {
        background: var(--dark-fill-strong);
        border-color: var(--ink-line);
        color: var(--dark-text);
    }
    .hero .actions { display: flex; gap: var(--sp-2); flex-wrap: wrap; }
    .hero .btn-primary { background: var(--brand-on-dark); }
    .hero .btn-primary:hover { background: var(--brand); }
    .hero .btn-ghost { border-color: var(--dark-border); color: var(--dark-text); }
    .hero .btn-ghost:hover { background: var(--dark-fill-strong); color: var(--text-on-brand); border-color: var(--dark-border-strong); }

    /* hero spec sheet */
    .spec {
        background: var(--dark-fill);
        border: 1px solid var(--ink-line);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }
    .spec-head {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: var(--sp-3) var(--sp-4); border-bottom: 1px solid var(--ink-line);
        font-family: var(--font-mono); font-size: var(--text-2xs);
        letter-spacing: .12em; text-transform: uppercase; color: var(--dark-muted);
    }
    .spec-live { display: inline-flex; align-items: center; gap: 6px; color: var(--dark-ok-bright); }
    .spec-row {
        display: flex; align-items: baseline; justify-content: space-between; gap: var(--sp-4);
        padding: 10px var(--sp-4); border-bottom: 1px solid var(--ink-line-soft);
        font-size: var(--text-base);
    }
    .spec-k { color: var(--dark-muted); }
    .spec-v { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--dark-text); }
    .terminal {
        margin: var(--sp-3); padding: var(--sp-3) var(--sp-4);
        background: var(--term-bg); border: 1px solid var(--term-border); border-radius: var(--radius);
        font-family: var(--font-mono); font-size: var(--text-xs); line-height: 1.85;
        color: var(--term-text); overflow-x: auto;
    }
    .term-line { white-space: nowrap; }
    .term-line .prompt { color: var(--dark-ok); }
    .term-line .dim { color: var(--term-muted); }
    .spec-foot {
        padding: var(--sp-3) var(--sp-4); border-top: 1px solid var(--ink-line);
        display: flex; gap: var(--sp-4); flex-wrap: wrap;
        font-family: var(--font-mono); font-size: var(--text-xs); color: var(--dark-muted);
    }
    .spec-foot b { color: var(--dark-text); font-weight: 600; }

    /* ---------- concepts (home) ---------- */
    .grid { display: grid; gap: var(--sp-4); grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); }
    .concept {
        background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-lg);
        padding: var(--sp-5); display: flex; flex-direction: column; gap: 9px;
        transition: box-shadow .16s ease, border-color .16s ease;
    }
    .concept:hover { box-shadow: var(--shadow-sm); border-color: var(--line-strong); }
    .concept .top { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-3); }
    .concept .idx {
        font-family: var(--font-mono); font-size: var(--text-xs); color: var(--muted);
        letter-spacing: .08em; font-weight: 600;
    }
    .concept h3 { font-size: var(--text-lg); font-weight: 650; }
    .concept p { font-size: var(--text-base); line-height: 1.62; }
    .concept .meta { margin-top: auto; padding-top: var(--sp-3); border-top: 1px solid var(--line); display: grid; gap: 7px; }
    .concept .meta div { font-size: var(--text-sm); display: flex; gap: var(--sp-2); align-items: baseline; }
    .concept .meta .lbl {
        color: var(--muted); min-width: 44px; text-transform: uppercase;
        font-size: var(--text-2xs); letter-spacing: var(--tracking-label); font-weight: 650; flex-shrink: 0;
    }
    .concept .meta code { white-space: normal; word-break: break-word; font-size: .82em; }
    .concept .meta strong { color: var(--ink); }

    /* ---------- ladder (home) ---------- */
    .steps { display: grid; border-top: 1px solid var(--ink-line); }
    .step {
        position: relative;
        display: grid; grid-template-columns: 220px 1fr; gap: var(--sp-7); align-items: baseline;
        padding: var(--sp-5) 0 var(--sp-5) var(--sp-6); border-bottom: 1px solid var(--ink-line);
    }
    .step::before {
        content: ""; position: absolute; left: 0; top: 27px;
        width: 7px; height: 7px; border-radius: 50%; background: var(--dot-idle);
    }
    .step .ver { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--dark-body); }
    .step .ver b { color: var(--text-on-brand); font-weight: 600; }
    .step .what { font-size: var(--text-base); color: var(--dark-muted); }
    .step .what strong { color: var(--text-on-brand); }
    .step.now {
        background: linear-gradient(90deg, var(--ok-on-dark-fill), transparent 65%);
        border-bottom-color: var(--ok-on-dark-line);
    }
    .step.now::before { background: var(--dark-ok); box-shadow: 0 0 0 4px var(--ok-on-dark-ring); }
    .step.now .ver { color: var(--dark-ok-bright); }
    .step.now .what { color: var(--dark-ok-text); }
    .tag {
        display: inline-block; font-family: var(--font-mono);
        font-size: var(--text-2xs); letter-spacing: var(--tracking-label); text-transform: uppercase;
        background: transparent; color: var(--dark-ok-bright);
        border: 1px solid var(--ok-on-dark-line); border-radius: var(--radius-xs);
        padding: 2px 6px; margin-left: var(--sp-2); font-weight: 600; vertical-align: 1px;
    }

    /* ---------- evidence table (home) ---------- */
    .evidence { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-xs); }
    table { width: 100%; border-collapse: collapse; font-size: var(--text-base); }
    th, td { text-align: left; padding: var(--sp-3) var(--sp-5); border-bottom: 1px solid var(--line); vertical-align: baseline; }
    th {
        font-size: var(--text-2xs); letter-spacing: var(--tracking-label); text-transform: uppercase;
        color: var(--muted); background: var(--surface-2); font-weight: 650;
        border-bottom-color: var(--line-strong);
    }
    tr:last-child td { border-bottom: 0; }
    td code { font-size: .82em; }
    td.status-ok { color: var(--ok); font-weight: 650; font-family: var(--font-mono); font-size: var(--text-sm); }
    td.status-bad { color: var(--danger); font-weight: 650; font-family: var(--font-mono); font-size: var(--text-sm); }

    /* ---------- description (show) ---------- */
    .dl { display: grid; gap: 0; }
    .dl-row { display: grid; grid-template-columns: 130px 1fr; gap: var(--sp-4); padding: var(--sp-3) 0; border-bottom: 1px solid var(--line); }
    .dl-row:last-child { border-bottom: 0; }
    .dl-key { font-size: var(--text-2xs); letter-spacing: var(--tracking-label); text-transform: uppercase; color: var(--muted); font-weight: 650; padding-top: var(--sp-1); }
    .dl-val { font-size: var(--text-base); color: var(--ink); overflow-wrap: anywhere; }

    /* ---------- metrics summary ---------- */
    .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--sp-3); margin-bottom: var(--sp-5); }
    .stat {
        background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius);
        padding: var(--sp-4) var(--sp-5); box-shadow: var(--shadow-xs);
    }
    .stat-label { font-size: var(--text-2xs); letter-spacing: var(--tracking-label); text-transform: uppercase; color: var(--muted); font-weight: 650; }
    .stat-value {
        font-size: var(--text-display); font-weight: 700; color: var(--ink); line-height: 1.1;
        margin-top: 7px; letter-spacing: -.035em; font-variant-numeric: tabular-nums;
    }
    .stat-note { font-size: var(--text-sm); color: var(--muted); margin-top: 3px; }
    .stat.is-ok .stat-value { color: var(--ok); }
    .stat.is-danger { border-color: var(--danger-line); background: var(--danger-soft); }
    .stat.is-danger .stat-value { color: var(--danger); }

    /* ---------- filters / search ---------- */
    .toolbar {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--sp-3); flex-wrap: wrap; margin-bottom: var(--sp-4);
    }
    .chips {
        display: inline-flex; align-items: center; gap: 2px; flex-wrap: wrap;
        background: var(--surface-3); border: 1px solid var(--line); border-radius: var(--radius);
        padding: 3px;
    }
    .chip {
        font-size: var(--text-sm); font-weight: 600; padding: 6px var(--sp-3);
        border-radius: var(--radius-sm); text-decoration: none;
        border: 1px solid transparent; background: transparent; color: var(--tab-text);
        transition: background .12s ease, color .12s ease, box-shadow .12s ease;
    }
    .chip:hover { color: var(--ink); background: var(--surface-2); text-decoration: none; }
    .chip.is-active { background: var(--surface); color: var(--ink); box-shadow: var(--shadow-xs); border-color: var(--line); }
    .chip .n { font-family: var(--font-mono); font-size: var(--text-2xs); color: var(--muted); margin-left: 5px; }
    .chip.is-active .n { color: var(--brand); }
    .searchform { display: flex; gap: var(--sp-2); align-items: center; flex-wrap: wrap; }
    .searchform .input { width: 220px; }
    .select {
        padding: 9px 10px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm);
        font-size: var(--text-base); font-family: inherit; color: var(--ink); background: var(--surface);
        box-shadow: var(--shadow-xs);
    }
    .select:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }

    /* ---------- priority / due date ---------- */
    .item-tags { display: flex; align-items: center; gap: var(--sp-2); flex-wrap: wrap; }
    .due { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--muted); }
    .due.is-late { color: var(--danger); font-weight: 650; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-5); }
    .field-row .field + .field { margin-top: 0; }

    /* ---------- audit trail ---------- */
    .timeline { display: grid; }
    .tl-item { display: grid; grid-template-columns: 132px 1fr; gap: var(--sp-5); padding: var(--sp-3) 0; border-bottom: 1px solid var(--line); }
    .tl-item:last-child { border-bottom: 0; }
    .tl-when { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--muted); padding-top: 3px; }
    .tl-what { font-size: var(--text-base); }
    .tl-what strong { color: var(--ink); font-weight: 650; }
    .tl-diff { margin-top: 7px; display: flex; gap: 6px; flex-wrap: wrap; }

    .tl-diff code { font-size: var(--text-xs); white-space: normal; word-break: break-word; }

    /* ---------- timeline ---------- */
    .timeline-progress {
        height: 6px;
        background: var(--surface-3);
        border-radius: 3px;
        margin: var(--sp-3) 0;
        overflow: hidden;
        position: relative;
    }
    .timeline-progress::after {
        content: '';
        position: absolute;
        top: 0; bottom: 0; right: 0;
        width: 40px;
        background: linear-gradient(to left, var(--brand), transparent);
        z-index: 1;
    }
    .timeline-progress-bar {
        height: 100%;
        background: linear-gradient(to right, var(--brand), var(--stage-done));
        border-radius: 3px;
        transition: width 0.35s ease;
        box-shadow: 0 0 10px rgba(var(--brand-rgb), 0.3);
    }
    /* "now" marker on the progress bar */
    .timeline-progress .now-marker {
        position: absolute;
        top: 50%; transform: translateY(-50%);
        width: 3px; height: 3px;
        background: var(--ink-900);
        border-radius: 50%;
        box-shadow: 0 0 6px rgba(0,0,0,0.5);
    }
    .tl-item {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        border-radius: var(--radius-sm);
        padding: var(--sp-2);
        margin: 0 -var(--sp-2) var(--sp-2);
        position: relative;
        background: var(--surface);
        border: 1px solid var(--line);
        box-shadow: var(--shadow-sm);
    }
    .tl-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        background: var(--surface-2);
    }
    .tl-item.is-current {
        border-left: 5px solid var(--brand);
        background: linear-gradient(var(--brand-ring), var(--surface));
        box-shadow: 0 4px 20px rgba(var(--brand-rgb), 0.4);
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; box-shadow: 0 0 12px rgba(var(--brand-rgb), 0.3); }
        50% { opacity: 0.85; box-shadow: 0 0 25px rgba(var(--brand-rgb), 0.5); }
    }
    .tl-item.is-expanded .tl-what {
        animation: expand 0.3s ease;
    }
    @keyframes expand {
        from { opacity: 0; max-height: 0; margin-top: -40px; overflow: hidden; }
        to { opacity: 1; max-height: 500px; margin-top: 0; }
    }
    .tl-tooltip {
        position: absolute;
        background: var(--ink-900);
        color: var(--surface);
        padding: var(--sp-3);
        border-radius: var(--radius-sm);
        font-size: var(--text-sm);
        max-width: 280px;
        z-index: 10;
        box-shadow: var(--shadow-lg);
        animation: tooltip-appear 0.2s ease;
    }
    @keyframes tooltip-appear {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .tl-tooltip::before {
        content: '';
        position: absolute;
        top: -5px;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-bottom-color: var(--ink-900);
    }
    .badge-secondary {
        background: var(--surface-3);
        color: var(--body);
    }
    /* ------- summary on top ------- */
    .timeline-summary {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius-md);
        padding: var(--sp-4);
        margin: var(--sp-4) 0;
    }
    .summary-row {
        display: flex; justify-content: space-between; align-items: center;
        margin: var(--sp-2) 0;
    }
    .summary-label { color: var(--body); font-size: var(--text-sm); }
    .summary-value {
        font-weight: 600; color: var(--brand);
        display: flex; align-items: center; gap: var(--sp-1);
    }
    .summary-value small { font-weight: normal; color: var(--muted); }
    /* ------- progress rings ------- */
    .progress-ring {
        width: 40px; height: 40px;
        margin: 0 auto var(--sp-2);
        position: relative;
    }
    .progress-ring svg {
        transform: rotate(-90deg);
    }
    .progress-ring .progress-bg {
        fill: none; stroke: var(--surface-3); stroke-width: 6;
    }
    .progress-ring .progress-fill {
        fill: none; stroke: var(--brand); stroke-width: 6;
        stroke-dasharray: 251; stroke-dashoffset: 0;
        transition: stroke-dashoffset 0.35s ease;
    }
    /* ---------- footer ---------- */
    .footer { background: var(--ink-900); padding: var(--sp-7) 0; border-top: 1px solid var(--ink-800); }
    .footer-inner { display: flex; justify-content: space-between; gap: var(--sp-4); flex-wrap: wrap; font-size: var(--text-sm); color: var(--dark-muted); }
    .footer code { background: var(--dark-fill-strong); border-color: var(--ink-line); color: var(--dark-text); }

    /* ---------- responsive ---------- */
    @media (max-width: 900px) {
        .hero-grid { grid-template-columns: 1fr; gap: var(--sp-7); }
        .hero h1 { max-width: 22ch; }
        .step { grid-template-columns: 170px 1fr; gap: var(--sp-5); }
    }
    @media (max-width: 680px) {
        .wrap { padding: 0 var(--sp-4); }
        .page { padding-top: var(--sp-7); padding-bottom: var(--sp-10); }
        .page-head { align-items: flex-start; gap: var(--sp-4); }
        .page-head h1 { font-size: var(--text-xl); }
        /* Actions became a single button (menu), so they fit alongside. */
        .item { gap: var(--sp-2); padding: var(--sp-5) var(--sp-4); }
        .step { grid-template-columns: 1fr; gap: var(--sp-1); padding-left: var(--sp-5); }
        .step::before { top: 22px; }
        .nav-inner { padding-top: var(--sp-2); padding-bottom: var(--sp-2); }
        .nav-links { width: 100%; gap: 2px; flex-wrap: nowrap; overflow-x: auto; padding-bottom: var(--sp-1); scrollbar-width: none; }
        .nav-links::-webkit-scrollbar { display: none; }
        .nav-user { display: none; }
        .auth { padding: var(--sp-7) var(--sp-4) var(--sp-9); }
        .auth-card { padding: var(--sp-6); }
        .hero { padding: var(--sp-9) 0 var(--sp-8); }
        .section { padding: var(--sp-9) 0; }
        .section-head h2 { font-size: var(--text-xl); }
        .dl-row { grid-template-columns: 1fr; gap: 2px; }
        .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .field-row { grid-template-columns: 1fr; }
        .field-row .field + .field { margin-top: var(--sp-5); }
        .tl-item { grid-template-columns: 1fr; gap: 2px; }
        .searchform { width: 100%; }
        .searchform .input { width: 100%; flex: 1 1 auto; }
        th, td { padding: var(--sp-3) var(--sp-4); }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        *, *::before, *::after { transition: none !important; }
    }
    /* ---------- flow: server-generated charts ----------
          Plain SVG, no library and no JS. The rules below only paint:
          coordinates come already computed from PHP (App\Flow\Chart). */
    .chart-panel + .chart-panel, .grid-2 + .panel { margin-top: var(--sp-4); }
    .chart-head {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: var(--sp-5); flex-wrap: wrap; margin-bottom: var(--sp-4);
    }
    .chart-head h2 { font-size: var(--text-lg); }
    .chart-head .hint { max-width: 58ch; margin-bottom: 0; }
    .legend { display: flex; align-items: center; gap: var(--sp-4); flex-wrap: wrap; list-style: none; margin: 0; padding: 0; }
    .legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: var(--text-sm); color: var(--body); white-space: nowrap; }
    .legend-item b { font-family: var(--font-mono); font-variant-numeric: tabular-nums; color: var(--ink); }
    .swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
    .swatch-backlog { background: var(--stage-backlog); }
    .swatch-doing { background: var(--stage-doing); }
    .swatch-review { background: var(--stage-review); }
    .swatch-done { background: var(--stage-done); }
    .chart { margin: 0; }
    .chart svg { display: block; width: 100%; height: auto; overflow: visible; }
    .chart-grid-line { stroke: var(--line); stroke-width: 1; }
    .chart-axis { fill: var(--muted); font-family: var(--font-mono); font-size: 10.5px; }
    .chart-value { fill: var(--ink); font-family: var(--font-mono); font-size: 10.5px; font-weight: 650; }
    .band-backlog { fill: var(--stage-backlog); }
    .band-doing { fill: var(--stage-doing); }
    .band-review { fill: var(--stage-review); }
    .band-done { fill: var(--stage-done); }
    /* A hairline in the surface color separates the bands without a border
          on each one: it is what makes the CFD look like a chart, not a smear. */
    .band-edge { fill: none; stroke: var(--surface); stroke-width: 2; stroke-linejoin: round; }
    .bar { fill: var(--brand); }
    .bar.is-zero { fill: var(--surface-3); }
    .hbar-track { fill: var(--surface-3); }
    .hbar-fill { fill: var(--brand); }
    .hbar-fill.is-warn { fill: var(--warn); }
    .hbar-label { fill: var(--body); font-size: 12px; }
    .hbar-value { fill: var(--ink); font-family: var(--font-mono); font-size: 12px; font-variant-numeric: tabular-nums; }
    .stat-unit { font-size: var(--text-sm); font-weight: 600; color: var(--muted); margin-left: 5px; letter-spacing: 0; }
    /* Header actions: .actions used to exist only inside the hero. */
    .page-head .actions { display: flex; align-items: center; gap: var(--sp-2); flex-wrap: wrap; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-4); }
    .grid-2 > .panel { min-width: 0; }
    .stale-list { list-style: none; margin: var(--sp-4) 0 0; padding: var(--sp-4) 0 0; border-top: 1px solid var(--line); }
    .stale-list li { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sp-3); padding: 5px 0; font-size: var(--text-base); }
    .stale-list a { color: var(--ink); }
    .stale-list .age { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--warn); white-space: nowrap; }
    .method { margin: 0; padding-left: var(--sp-5); }
    .method li { font-size: var(--text-base); color: var(--body); margin-bottom: 7px; }
    .method li:last-child { margin-bottom: 0; }
    .method strong { color: var(--ink); font-weight: 650; }
    @media (max-width: 900px) {
        .grid-2 { grid-template-columns: 1fr; }
        .chart-head { gap: var(--sp-3); }
    }

    /* ---------- project: overview ----------
          The page opens on the state of the work, not the list. The progress
          bar is the same stacked geometry as the CFD: one band per stage,
          with a written label next to it so color is never the only cue. */
    .progress-head {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: var(--sp-5); flex-wrap: wrap; margin-bottom: var(--sp-4);
    }
    .progress-value {
        font-size: var(--text-hero); font-weight: 700; color: var(--ink);
        line-height: 1; letter-spacing: var(--tracking-tight); margin: var(--sp-2) 0;
        font-variant-numeric: tabular-nums;
    }
    .progress-value .stat-unit { font-size: var(--text-md); }

    /* main column + the "what now?" column */
    .split {
        display: grid; grid-template-columns: minmax(0, 1fr) 340px;
        gap: var(--sp-4); align-items: start; margin-top: var(--sp-4);
    }
    .split-aside { display: grid; gap: var(--sp-4); min-width: 0; }
    .timeline-now { white-space: nowrap; }
    .timeline-now strong { font-family: var(--font-mono); color: var(--ink); }

    /* timeline: one week per row, state at the end of it */
    .week-head {
        display: flex; align-items: baseline; justify-content: space-between;
        gap: var(--sp-3); flex-wrap: wrap;
    }
    .week-count { font-size: var(--text-base); color: var(--ink); font-weight: 650; }
    .week-meta { font-size: var(--text-sm); color: var(--muted); font-family: var(--font-mono); }
    .week-delta { color: var(--ok); font-weight: 650; }
    .week-bar { margin: var(--sp-2) 0 0; }
    .week-stages {
        display: flex; gap: var(--sp-4); flex-wrap: wrap; list-style: none;
        margin: var(--sp-2) 0 0; padding: 0;
        font-family: var(--font-mono); font-size: var(--text-xs);
        color: var(--muted); font-variant-numeric: tabular-nums;
    }
    .week-stages li { display: inline-flex; align-items: center; gap: 5px; }
    .week-events {
        list-style: none; margin: var(--sp-3) 0 0; padding: var(--sp-3) 0 0;
        border-top: 1px dashed var(--line); display: grid; gap: 4px;
    }
    .week-events li { font-size: var(--text-sm); color: var(--body); }
    .week-events li:last-child { margin-bottom: 0; }
    .week-events strong { color: var(--ink); font-weight: 650; }
    .week-events a {
        color: var(--ink); text-decoration: underline;
        text-decoration-color: var(--line-strong);
    }
    .week-events a:hover { text-decoration-color: var(--brand); }
    .tl-item.is-current .tl-when { color: var(--brand); font-weight: 650; }
    .tl-item.is-current .badge { margin-left: var(--sp-1); }

    /* "open now": what needs action, without pagination */
    .now-list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--sp-2); }
    .now-list li {
        display: flex; align-items: baseline; gap: var(--sp-2); flex-wrap: wrap;
        padding-bottom: var(--sp-2); border-bottom: 1px solid var(--line);
    }
    .now-list li:last-child { border-bottom: 0; padding-bottom: 0; }
    .now-list a { color: var(--ink); font-size: var(--text-base); min-width: 0; }
    .now-list a:hover { color: var(--brand); }
    .now-list .badge { flex-shrink: 0; }
    .now-meta {
        width: 100%; font-family: var(--font-mono); font-size: var(--text-xs);
        color: var(--muted);
    }
    .now-meta .is-late { color: var(--danger); font-weight: 650; }

    /* team: workload and members in one list - it used to be two panels
          and the same person showed up twice. */
    .member { padding: var(--sp-3) 0; border-bottom: 1px solid var(--line); }
    .member:last-of-type { border-bottom: 0; padding-bottom: 0; }
    .member-head { display: flex; align-items: baseline; justify-content: space-between; gap: var(--sp-2); flex-wrap: wrap; }
    .member-name {
        display: inline-flex; align-items: center; gap: var(--sp-2); flex-wrap: wrap;
        font-size: var(--text-base); color: var(--ink); font-weight: 600; min-width: 0;
    }
    .member-name .badge { font-weight: 500; }
    .member-count { font-size: var(--text-sm); color: var(--muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .member-count b { color: var(--ink); font-family: var(--font-mono); }
    .member-bar { margin: var(--sp-2) 0 0; }
    .member-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--sp-2); flex-wrap: wrap; margin-top: var(--sp-2);
        font-family: var(--font-mono); font-size: var(--text-xs); color: var(--muted);
    }
    .member-foot span { min-width: 0; overflow-wrap: anywhere; }

    @media (max-width: 960px) {
        /* Stack, but keep the timeline on top: it is the main reading and
              can't drop to the end of the page. */
        .split { grid-template-columns: 1fr; }
        .split-aside { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 680px) {
        .split-aside { grid-template-columns: 1fr; }
        .progress-head { align-items: flex-start; }
        .week-head { gap: 2px; }
        .week-stages { gap: var(--sp-2) var(--sp-3); }
    }

    /* ---------- schedule: gantt / view over time ----------
          Horizontal scale + one bar per task, with a side column of tasks. */
    .gantt-wrap { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: var(--sp-4); align-items: start; }
    .gantt-side { min-width: 0; }
    .gantt-side-head {
        position: sticky; top: 0; z-index: 30;
        background: var(--surface); border-bottom: 1px solid var(--line);
        padding: var(--sp-3); display: flex; align-items: baseline; justify-content: space-between; gap: var(--sp-2);
    }
    .gantt-side-list { display: grid; }
    .gantt-task {
        display: flex; align-items: center; gap: var(--sp-2);
        padding: var(--sp-2); border-bottom: 1px solid var(--line);
        font-size: var(--text-sm); cursor: pointer;
        transition: background 0.15s ease;
    }
    .gantt-task:hover { background: var(--surface-2); }
    .gantt-task.is-done .gantt-task-title { text-decoration: line-through; color: var(--muted); }
    .gantt-task.is-late .gantt-task-title { color: var(--danger); }
    .gantt-task-title { color: var(--ink); font-weight: 550; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
    .gantt-main { min-width: 0; overflow-x: auto; }
    .gantt-scale {
        position: sticky; top: 0; z-index: 20;
        background: var(--surface); border-bottom: 1px solid var(--line);
    }
    .gantt-scale-track { position: relative; height: 28px; }
    .gantt-scale-tick {
        position: absolute; top: 0; bottom: 0;
        border-left: 1px solid var(--line); padding-left: 6px;
        font-family: var(--font-mono); font-size: 10.5px; color: var(--muted);
        display: flex; align-items: center; white-space: nowrap;
    }
    .gantt-scale-tick:first-child { border-left: 0; }
    .gantt-scale-gridline { position: absolute; top: 0; bottom: 0; border-left: 1px solid var(--line); opacity: 0.5; }
    .gantt-bars { position: relative; }
    .gantt-row {
        position: relative;
        height: 44px;
        border-bottom: 1px solid var(--line);
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .gantt-row:hover { background: var(--surface-2); }
    .gantt-bar {
        position: absolute;
        top: 13px;
        height: 18px;
        border-radius: var(--radius-sm);
        background: var(--brand);
        overflow: hidden;
        cursor: pointer;
        transition: filter 0.15s ease, transform 0.15s ease;
    }
    .gantt-bar:hover { filter: brightness(1.1); }
    .gantt-bar.is-done { background: var(--stage-done); }
    .gantt-bar.is-late { background: var(--danger); }
    .gantt-bar.is-done::after { content: '✓'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: var(--surface); font-size: 11px; }
    .gantt-progress { position: absolute; inset: 0 auto 0 0; background: rgba(255,255,255,0.25); }
    .today-marker { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--danger); z-index: 10; pointer-events: none; }
    .gantt-label { position: absolute; top: -22px; background: var(--ink-900); color: var(--surface); padding: 2px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; z-index: 15; }
    .gantt-legend { display: flex; gap: var(--sp-4); flex-wrap: wrap; margin-top: var(--sp-3); font-size: var(--text-sm); color: var(--body); }
    .gantt-legend-item { display: inline-flex; align-items: center; gap: 6px; }
    .gantt-legend-swatch { width: 12px; height: 8px; border-radius: 2px; }
    .zoom-group { display: inline-flex; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); overflow: hidden; }
    .zoom-btn {
        background: var(--surface); border: 0; padding: 6px 12px; font-size: var(--text-sm);
        color: var(--body); cursor: pointer; font-family: inherit;
    }
    .zoom-btn + .zoom-btn { border-left: 1px solid var(--line-strong); }
    .zoom-btn.is-active { background: var(--brand); color: var(--surface); font-weight: 600; }
    .zoom-btn:hover:not(.is-active) { background: var(--surface-2); }

    /* ---------- utilities (the only way to make one-off tweaks) ----------
          They sit at the end of the file on purpose: same specificity as the
          components, so they win by order. No style="" in views. */
    .mt-0 { margin-top: 0; }
    .mt-1 { margin-top: var(--sp-1); }
    .mt-2 { margin-top: var(--sp-2); }
    .mt-3 { margin-top: var(--sp-3); }
    .mt-4 { margin-top: var(--sp-4); }
    .mt-5 { margin-top: var(--sp-5); }
    .mt-6 { margin-top: var(--sp-6); }
    .mt-7 { margin-top: var(--sp-7); }
    .mb-1 { margin-bottom: var(--sp-1); }
    .mb-2 { margin-bottom: var(--sp-2); }
    .mb-3 { margin-bottom: var(--sp-3); }
    /* an h2 inside .section-head carries the component's specificity;
          the compound selector below is what guarantees the smaller size. */
    .section-head h2.panel-title { font-size: 19px; }
    .section-head h2.panel-title-sm { font-size: 17px; }
</style>
