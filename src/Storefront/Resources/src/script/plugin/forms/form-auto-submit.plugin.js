import Plugin from 'src/script/helper/plugin/plugin.class';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';

/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 */
export default class FormAutoSubmitPlugin extends Plugin {

    init() {
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
        if (this.el && this.el.nodeType === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        const onChange = this._onChange.bind(this);
        this._form.removeEventListener('change', onChange);
        this._form.addEventListener('change', onChange);
    }

    /**
     * on change callback for the form
     *
     * @private
     */
    _onChange() {
        this._form.submit();
        PageLoadingIndicatorUtil.open();
    }

}
