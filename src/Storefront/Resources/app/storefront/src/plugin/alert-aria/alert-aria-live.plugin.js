export default class AlertAriaLivePlugin extends window.PluginBaseClass {

    static options = {
        delay: 900,
    };

    init() {
        this._registerEvents();
    }

    _registerEvents() {
        document.$emitter.subscribe('AlertAriaLive/Update', this._onAlertAriaAdded.bind(this));
    }

    _onAlertAriaAdded(event) {
        const payload = event.detail;
        const message = payload.message;
        let delay = this.options.delay;

        if (payload.ariaLive === 'assertive') {
            this.el.setAttribute('aria-live', 'assertive');
            delay = delay - 100;
        }

        setTimeout(() => {
            if (message.length > 0) {
                const updateEl = document.createElement('p');
                updateEl.innerHTML = message;
                this.el.appendChild(updateEl);
            }
        }, delay);
    }
}
