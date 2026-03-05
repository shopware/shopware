/**
 * @sw-package framework
 */

(() => {
    const pageLoadTime = Date.now();

    const addErrorMessage = (message) => {
        const errorTextElement = document.querySelector('#page-loading-screen .loading-indicator__error');
        if (errorTextElement) {
            if (errorTextElement.textContent.length === 0) {
                errorTextElement.textContent = "An unexpected error has occurred which prevents the page from loading.\nPlease check the browser console for more details.";
            }
            errorTextElement.textContent += `\n\n${message}`;
        }
        const loadingIndicator = document.querySelector('#page-loading-screen .loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
    
        const loadingIndicatorScreen = document.querySelector('#page-loading-screen');
        if (loadingIndicatorScreen) {
            loadingIndicatorScreen.style.opacity = '1';
        }
    };

    const onError = (event) => {
        addErrorMessage(event.message);
    };

    const onUnhandledRejection = (event) => {
        addErrorMessage(event.reason);
    };

    window.addEventListener('error', onError);
    window.addEventListener('unhandledrejection', onUnhandledRejection);

    window.removePageLoadingIndicator = () => {
        // `DELAY` matches animation-delay that is used in `administration/index.html`
        const DELAY = 2000;
        const MIN_VISIBLE_TIME = 300;

        const startTime = pageLoadTime;
        const elapsedTime = Date.now() - startTime;
        // prevent flickering, show loading indicator longer than necessary:
        const buffer = elapsedTime < DELAY ? 0 : Math.max(DELAY + MIN_VISIBLE_TIME - elapsedTime, 0);

        setTimeout(() => {
            document.getElementById('page-loading-screen')?.remove();
        }, buffer);

        window.removeEventListener('error', onError);
        window.removeEventListener('unhandledrejection', onUnhandledRejection);
    };
})();
