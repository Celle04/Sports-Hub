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
    var mobileQuery = window.matchMedia('(max-width: 760px)');

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

    function syncToggleLabel() {
        var isMobile = mobileQuery.matches;
        var open = isMobile
            ? root.classList.contains('sidebar-open')
            : root.classList.contains('sidebar-collapsed');
        var label = isMobile
            ? (open ? 'Close navigation' : 'Open navigation')
            : (open ? 'Expand sidebar' : 'Collapse sidebar');
        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('data-tooltip', label);
        });
    }

    /* Desktop: collapse the rail. Mobile: slide the off-canvas drawer. */
    function applySidebar(collapsed) {
        root.classList.toggle('sidebar-collapsed', collapsed);
        syncToggleLabel();
    }

    function setDrawer(open) {
        root.classList.toggle('sidebar-open', !!open);
        syncToggleLabel();
        hideTooltip();
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

        var sidebar = target.closest('.portal-sidebar');
        if (sidebar) {
            left = Math.max(left, sidebar.getBoundingClientRect().right + 12);
        }

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
            if (root.classList.contains('sidebar-open')) {
                setDrawer(false);
            }
        }
    });

    function init() {
        applySidebar(readPreference(SIDEBAR_KEY, 'expanded') === 'collapsed');
        applyTheme(readPreference(THEME_KEY, 'light'));

        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (mobileQuery.matches) {
                    setDrawer(!root.classList.contains('sidebar-open'));
                    return;
                }
                var collapsed = !root.classList.contains('sidebar-collapsed');
                applySidebar(collapsed);
                writePreference(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
                hideTooltip();
            });
        });

        /* Mobile drawer affordances: backdrop, close button and nav links. */
        var backdrop = document.querySelector('[data-sidebar-backdrop]');
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setDrawer(false);
            });
        }

        var closeButton = document.querySelector('[data-sidebar-close]');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                setDrawer(false);
            });
        }

        document.querySelectorAll('.portal-sidebar .portal-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                setDrawer(false);
            });
        });

        var onViewportChange = function () {
            if (!mobileQuery.matches) {
                setDrawer(false);
            }
            syncToggleLabel();
        };
        if (mobileQuery.addEventListener) {
            mobileQuery.addEventListener('change', onViewportChange);
        } else if (mobileQuery.addListener) {
            mobileQuery.addListener(onViewportChange);
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
