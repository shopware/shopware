import Plugin from 'src/script/helper/plugin/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';

export default class ConfirmOrderPlugin extends Plugin {

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        this.formCount = 0;
        this.formLoadedCount = 0;
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
     * register needed events
     *
     * @private
     */
    _registerEvents() {
        this._form.addEventListener('submit', this._onSubmit.bind(this));
    }

    /**
     * callback when the confirm form is being submitted
     *
     * @param {Event} event
     *
     * @private
     */
    _onSubmit(event) {
        event.preventDefault();
        PageLoadingIndicatorUtil.create();

        this.formCount = 0;
        this.formLoadedCount = 0;

        this._submitForm('#confirmShippingForm');
        this._submitForm('#confirmPaymentForm');
    }

    /**
     * submits a form
     *
     * @param {string} selector
     *
     * @private
     */
    _submitForm(selector) {
        const form = DomAccess.querySelector(document, selector, false);
        if (form && form.nodeName === 'FORM') {

            /** @type FormAjaxSubmitPlugin **/
            const formAjaxSubmitPlugin = PluginManager.getPluginInstanceFromElement(form, 'FormAjaxSubmit');

            if (formAjaxSubmitPlugin) {
                formAjaxSubmitPlugin.options.replaceSelectors = false;
                this.formCount += 1;
                form.dispatchEvent(new CustomEvent('submit'));
                formAjaxSubmitPlugin.addCallback(() => {
                    this.formLoadedCount += 1;
                    if (this.formLoadedCount === this.formCount) {
                        this._formsFinished();
                    }

                });
            }
        }
    }

    /**
     * callback when all related
     * form requests are finished
     *
     * @private
     */
    _formsFinished() {
        this._form.submit();
    }

}
