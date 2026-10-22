(() => {
    const card = document.querySelector('.auth-shell');
    if (!card) {
        return;
    }

    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const params = new URLSearchParams(window.location.search);
    if (params.get('transition') === 'flip') {
        params.delete('transition');
        const query = params.toString();
        window.history.replaceState(
            null,
            '',
            window.location.pathname + (query ? `?${query}` : '') + window.location.hash
        );

    }

    if (motionPreference.matches) {
        return;
    }

    const switchLink = document.querySelector('.auth-switch a');
    if (!switchLink) {
        return;
    }

    switchLink.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const destination = new URL(switchLink.href, window.location.href);
        if (destination.origin !== window.location.origin) {
            return;
        }

        event.preventDefault();
        destination.searchParams.set('transition', 'flip');
        window.location.assign(destination.href);
    });
})();
