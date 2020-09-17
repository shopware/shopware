import Plugin from 'src/plugin-system/plugin.class';

/**
 * This plugin prevents submitting forms multiple times
 */
export default class FormSubmitOncePlugin extends Plugin {
    init() {
        // indicates if form was already submitted
        this.submitted = false;

        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        this._registerEvents();
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }

    /**
     * register events
     *
     * @private
     */
    _registerEvents() {
        const onSubmit = this._onSubmit.bind(this);
        this._form.removeEventListener('submit', onSubmit);
        this._form.addEventListener('submit', onSubmit);
    }

    /**
     * on submit event handler
     *
     * @param event
     *
     * @private
     */
    _onSubmit(event) {
        if (this.submitted) {
            event.preventDefault();
            return;
        }

        this.submitted = true;
    }
}
