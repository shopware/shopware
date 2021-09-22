(function () {
    function isIE() {
        var userAgent = navigator.userAgent;
        return userAgent.indexOf("MSIE ") > -1 || userAgent.indexOf("Trident/") > -1;
    }

    function insertLegacyBrowserWarning() {
        var browserWarningBaseElement = document.createElement('div');
        var browserWarningElement = enrichElement(browserWarningBaseElement);

        document.body.insertBefore(browserWarningElement, window.document.body.firstChild);
    }

    function enrichElement(element) {
        element.innerHTML =
            '<strong>It seems that you are using an unsupported browser.</strong> ' +
            'If you are using Internet Explorer please consider an update to the <a href="https://www.microsoft.com/windows/microsoft-edge" target="_blank">Edge browser</a> or ' +
            '<a href="https://browsehappy.com/" target="_blank">another browser</a> to use the Shopware installer and updater.';
        element.style.width = '100%';
        element.style.padding = '30px';
        element.style.border = '2px solid #ffb75d';
        element.style.backgroundColor = '#fff6ec';

        return element;
    }

    if (isIE()) {
        insertLegacyBrowserWarning();
    }
})();
