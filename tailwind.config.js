// Tailwind 1.9 (2020): the version a Laravel 5.8 app would have adopted.
// Build: npm run css:build (Tailwind 1's own CLI, no Laravel Mix, because
// Mix 4's webpack 4 doesn't run on the current Node).
//
// Colors: the tokens stay in :root of resources/views/partials/ui.blade.php.
// Here they only get class names (bg-surface, text-ink, border-line...).
// v1 limit: a var() color doesn't support the opacity modifier.
module.exports = {
    purge: [
        './resources/views/**/*.blade.php',
        './public/js/lab.js',
    ],
    future: {
        removeDeprecatedGapUtilities: true,
        purgeLayersByDefault: true,
    },
    corePlugins: {
        // The reset stays with the legacy CSS (ui.blade.php). Tailwind's
        // preflight would override h1, a, table... on every old screen.
        preflight: false,
    },
    theme: {
        extend: {
            colors: {
                ink: 'var(--ink)',
                body: 'var(--body)',
                muted: 'var(--muted)',
                bg: 'var(--bg)',
                surface: {
                    default: 'var(--surface)',
                    2: 'var(--surface-2)',
                    3: 'var(--surface-3)',
                },
                line: {
                    default: 'var(--line)',
                    strong: 'var(--line-strong)',
                    hover: 'var(--line-hover)',
                },
                brand: {
                    default: 'var(--brand)',
                    600: 'var(--brand-600)',
                    soft: 'var(--brand-soft)',
                },
                ok: { default: 'var(--ok)', soft: 'var(--ok-soft)', line: 'var(--ok-line)' },
                warn: { default: 'var(--warn)', soft: 'var(--warn-soft)', line: 'var(--warn-line)' },
                danger: { default: 'var(--danger)', soft: 'var(--danger-soft)', line: 'var(--danger-line)' },
                stage: {
                    backlog: 'var(--stage-backlog)',
                    doing: 'var(--stage-doing)',
                    review: 'var(--stage-review)',
                    done: 'var(--stage-done)',
                },
            },
            fontFamily: {
                mono: 'var(--font-mono)',
            },
        },
    },
};
