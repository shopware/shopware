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

    _onButtonClicked(e) {
        e.preventDefault();

        this.$emitter.publish('guest-logout');

        window.location.assign(this.el.getAttribute('href'));
    }
}
