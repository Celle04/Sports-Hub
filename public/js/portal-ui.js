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
    var SCROLL_KEY = 'sports_hub_scroll';
    var root = document.documentElement;
    var mobileQuery = window.matchMedia('(max-width: 760px)');

    /* ---- Keep the page where the user left it -------------------------------
     * Every action in the portal (closing a session, approving an
     * application, applying a filter, opening a record) submits a form or
     * follows a link and comes back on a fresh request, which normally lands
     * at the top of the page. The position is remembered before the action and
     * restored once the new page is ready.
     */
    (function scrollMemory() {
        /* Pagination is the one place where starting at the top is expected. */
        function isPaginationLink(node) {
            var link = node && node.closest ? node.closest('a[href]') : null;
            if (!link) { return false; }
            if (link.hasAttribute('download')) { return true; }
            if (link.closest('.pagination, nav[aria-label*="agination" i], [rel="next"], [rel="prev"]')) { return true; }
            return link.getAttribute('rel') === 'next' || link.getAttribute('rel') === 'prev';
        }

        function remember() {
            try {
                window.sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
                    path: window.location.pathname,
                    y: window.scrollY || window.pageYOffset || 0
                }));
            } catch (e) {
                /* private mode or storage disabled - the page still works */
            }
        }

        function restore() {
            try {
                if (pending === null) {
                    var raw = window.sessionStorage.getItem(SCROLL_KEY);
                    if (!raw) { return; }
                    window.sessionStorage.removeItem(SCROLL_KEY);
                    pending = JSON.parse(raw);
                }

                if (!pending || pending.path !== window.location.pathname) { return; }
                if (!pending.y) { return; }

                /* A link that points at a section of the page (for example the
                   attendance record that was just opened) scrolls to that
                   section on its own, so the remembered position steps aside. */
                if (window.location.hash) { return; }

                window.scrollTo(0, pending.y);
            } catch (e) {
                /* ignore: the page simply starts at the top */
            }
        }

        var pending = null;

        document.addEventListener('submit', remember, true);

        document.addEventListener('click', function (event) {
            if (isPaginationLink(event.target)) { return; }
            remember();
        }, true);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restore);
        } else {
            restore();
        }

        /* The page grows while the fonts and images settle, so the position is
           applied once more when everything has loaded. */
        window.addEventListener('load', restore);
    })();

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
        /* On desktop the rail is "expanded" unless it has been collapsed;
           on mobile the drawer is "expanded" while it is open. */
        var expanded = isMobile
            ? root.classList.contains('sidebar-open')
            : !root.classList.contains('sidebar-collapsed');
        var label = isMobile
            ? (expanded ? 'Close navigation' : 'Open navigation')
            : (expanded ? 'Collapse sidebar' : 'Expand sidebar');
        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
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
            /* The button always names the action it performs, so screen reader
               users and the tooltip agree on what the next click will do. */
            var label = isDark ? 'Light Mode' : 'Dark Mode';
            toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            toggle.setAttribute('data-tooltip', isDark ? 'Switch to light mode' : 'Switch to dark mode');
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

    /* ---- Notification dropdown ------------------------------------------------ */
    (function initNotificationDropdown() {
        var trigger = document.querySelector('[data-notification-toggle]');
        var menu = document.getElementById('portal-notification-menu');
        var closeButton = document.querySelector('[data-notification-close]');
        if (!trigger || !menu) { return; }

        function isOpen() { return !menu.hidden; }
        function setOpen(open) {
            if (open) {
                menu.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
                positionMenu();
            } else {
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        }
        function open() { setOpen(true); }
        function close() { setOpen(false); }

        /*
         * The panel is anchored to the bell with fixed positioning and then
         * clamped to the viewport, so it can never be pushed off screen: on a
         * phone it spans the width between the screen edges, on a desktop it
         * stays tucked under the trigger. When there is no room underneath it
         * flips above the trigger instead of being cut off.
         */
        function positionMenu() {
            var margin = 12;
            var gap = 8;
            var triggerRect = trigger.getBoundingClientRect();
            var viewportWidth = window.innerWidth;
            var viewportHeight = window.innerHeight;

            var width = Math.min(menu.offsetWidth || 420, viewportWidth - margin * 2);
            if (width < 260) {
                width = Math.max(0, viewportWidth - margin * 2);
            }

            var left = triggerRect.right - width;
            left = Math.max(margin, Math.min(left, viewportWidth - width - margin));

            menu.style.position = 'fixed';
            menu.style.left = left + 'px';
            menu.style.right = 'auto';
            menu.style.width = width + 'px';

            var top = triggerRect.bottom + gap;
            var roomBelow = viewportHeight - top - margin;
            var roomAbove = triggerRect.top - gap - margin;

            if (roomBelow < 180 && roomAbove > roomBelow) {
                menu.style.top = 'auto';
                menu.style.bottom = Math.max(margin, viewportHeight - triggerRect.top + gap) + 'px';
                menu.style.maxHeight = Math.max(200, roomAbove) + 'px';
                return;
            }

            menu.style.bottom = 'auto';
            menu.style.top = Math.max(margin, top) + 'px';
            menu.style.maxHeight = Math.max(200, roomBelow) + 'px';
        }

        /* Re-anchor the panel, or close it when the bell scrolls away. */
        function reposition() {
            if (!isOpen()) { return; }
            var rect = trigger.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight) {
                close();
                return;
            }
            positionMenu();
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            isOpen() ? close() : open();
        });
        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                close();
            });
        }

        // Close when clicking outside.
        document.addEventListener('click', function (event) {
            if (!isOpen()) { return; }
            if (!menu.contains(event.target) && !trigger.contains(event.target)) {
                close();
            }
        });

        // Close on Escape.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) {
                event.preventDefault();
                close();
            }
        });

        /* Keep the panel anchored to the bell while the page moves. */
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
