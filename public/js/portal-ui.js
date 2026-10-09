/*
 * Portal UI preferences: collapsible sidebar and light/dark theme.
 *
 * Both preferences are pure UI choices, so they live in localStorage (never the
 * database) and are restored before paint by the inline script in the layouts.
 * This file only wires up the toggle buttons and the hover tooltips.
 */
(function () {
    'use strict';

    var THEME_KEY = 'sports_hub_theme';
    var SIDEBAR_KEY = 'sports_hub_sidebar';
    var root = document.documentElement;

    function readPreference(key, fallback) {
        try {
            return localStorage.getItem(key) || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writePreference(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            /* private mode or storage disabled - the UI still works for this page */
        }
    }

    function applySidebar(collapsed) {
        root.classList.toggle('sidebar-collapsed', collapsed);
        var toggle = document.getElementById('sidebar-toggle');
        if (toggle) {
            var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('data-tooltip', label);
        }
    }

    function applyTheme(theme) {
        var isDark = theme === 'dark';
        if (isDark) {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
        var toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            toggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

    /* ---- Floating tooltips (survive the sidebar's scroll clipping) ---- */
    var tooltip = null;

    function getTooltip() {
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'portal-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltip);
        }
        return tooltip;
    }

    function positionTooltip(target) {
        var tip = getTooltip();
        var rect = target.getBoundingClientRect();
        tip.style.visibility = 'hidden';
        tip.classList.add('is-visible');
        var tipRect = tip.getBoundingClientRect();
        var top = rect.top + (rect.height - tipRect.height) / 2;
        var left = rect.right + 10;

        if (left + tipRect.width > window.innerWidth - 8) {
            left = rect.left - tipRect.width - 10;
        }

        tip.style.top = Math.max(8, top) + 'px';
        tip.style.left = Math.max(8, left) + 'px';
        tip.style.visibility = 'visible';
    }

    function showTooltip(target) {
        var tip = getTooltip();
        tip.textContent = target.getAttribute('data-tooltip') || '';
        if (!tip.textContent) {
            return;
        }
        tip.classList.add('is-visible');
        positionTooltip(target);
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.classList.remove('is-visible');
        }
    }

    /*
     * Sidebar item labels only need a tooltip once the sidebar is collapsed.
     * Icon buttons in the topbar always show one.
     */
    function tooltipTarget(node) {
        var target = node && node.closest ? node.closest('[data-tooltip]') : null;
        if (!target) {
            return null;
        }
        var isSidebarItem = target.closest('.portal-nav') || target.classList.contains('portal-logout');
        if (isSidebarItem && !root.classList.contains('sidebar-collapsed')) {
            return null;
        }
        return target;
    }

    document.addEventListener('mouseover', function (event) {
        var target = tooltipTarget(event.target);
        if (target) {
            showTooltip(target);
        }
    });

    document.addEventListener('mouseout', function (event) {
        if (tooltipTarget(event.target)) {
            hideTooltip();
        }
    });

    document.addEventListener('focusin', function (event) {
        var target = tooltipTarget(event.target);
        if (target) {
            showTooltip(target);
        }
    });

    document.addEventListener('focusout', function () {
        hideTooltip();
    });

    window.addEventListener('scroll', hideTooltip, true);

    /* ---- Close open table action menus on outside click or Escape ---- */
    function closeTableMenus() {
        document.querySelectorAll('details.table-menu[open]').forEach(function (menu) {
            menu.open = false;
        });
    }

    document.addEventListener('click', function (event) {
        var menu = event.target && event.target.closest ? event.target.closest('details.table-menu') : null;
        if (!menu) {
            closeTableMenus();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeTableMenus();
        }
    });

    function init() {
        applySidebar(readPreference(SIDEBAR_KEY, 'expanded') === 'collapsed');
        applyTheme(readPreference(THEME_KEY, 'light'));

        var sidebarToggle = document.getElementById('sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                var collapsed = !root.classList.contains('sidebar-collapsed');
                applySidebar(collapsed);
                writePreference(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
                hideTooltip();
            });
        }

        var themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                writePreference(THEME_KEY, next);
                hideTooltip();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
