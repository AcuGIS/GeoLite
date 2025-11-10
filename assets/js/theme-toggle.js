/**
 * GeoLite Theme Toggle
 * Provides light/dark theme switching with persistence
 */
(function () {
    const storageKey = 'geolite-theme';
    const toggleSelector = '[data-theme-toggle]';
    const toggles = new Set();
    let initialized = false;

    function getMediaMatcher() {
        if (typeof window === 'undefined' || !window.matchMedia) return null;
        return window.matchMedia('(prefers-color-scheme: dark)');
    }

    function updateToggleAppearance(button, isDark) {
        if (!button) return;
        const icon = button.querySelector('.theme-toggle-icon');
        const label = button.querySelector('.theme-toggle-label');

        if (icon) {
            icon.classList.toggle('bi-moon-stars', !isDark);
            icon.classList.toggle('bi-brightness-high', isDark);
        }

        if (label) {
            label.textContent = isDark ? 'Light' : 'Dark';
        }

        button.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }

    function applyTheme(theme, persist = true) {
        if (!document.body) {
            // Defer until body exists
            document.addEventListener('DOMContentLoaded', () => applyTheme(theme, persist), { once: true });
            return;
        }

        const isDark = theme === 'dark';
        document.body.classList.toggle('dark-mode', isDark);
        document.documentElement.setAttribute('data-theme', theme);

        if (persist) {
            try {
                localStorage.setItem(storageKey, theme);
            } catch (error) {
                console.warn('Theme preference could not be saved:', error);
            }
        }

        toggles.forEach((btn) => updateToggleAppearance(btn, isDark));
    }

    function currentTheme() {
        return document.body && document.body.classList.contains('dark-mode') ? 'dark' : 'light';
    }

    function handleToggleClick(event) {
        event.preventDefault();
        const nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
    }

    function registerToggle(button) {
        if (!button || toggles.has(button)) return;

        toggles.add(button);
        button.addEventListener('click', handleToggleClick);
        updateToggleAppearance(button, currentTheme() === 'dark');
    }

    function initToggles() {
        document.querySelectorAll(toggleSelector).forEach(registerToggle);
    }

    function initTheme() {
        if (initialized) return;
        initialized = true;

        let savedTheme = null;
        try {
            savedTheme = localStorage.getItem(storageKey);
        } catch (error) {
            console.warn('Unable to read saved theme preference:', error);
        }

        if (!savedTheme) {
            const media = getMediaMatcher();
            savedTheme = media && media.matches ? 'dark' : 'light';
        }

        applyTheme(savedTheme, Boolean(savedTheme));

        const media = getMediaMatcher();
        if (media) {
            const handleChange = (event) => {
                try {
                    if (localStorage.getItem(storageKey)) return;
                } catch (error) {
                    // continue
                }
                applyTheme(event.matches ? 'dark' : 'light', false);
            };

            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', handleChange);
            } else if (typeof media.addListener === 'function') {
                media.addListener(handleChange);
            }
        }

        initToggles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }

    window.GeoliteTheme = {
        applyTheme,
        registerToggle,
        refresh: initToggles,
    };
})();


