import Plugin from 'src/script/helper/plugin/plugin.class';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import HttpClient from 'src/script/service/http-client.service';
import DomAccess from 'src/script/helper/dom-access.helper';
import ElementLoadingIndicatorUtil from 'src/script/utility/loading-indicator/element-loading-indicator.util';


/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 */
export default class FormAjaxSubmitPlugin extends Plugin {

    static options = {
        ajaxContainerSelector: false,
        loaderElement: false,
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        if (!this.options.ajaxContainerSelector) {
            throw new Error('The option "ajaxContainerSelector" must ge given when using ajax.');
        }

        this._client = new HttpClient(window.accessKey, window.contextToken);

        this._loaderElement = this._form;
        if (this.options.loaderElement) {
            this._loaderElement = DomAccess.querySelector(document, this.options.loaderElement);
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
        const onSubmit = this._onSubmit.bind(this);
        this._form.removeEventListener('submit', onSubmit);
        this._form.addEventListener('submit', onSubmit);
    }

    /**
     * on change callback for the form
     *
     * @private
     */
    _onChange() {
        this._form.submit();
        ElementLoadingIndicatorUtil.create(this._form);
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
        ElementLoadingIndicatorUtil.create(this._loaderElement);
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
        ElementLoadingIndicatorUtil.remove(this._loaderElement);
        const container = DomAccess.querySelector(document, this.options.ajaxContainerSelector);
        container.innerHTML = response;
        window.PluginManager.executePlugins();
    }

}
