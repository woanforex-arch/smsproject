{{--
    Corporate theme for the admin panel.

    Injected through the STYLES_AFTER render hook rather than a Vite theme so
    it applies immediately, with no `npm run build` step and no cache to clear.

    NOTE: every class below is verified against Filament v5's compiled CSS.
    v5 renamed several of them (e.g. `.fi-sidebar-item-btn`, not
    `.fi-sidebar-item-button`), so a typo here fails silently. The
    AdminThemeTest suite asserts each selector still exists upstream.
--}}
@php
    $p = config('admin_theme.palette');
@endphp
<style>
    :root {
        --ct-navy: {{ $p['navy'] }};
        --ct-navy-dark: {{ $p['navy-dark'] }};
        --ct-navy-light: {{ $p['navy-light'] }};
        --ct-ink: {{ $p['ink'] }};
        --ct-body: {{ $p['body'] }};
        --ct-muted: {{ $p['muted'] }};
        --ct-faint: {{ $p['faint'] }};
        --ct-surface: {{ $p['surface'] }};
        --ct-canvas: {{ $p['canvas'] }};
        --ct-border: {{ $p['border'] }};
        --ct-border-strong: {{ $p['border-strong'] }};

        /* Foreground tones for chrome that sits on the navy sidebar. */
        --ct-on-navy: rgb(255 255 255 / 0.86);
        --ct-on-navy-muted: rgb(255 255 255 / 0.58);
        --ct-on-navy-active: #ffffff;
    }

    /* =====================================================================
     | 1. Surfaces
     | ================================================================== */

    .fi-body,
    .fi-simple-layout {
        background-color: var(--ct-canvas);
    }

    .fi-section,
    .fi-wi-stats-overview-stat,
    .fi-dropdown-panel,
    .fi-modal-window,
    .fi-fo-repeater-item {
        border-color: var(--ct-border);
        box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04);
    }

    .fi-topbar {
        background-color: var(--ct-surface);
        border-bottom: 1px solid var(--ct-border);
        box-shadow: none;
    }

    .fi-simple-main-ctn {
        border: 1px solid var(--ct-border);
        border-radius: 0.875rem;
        background-color: var(--ct-surface);
        box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04);
    }

    .fi-simple-layout {
        padding-top: 4rem;
    }

    /* =====================================================================
     | 2. Sidebar
     |
     | The wrapper carries `fi-active`; the clickable element is
     | `.fi-sidebar-item-btn`, NOT `.fi-sidebar-item-button`. Filament's own
     | active rules use (0,3,0) specificity, so ours match it exactly and win
     | on source order.
     | ================================================================== */

    .fi-sidebar,
    .fi-sidebar-header {
        background-color: var(--ct-navy-dark);
        border-right-color: rgb(255 255 255 / 0.06);
    }

    .fi-sidebar-group-label {
        color: var(--ct-on-navy-muted);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    /*
     | Group chevrons and the collapse toggle. Filament paints these
     | --gray-400 (0,2,0), which is too dim against the navy surface, and the
     | *active* collapsed group --primary-600 (0,4,0) — navy on navy, i.e.
     | invisible whenever the sidebar is collapsed.
     */
    .fi-sidebar-group-btn > .fi-icon,
    .fi-sidebar-group-dropdown-trigger-btn > .fi-icon {
        color: var(--ct-on-navy-muted);
    }

    .fi-sidebar-group.fi-active .fi-sidebar-group-btn > .fi-icon,
    .fi-sidebar-group.fi-active .fi-sidebar-group-dropdown-trigger-btn > .fi-icon {
        color: var(--ct-on-navy-active);
    }

    /*
     | Item labels carry their own colour declaration in Filament
     | (.fi-sidebar-item-label { color: var(--gray-700) }), so setting the
     | colour on the parent button is NOT enough — an explicit colour on the
     | child beats inheritance. This rule must exist or every inactive nav
     | label renders dark grey on the navy sidebar.
     */
    .fi-sidebar-item-label {
        color: var(--ct-on-navy);
        font-weight: 500;
    }

    /* Inactive item button + icon. */
    .fi-sidebar-item-btn {
        border-radius: 0.5rem;
        color: var(--ct-on-navy);
    }

    .fi-sidebar-item-btn > .fi-icon {
        color: var(--ct-on-navy-muted);
    }

    .fi-sidebar-item-btn:hover {
        background-color: rgb(255 255 255 / 0.07);
    }

    .fi-sidebar-item-btn:hover > .fi-icon {
        color: var(--ct-on-navy);
    }

    /* Footer user menu trigger, also grey-400 in Filament. */
    .fi-sidebar .fi-user-menu-trigger .fi-icon {
        color: var(--ct-on-navy-muted);
    }

    .fi-sidebar .fi-user-menu-trigger:hover .fi-icon,
    .fi-sidebar .fi-user-menu-trigger:focus-visible .fi-icon {
        color: var(--ct-on-navy);
    }

    /* Active item: solid chip, pure white text — never navy-on-navy. */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--ct-navy-light);
        color: var(--ct-on-navy-active);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
        color: var(--ct-on-navy-active);
        font-weight: 600;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: var(--ct-on-navy-active);
    }

    .fi-sidebar-item.fi-sidebar-item-has-active-child-items > .fi-sidebar-item-btn {
        color: var(--ct-on-navy);
    }

    .fi-sidebar-item-btn > .fi-sidebar-item-grouped-border > .fi-sidebar-item-grouped-border-part {
        background-color: rgb(255 255 255 / 0.28);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-grouped-border > .fi-sidebar-item-grouped-border-part {
        background-color: var(--ct-on-navy-active);
    }

    /* =====================================================================
     | 3. Typography
     | ================================================================== */

    .fi-section-header-heading,
    .fi-simple-header-heading {
        color: var(--ct-ink);
        font-weight: 600;
        letter-spacing: -0.011em;
    }

    /* =====================================================================
     | 4. Tables
     |
     | The header label is bare text inside `.fi-ta-header-cell`; there is no
     | `-label` element in v5.
     | ================================================================== */

    .fi-ta-header-cell {
        background-color: var(--ct-canvas);
        border-bottom: 1px solid var(--ct-border-strong);
        color: var(--ct-muted);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .fi-ta-header-cell-sort-btn:hover,
    .fi-ta-header-cell-sort-btn:focus-visible {
        color: var(--ct-navy);
    }

    /* Sorted column reads clearly rather than grey-on-grey. */
    .fi-ta-header-cell.fi-ta-header-cell-sorted,
    .fi-ta-header-cell.fi-ta-header-cell-sorted .fi-ta-header-cell-sort-btn,
    .fi-ta-header-cell.fi-ta-header-cell-sorted .fi-icon {
        color: var(--ct-navy);
    }

    .fi-ta-row {
        border-bottom-color: var(--ct-border);
    }

    .fi-ta-row:hover {
        background-color: color-mix(in oklab, var(--ct-navy) 3%, transparent);
    }

    /* =====================================================================
     | 5. Stats
     | ================================================================== */

    .fi-wi-stats-overview-stat {
        border: 1px solid var(--ct-border);
        border-radius: 0.75rem;
        background-color: var(--ct-surface);
    }

    .fi-wi-stats-overview-stat-value {
        color: var(--ct-ink);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    .fi-wi-stats-overview-stat-label {
        color: var(--ct-muted);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    /* =====================================================================
     | 6. Highlight states — the "active / selected" family
     |
     | These override Filament's grey-50 / primary-700 pairs so a highlighted
     | item is never rendered as tinted text on a near-identical tint.
     | ================================================================== */

    .fi-tabs-item.fi-active {
        background-color: color-mix(in oklab, var(--ct-navy) 8%, var(--ct-surface));
    }

    .fi-tabs-item.fi-active .fi-tabs-item-label,
    .fi-tabs-item.fi-active > .fi-icon {
        color: var(--ct-navy);
        font-weight: 600;
    }

    .fi-pagination .fi-pagination-item.fi-active .fi-pagination-item-btn {
        background-color: color-mix(in oklab, var(--ct-navy) 10%, var(--ct-surface));
        border-color: transparent;
    }

    .fi-pagination .fi-pagination-item.fi-active .fi-pagination-item-label {
        color: var(--ct-navy);
        font-weight: 600;
    }

    /* Text selection must stay legible against the navy chrome. */
    ::selection {
        background-color: color-mix(in oklab, var(--ct-navy) 24%, transparent);
        color: inherit;
    }

    .fi-sidebar ::selection {
        background-color: rgb(255 255 255 / 0.28);
        color: #fff;
    }

    /* Global search term highlighting. */
    mark {
        background-color: #fef3c7;
        color: #78350f;
        border-radius: 0.1875rem;
        padding-inline: 0.125rem;
    }

    /* Focus rings — visible against both light and navy surfaces. */
    :focus-visible {
        outline-color: var(--ct-navy-light);
    }

    .fi-sidebar :focus-visible {
        outline-color: #ffffff;
    }

    /* =====================================================================
     | 7. Forms
     |
     | Field labels use `.fi-fo-field-label` in v5.
     | ================================================================== */

    .fi-input,
    .fi-select-input,
    .fi-input-wrp {
        border-color: var(--ct-border-strong);
        border-radius: 0.5rem;
    }

    .fi-input-wrp:focus-within {
        border-color: var(--ct-navy-light);
        box-shadow: 0 0 0 3px color-mix(in oklab, var(--ct-navy) 14%, transparent);
    }

    .fi-fo-field-label {
        color: var(--ct-body);
        font-weight: 500;
    }

    /* =====================================================================
     | 8. Motion — restrained, and respectful of user preference
     | ================================================================== */

    .fi-btn,
    .fi-sidebar-item-btn,
    .fi-dropdown-panel {
        transition-property: background-color, border-color, color, box-shadow, opacity;
        transition-duration: 150ms;
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-btn,
        .fi-sidebar-item-btn,
        .fi-dropdown-panel {
            transition-duration: 0.01ms !important;
        }
    }
</style>