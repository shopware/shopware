/**
 * @sw-package framework
 */

window._pageLoadTime_ = Date.now();

const addErrorMessage = (message, options = { showLoadingIndicator: true, isError: false }) => {
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

window.addEventListener('error', (event) => {
    addErrorMessage(event.message);
});

window.addEventListener('unhandledrejection', (event) => {
    addErrorMessage(event.reason);
});