export default class AlertAriaPlugin extends window.PluginBaseClass {

    static options = {
        ariaLive: 'polite',
        central: false,
    };

    init() {
        this._container = this.el.querySelector('.alert-content-container');

        if (this.options.central) {
            this._publishAriaLiveUpdate();
        } else {
            this._createAriaLiveElement();
        }
    }

    _publishAriaLiveUpdate() {
        document.$emitter.publish('AlertAriaLive/Update', {
            message: this._container.innerHTML,
            ariaLive: this.options.ariaLive,
        });
    }

    _createAriaLiveElement() {
        const delay = this.options.ariaLive === 'assertive' ? 900 : 1000;

        console.log(delay, this._container.innerHTML.trim());

        setTimeout(() => {
            const ariaLiveElement = document.createElement('div');
            ariaLiveElement.setAttribute('class', 'visually-hidden');
            ariaLiveElement.innerHTML = this._container.innerHTML;

            this.el.insertAdjacentElement('afterbegin', ariaLiveElement);
        }, delay);
    }
}
