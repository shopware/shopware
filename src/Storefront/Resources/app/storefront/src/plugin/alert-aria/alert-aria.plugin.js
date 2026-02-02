export default class AlertAriaPlugin extends window.PluginBaseClass {

    static options = {
        ariaLive: 'polite',
        central: false,
    };

    init() {
        this._container = this.el.querySelector('.alert-content-container');

        this._announceAlert();
    }

    _announceAlert() {
        const delay = this.options.ariaLive === 'assertive' ? 1000 : 1500;

        // Initially hide the alert content from screenreader.
        this._container.setAttribute('aria-hidden', 'true');

        // After timeout, disable aria-hidden to trigger the parent aria-live region.
        setTimeout(() => {
            this._container.setAttribute('aria-hidden', 'false');
        }, delay);
    }
}
