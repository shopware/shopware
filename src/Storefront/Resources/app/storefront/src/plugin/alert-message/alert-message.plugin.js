export default class AlertMessagePlugin extends window.PluginBaseClass {

    static options = {
        ariaLive: 'polite',
        screenReaderDelay: 500,
    };

    init() {
        this._applyAriaAttributes();
    }

    _applyAriaAttributes() {
        setTimeout(() => {
            this.el.setAttribute('role', 'alert');
            this.el.setAttribute('aria-live', this.options.ariaLive);
        }, this.options.screenReaderDelay);
    }
}
