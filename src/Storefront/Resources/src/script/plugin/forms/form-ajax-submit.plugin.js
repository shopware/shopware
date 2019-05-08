import Plugin from 'src/script/helper/plugin/plugin.class';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import HttpClient from 'src/script/service/http-client.service';
import DomAccess from 'src/script/helper/dom-access.helper';
import ElementLoadingIndicatorUtil from 'src/script/utility/loading-indicator/element-loading-indicator.util';
import ElementReplaceHelper from 'src/script/helper/element-replace.helper';
import Iterator from 'src/script/helper/iterator.helper';

/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 */
export default class FormAjaxSubmitPlugin extends Plugin {

    static options = {
        replaceSelectors: false,
        loaderElementSelector: false,
        submitOnChange: false,
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        if (!this.options.replaceSelectors) {
            throw new Error('The option "replaceSelectors" must ge given when using ajax.');
        }
        if (typeof this.options.replaceSelectors === 'string') {
            this.options.replaceSelectors = [this.options.replaceSelectors];
        }

        this._client = new HttpClient(window.accessKey, window.contextToken);
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
        const onSubmit = this._onSubmit.bind(this);
        this._form.removeEventListener('submit', onSubmit);
        this._form.addEventListener('submit', onSubmit);

        if (this.options.submitOnChange) {
            this._form.removeEventListener('change', onSubmit);
            this._form.addEventListener('change', onSubmit);
        }
    }

    /**
     * on submit callback for the form
     *
     * @param event
     *
     * @private
     */
    _onSubmit(event) {
        event.preventDefault();
        this._createLoadingIndicators();
        const data = FormSerializeUtil.serialize(this._form);
        const action = DomAccess.getAttribute(this._form, 'action');

        this._client.post(action, data, this._onAfterAjaxSubmit.bind(this));
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
        this._removeLoadingIndicators();
        ElementReplaceHelper.replaceFromMarkup(response, this.options.replaceSelectors);
        window.PluginManager.initializePlugins();
    }

    /**
     * creates loading indicators
     *
     * @private
     */
    _createLoadingIndicators() {
        Iterator.iterate(this.options.replaceSelectors, (selector) => {
            const elements = DomAccess.querySelectorAll(document, selector);
            Iterator.iterate(elements, ElementLoadingIndicatorUtil.create);
        });
    }

    /**
     * removes loading indicators
     *
     * @private
     */
    _removeLoadingIndicators() {
        Iterator.iterate(this.options.replaceSelectors, (selector) => {
            const elements = DomAccess.querySelectorAll(document, selector);
            Iterator.iterate(elements, ElementLoadingIndicatorUtil.remove);
        });
    }

}
