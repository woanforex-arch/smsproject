<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards against silent theme breakage.
 *
 * Filament's compiled CSS ships minified to a single line, so a mistyped class
 * in our theme fails completely silently: the rule simply never matches. That
 * is exactly how the "active item text invisible" bug happened — we targeted
 * `.fi-sidebar-item-button` when Filament v5 uses `.fi-sidebar-item-btn`.
 *
 * These tests parse the real stylesheet and assert that every class this
 * project's theme relies on actually exists upstream.
 */
class ThemeSelectorTest extends TestCase
{
    private const CSS_PATH = 'public/css/filament/filament/app.css';

    /**
     * Classes the corporate theme targets, grouped by the concern they serve.
     * Keep in sync with resources/views/filament/theme.blade.php.
     */
    private const SELECTORS = [
        // Surfaces & chrome
        'fi-body' => 'page background',
        'fi-section' => 'card surface',
        'fi-topbar' => 'topbar surface',
        'fi-simple-layout' => 'login layout',
        'fi-simple-main-ctn' => 'login card',

        // Sidebar — note the v5 names
        'fi-sidebar' => 'sidebar surface',
        'fi-sidebar-header' => 'sidebar header',
        'fi-sidebar-item' => 'sidebar item wrapper (carries fi-active)',
        'fi-sidebar-item-btn' => 'sidebar item button (NOT .fi-sidebar-item-button)',
        'fi-sidebar-item-label' => 'sidebar item label',
        'fi-sidebar-group-label' => 'sidebar group label',
        'fi-sidebar-item-grouped-border-part' => 'sidebar active marker',

        // Tables
        'fi-ta-header-cell' => 'table header cell (no -label element in v5)',
        'fi-ta-header-cell-sorted' => 'sorted column',
        'fi-ta-row' => 'table row',

        // Stats
        'fi-wi-stats-overview-stat' => 'stat card',
        'fi-wi-stats-overview-stat-value' => 'stat value',
        'fi-wi-stats-overview-stat-label' => 'stat label',

        // Highlight states
        'fi-tabs-item' => 'tabs',
        'fi-pagination-item' => 'pagination',

        // Forms
        'fi-input' => 'input',
        'fi-input-wrp' => 'input wrapper',
        'fi-fo-field-label' => 'field label (NOT .fi-fo-field-wrp-label)',
        'fi-btn' => 'button',
        'fi-dropdown-panel' => 'dropdown panel',
    ];

    /** @return array<string, int> class name => reference count */
    private function filamentClasses(): array
    {
        $path = base_path(self::CSS_PATH);

        $this->assertFileExists($path, 'Filament compiled CSS not found — run the asset publish step.');

        $css = (string) file_get_contents($path);

        // The stylesheet is minified onto one line, so split it into rules
        // before harvesting class names.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $classes = [];

        foreach ($rules as $rule) {
            foreach (explode(',', $rule[1]) as $selector) {
                if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', trim($selector), $found)) {
                    foreach ($found[1] as $class) {
                        $classes[$class] = ($classes[$class] ?? 0) + 1;
                    }
                }
            }
        }

        return $classes;
    }

    public function test_every_targeted_class_exists_in_filaments_stylesheet(): void
    {
        $available = $this->filamentClasses();

        $missing = [];

        foreach (self::SELECTORS as $class => $purpose) {
            if (! isset($available[$class])) {
                $missing[] = "  .{$class} — {$purpose}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Theme targets classes that do not exist in Filament v5; these rules will never apply:\n"
                .implode("\n", $missing),
        );
    }

    public function test_the_active_sidebar_state_is_actually_reachable(): void
    {
        $available = $this->filamentClasses();

        $this->assertArrayHasKey(
            'fi-active',
            $available,
            'Filament no longer marks active items with .fi-active.',
        );

        $this->assertArrayHasKey(
            'fi-sidebar-item',
            $available,
            'The active state lives on .fi-sidebar-item, not the button.',
        );
    }

    public function test_highlight_foreground_is_not_transparent_or_inheriting_invisible_ink(): void
    {
        $theme = (string) view('filament.theme')->render();

        // The active sidebar item must paint an explicit light foreground.
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-item\.fi-active > \.fi-sidebar-item-btn\s*>\s*\.fi-sidebar-item-label\s*\{[^}]*color:\s*var\(--ct-on-navy-active\)/',
            $theme,
            'Active sidebar label has no explicit foreground colour.',
        );

        // Highlight families must all be restated, never left to the default
        // grey-on-grey pairing.
        foreach ([
            'fi-tabs-item.fi-active',
            'fi-pagination-item.fi-active',
            'fi-ta-header-cell.fi-ta-header-cell-sorted',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $theme,
                "Highlight state [{$selector}] is not themed; it will fall back to Filament defaults.",
            );
        }
    }

    public function test_sidebar_navigation_text_is_recoloured(): void
    {
        $theme = (string) view('filament.theme')->render();

        /*
         | Filament declares its own colour on `.fi-sidebar-item-label`
         | (--gray-700), so setting a colour on the parent button is not
         | enough — an explicit colour on the child beats inheritance. Without
         | a standalone rule the labels render dark grey on the navy sidebar.
         */
        $this->assertMatchesRegularExpression(
            '/(?m)^\s*\.fi-sidebar-item-label\s*\{[^}]*color:\s*var\(--ct-on-navy\)/',
            $theme,
            'Sidebar item labels have no explicit colour; Filament\'s --gray-700 will win.',
        );

        // The collapsed-sidebar active group chevron is --primary-600 at
        // (0,4,0) in Filament — navy on navy whenever the sidebar collapses.
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-group\.fi-active .*\.fi-icon\s*\{[^}]*color:\s*var\(--ct-on-navy-active\)/',
            $theme,
            'Active collapsed navigation group icon is not recoloured.',
        );

        foreach ([
            '.fi-sidebar-group-btn > .fi-icon',
            '.fi-sidebar-group-dropdown-trigger-btn > .fi-icon',
            '.fi-sidebar .fi-user-menu-trigger .fi-icon',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $theme,
                "Sidebar icon [{$selector}] is not recoloured for the navy surface.",
            );
        }
    }

    public function test_on_navy_foreground_tones_are_light_enough_to_read(): void
    {
        $palette = config('admin_theme.palette');

        // Relative luminance helper (WCAG 2.1).
        $luminance = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $channels = array_map(
                fn ($v) => (int) hexdec(str_repeat($v, 2)) / 255,
                str_split($hex, 2),
            );

            $linear = array_map(
                fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
                $channels,
            );

            return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        };

        $contrast = function (string $a, string $b) use ($luminance): float {
            $l1 = $luminance($a);
            $l2 = $luminance($b);

            return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
        };

        // Active item: white label on the lighter navy chip.
        $this->assertGreaterThanOrEqual(
            4.5,
            $contrast('#ffffff', $palette['navy-light']),
            'Active sidebar text fails WCAG AA against its chip.',
        );

        // Inactive item: near-white on the dark navy sidebar.
        $this->assertGreaterThanOrEqual(
            4.5,
            $contrast('#ffffff', $palette['navy-dark']),
            'Sidebar text fails WCAG AA against the sidebar background.',
        );

        // Body copy on the canvas.
        $this->assertGreaterThanOrEqual(
            4.5,
            $contrast($palette['body'], $palette['canvas']),
            'Body text fails WCAG AA against the page background.',
        );

        // Muted stat labels on a card.
        $this->assertGreaterThanOrEqual(
            4.5,
            $contrast($palette['muted'], $palette['surface']),
            'Stat labels fail WCAG AA against cards.',
        );

        /*
         * Regression: Filament's default sidebar label colour is --gray-700.
         * On the navy sidebar that pair is far below AA, which is exactly the
         * "invisible sidebar text" symptom.
         */
        $filamentDefaultLabel = '#364153';   // --gray-700
        $this->assertLessThan(
            3.0,
            $contrast($filamentDefaultLabel, $palette['navy-dark']),
            'Filament\'s default sidebar label is no longer low-contrast here — '
            .'if this fails, verify whether a standalone .fi-sidebar-item-label rule is still required.',
        );

        // What the theme actually ships must clear AA comfortably.
        $this->assertGreaterThanOrEqual(
            7.0,
            $contrast('#ffffff', $palette['navy-dark']),
            'Sidebar navigation text fails WCAG AAA against the sidebar background.',
        );
    }
}