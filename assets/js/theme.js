(() => {
    const storageKey = 'staywise-theme';
    const validThemes = ['dark', 'light'];

    let theme = 'dark';
    try {
        const savedTheme = window.localStorage.getItem(storageKey);
        if (validThemes.includes(savedTheme)) {
            theme = savedTheme;
        }
    } catch (error) {
        console.warn('Theme preference could not be loaded from local storage.', error);
    }

    const applyTheme = (nextTheme) => {
        theme = validThemes.includes(nextTheme) ? nextTheme : 'dark';
        document.documentElement.dataset.theme = theme;

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const nextLabel = theme === 'dark' ? 'Light mode' : 'Dark mode';
            const nextIcon = theme === 'dark' ? 'lucide:sun' : 'lucide:moon';
            button.setAttribute('aria-label', `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`);
            button.setAttribute('aria-pressed', String(theme === 'light'));

            const label = button.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = nextLabel;
            }

            const icon = button.querySelector('.theme-toggle-icon');
            if (icon) {
                icon.setAttribute('icon', nextIcon);
            }
        });
    };

    applyTheme(theme);
    document.addEventListener('DOMContentLoaded', () => applyTheme(theme), { once: true });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-toggle]');
        if (!button) {
            return;
        }

        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);

        try {
            window.localStorage.setItem(storageKey, nextTheme);
        } catch (error) {
            console.warn('Theme preference could not be saved to local storage.', error);
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey && validThemes.includes(event.newValue)) {
            applyTheme(event.newValue);
        }
    });
})();
