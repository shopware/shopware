import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * This plugin is used to set a csrf token on native form elements
 */
export default class FormCsrfHandler extends Plugin {

    static options = {
        formSelector: '',
    };

    init() {
        if(!this.checkHandlerShouldBeActive()) {
            return;
        }

        if (this.options.formSelector) {
            this._form = DomAccess.querySelector(this.el, this.options.formSelector);
        } else {
            this._form = this.el;
        }

        // check if validation plugin is active for this form
        this._validationPluginActive = !!window.PluginManager.getPluginInstanceFromElement(this._form, 'FormValidation');

        this.client = new HttpClient();
        this.registerEvents();
    }

    checkHandlerShouldBeActive() {
        // Deactivate if form method is not post
        return this.el.getAttribute('method').toUpperCase() === 'POST';
    }

    registerEvents() {
        this.el.addEventListener('submit', this.onSubmit.bind(this));
    }

    onSubmit(event) {
        // Abort when form.validation.plugin is active and form is not valid.
        // The validation plugin handles the submit itself in this case
        if(this._validationPluginActive) {
            if (this.el.checkValidity() === false) {
                return;
            }
        }
        event.preventDefault();
        this.$emitter.publish('beforeFetchCsrfToken');
        this.client.fetchCsrfToken(this.onTokenFetched.bind(this));
    }

    onTokenFetched(token) {
        this._form.appendChild(this.createCsrfInput(token));
        this.$emitter.publish('beforeSubmit');
        this.el.submit();
    }

    createCsrfInput(token) {
        const elem = document.createElement('input');
        elem.name = '_csrf_token';
        elem.value = token;
        elem.type = 'hidden';

        return elem;
    }
}
