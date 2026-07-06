import Plugin from 'src/plugin-system/plugin.class';

export default class AccountGuestAbortButtonPlugin extends Plugin {
    init() {
        this._registerEventListeners();
    }

    /**
     * @private
     */
    _registerEventListeners() {
        this.el.addEventListener('click', this._onButtonClicked.bind(this));
    }

    /**
     * Thin wrapper so tests can spy on navigation without mocking window.location
     * (non-configurable in JSDOM v26).
     */
    _assignLocation(url) {
        window.location.assign(url);
    }

    _onButtonClicked(e) {
        e.preventDefault();

        this.$emitter.publish('guest-logout');

        this._assignLocation(this.el.getAttribute('href'));
    }
}
