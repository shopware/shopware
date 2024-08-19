import Plugin from 'src/plugin-system/plugin.class';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';
import querystring from 'query-string';
import Debouncer from 'src/helper/debouncer.helper';

/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 *
 * @package content
 */
export default class FormAutoSubmitPlugin extends Plugin {
    static options = {
        useAjax: false,
        ajaxContainerSelector: false,

        /**
         * When this option is used the plugin only submits the form when
         * elements with one of the given selectors triggered the change event.
         *
         * @type null|[]String
         */
        changeTriggerSelectors: null,
        /**
         * When this option is used the submitting of the form is delayed
         * for the given time in milliseconds
         *
         * @type null|number
         */
        delayChangeEvent: null,

        /**
         * When true, the `FormAutoSubmitPlugin` will try to re-focus the previously focused element after page or ajax reload.
         * @type {boolean}
         */
        autoFocus: true,

        /**
         * The key under which the focus state is saved in `window.focusHandler`.
         * @type {string}
         */
        focusHandlerKey: 'form-auto-submit',
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        this._client = new HttpClient();

        if (this.options.useAjax) {
            if (!this.options.ajaxContainerSelector) {
                throw new Error(`[${this.constructor.name}] The option "ajaxContainerSelector" must be given when using ajax.`);
            }
        }

        if (this.options.changeTriggerSelectors && !Array.isArray(this.options.changeTriggerSelectors)) {
            throw new Error(`[${this.constructor.name}] The option "changeTriggerSelectors" must be an array of selector strings.`);
        }

        this._registerEvents();
        this._resumeFocusState();
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
        if (this.options.useAjax) {
            const onSubmit =
                this.options.delayChangeEvent ?
                    Debouncer.debounce(this._onSubmit.bind(this), this.options.delayChangeEvent) :
                    this._onSubmit.bind(this);

            this._form.removeEventListener('change', onSubmit);
            this._form.addEventListener('change', onSubmit);
        } else {
            const onChange =
                this.options.delayChangeEvent ?
                    Debouncer.debounce(this._onChange.bind(this), this.options.delayChangeEvent) :
                    this._onChange.bind(this);

            this._form.removeEventListener('change', onChange);
            this._form.addEventListener('change', onChange);
        }
    }

    /**
     * Checks if an event target element matches a selector from changeTriggerSelectors option.
     *
     * @param event
     * @return {boolean}
     * @private
     */
    _targetMatchesSelector(event) {
        return !!this.options.changeTriggerSelectors.find(selector => event.target.matches(selector));
    }

    /**
     * on change callback for the form
     *
     * @private
     */
    _onChange(event) {
        this._updateRedirectParameters();

        if (this.options.changeTriggerSelectors && !this._targetMatchesSelector(event)) {
            return;
        }

        this._saveFocusState(event.target);
        this._submitNativeForm();
    }

    /**
     * submits the form
     *
     * @private
     */
    _submitNativeForm() {
        this.$emitter.publish('beforeChange');

        this._form.submit();
        PageLoadingIndicatorUtil.create();
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
        PageLoadingIndicatorUtil.create();

        this.$emitter.publish('beforeSubmit');

        this._saveFocusState(event.target);
        this.sendAjaxFormSubmit();
    }

    sendAjaxFormSubmit() {
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
        PageLoadingIndicatorUtil.remove();
        const replaceContainer = DomAccess.querySelector(document, this.options.ajaxContainerSelector);
        replaceContainer.innerHTML = response;
        window.PluginManager.initializePlugins();

        this.$emitter.publish('onAfterAjaxSubmit');
    }

    _updateRedirectParameters() {
        const params = querystring.parse(window.location.search);
        const formData = FormSerializeUtil.serialize(this._form);

        Object.keys(params)
            .filter(param => !formData.has(`redirectParameters[${param}]`))
            .map(param => this._createInputForRedirectParameter(param, params[param]))
            .forEach((input) => {
                this._form.appendChild(input);
            });
    }

    _createInputForRedirectParameter(name, value) {
        const input = document.createElement('input');

        input.setAttribute('type', 'hidden');
        input.setAttribute('name', `redirectParameters[${name}]`);
        input.setAttribute('value', value);

        return input;
    }

    /**
     * @param {HTMLElement} element
     * @private
     */
    _saveFocusState(element) {
        if (!this.options.autoFocus) {
            return;
        }

        // If the form is submitted via AJAX, use the focusHandler in memory, otherwise use the persistent focusHandler.
        if (this.options.useAjax) {
            window.focusHandler.saveFocusState(this.options.focusHandlerKey, `[data-focus-id="${element.dataset.focusId}"]`);
            return;
        }

        window.focusHandler.saveFocusStatePersistent(this.options.focusHandlerKey, `[data-focus-id="${element.dataset.focusId}"]`);
    }

    /**
     * @private
     */
    _resumeFocusState() {
        if (!this.options.autoFocus) {
            return;
        }

        // If the form is submitted via AJAX, use the focusHandler in memory, otherwise use the persistent focusHandler.
        if (this.options.useAjax) {
            window.focusHandler.resumeFocusState(this.options.focusHandlerKey);
            return;
        }

        window.focusHandler.resumeFocusStatePersistent(this.options.focusHandlerKey);
    }
}
