import Plugin from 'src/plugin-system/plugin.class';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import ElementReplaceHelper from 'src/helper/element-replace.helper';

/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 *
 * @package framework
 */
export default class FormAjaxSubmitPlugin extends Plugin {

    static options = {
        /**
         * list of selectors which should be
         * replaced when the form is submitted
         */
        replaceSelectors: false,

        /**
         * whether or not the form should be submitted on change
         * can be boole or list of selectors for the elements which should trigger
         * the submit
         *
         * @type bool|[]String
         */
        submitOnChange: false,

        /**
         * whether or not the form should only be submitted once
         *
         * @type bool
         */
        submitOnce: false,

        /**
         * route which should be redirected to
         * when submitted
         */
        redirectTo: false,

        /**
         * route which should be forwarded to
         * when submitted
         */
        forwardTo: false,

        /**
         * whether or not the form should be submitted without validation
         * @type bool
         */
        noValidate: false,
    };

    init() {
        // indicates if form was at least submitted once
        this.loaded = false;

        this.formSubmittedByCaptcha = false;

        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        if (typeof this.options.replaceSelectors === 'string') {
            this.options.replaceSelectors = [this.options.replaceSelectors];
        }

        this._callbacks = [];
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._client = new HttpClient();
        this._registerEvents();
    }

    /**
     * @param callback
     */
    addCallback(callback) {
        if (typeof callback !== 'function') throw new Error('The callback must be a function!');

        this._callbacks.push(callback);
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
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        const onSubmit = this._onSubmit.bind(this);
        this._form.removeEventListener('submit', onSubmit);
        this._form.addEventListener('submit', onSubmit);

        if (this.options.submitOnChange) {
            Array.from(this._form.elements).forEach(element => {
                if (element.removeEventListener !== undefined) {
                    element.removeEventListener('change', onSubmit);
                    element.addEventListener('change', onSubmit);
                }
            });
        }
    }

    /**
     * on submit callback for the form
     *
     * @param {Event} event
     *
     * @private
     */
    _onSubmit(event) {

        if (event.type === 'submit' && event.cancelable === true) {
            event.preventDefault();
        }

        const submitter = event.submitter || event.currentTarget;

        // checks form validity before submit
        if (!this.options.noValidate && !submitter?.hasAttribute('formNoValidate') && !this._form.checkValidity()) {
            return;
        }

        // checks if form should only be submitted once
        if (this.loaded && this.options.submitOnce) {
            return;
        }

        this.$emitter.publish('beforeSubmit');

        if (event.type === 'change' && Array.isArray(this.options.submitOnChange)) {
            const target = event.currentTarget;
            this.options.submitOnChange.some(selector => {
                if (target.matches(selector)) {
                    this._fireRequest(event);
                    return true;
                }
                return false;
            });
        } else {
            this._fireRequest(event);
        }
    }

    /**
     * fire the ajax request for the form
     *
     * @param {Event} event
     *
     * @private
     */
    _fireRequest(event) {
        this._createLoadingIndicators();
        this.$emitter.publish('beforeSubmit');

        if (!this.formSubmittedByCaptcha) {
            this.sendAjaxFormSubmit(event);
        }
    }

    /**
     * submits the form via ajax
     *
     * @param {Event|undefined} event
     */
    sendAjaxFormSubmit(event) {
        let action = this._form.getAttribute('action');
        let method = this._form.getAttribute('method');

        const submitter = event?.submitter || event?.currentTarget;
        if (submitter?.hasAttribute('formAction')) {
            action = submitter.getAttribute('formAction');
        }
        if (submitter?.hasAttribute('formMethod')) {
            method = submitter.getAttribute('formMethod').toLowerCase();
        }

        const fetchOptions = {
            method: method === 'get' ? 'get' : (method ?? 'post'),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        };

        if (method !== 'get') {
            fetchOptions.body = this._getFormData();
        }

        fetch(action, fetchOptions)
            .then(response => response.text())
            .then(response => this._onAfterAjaxSubmit(response))
            .catch(error => {
                console.error('Form submission error:', error);
                this._removeLoadingIndicators();
            });
    }

    /**
     * serializes the form
     * and appends the redirect parameter
     *
     * @returns {FormData}
     *
     * @private
     */
    _getFormData() {
        /** @type FormData **/
        const data = FormSerializeUtil.serialize(this._form);

        if (this.options.redirectTo) {
            data.append('redirectTo', this.options.redirectTo);
        } else if (this.options.forwardTo) {
            data.append('forwardTo', this.options.forwardTo);
        }

        return data;
    }

    /**
     * callback when xhr is finished
     * replaces the container content with the response
     *
     * @param {string} response
     *
     * @private
     */
    _onAfterAjaxSubmit(response) {
        if (this.options.replaceSelectors) {
            this._removeLoadingIndicators();
            ElementReplaceHelper.replaceFromMarkup(response, this.options.replaceSelectors);
            window.PluginManager.initializePlugins();
        }

        this._executeCallbacks();

        this.loaded = true;

        this.$emitter.publish('onAfterAjaxSubmit', { response });
    }

    /**
     * creates loading indicators
     *
     * @private
     */
    _createLoadingIndicators() {
        if (this.options.replaceSelectors) {
            this.options.replaceSelectors.forEach((selector) => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    ElementLoadingIndicatorUtil.create(el);
                });
            });
        }

        this.$emitter.publish('createLoadingIndicators');
    }

    /**
     * removes loading indicators
     *
     * @private
     */
    _removeLoadingIndicators() {
        if (this.options.replaceSelectors) {
            this.options.replaceSelectors.forEach((selector) => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    ElementLoadingIndicatorUtil.remove(el);
                });
            });
        }

        this.$emitter.publish('removeLoadingIndicators');
    }

    /**
     * executes all registered callbacks
     *
     * @private
     */
    _executeCallbacks() {
        this._callbacks.forEach(callback => {
            if (typeof callback !== 'function') throw new Error('The callback must be a function!');
            callback.apply(this);
        });

        this.$emitter.publish('executeCallbacks');
    }
}
