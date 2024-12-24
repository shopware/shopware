import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import ElementLoadingIndicatorUtil from "src/utility/loading-indicator/element-loading-indicator.util";

export default class CheckoutPreparePlugin extends Plugin {

    static options = {
        formSelector: '.login-form',
        emailFieldSelector: '#loginMail',
        passwordFieldSelector: '#loginPassword',
        loginActionSelector: '.login-action',
        registerActionSelector: '.register-action',
        registerActionButtonSelector: '.register-action > button',
        guestActionSelector: '.guest-action',
        allowAccountLookup: false,
        accountLookupUrl: '',
    };

    init() {
        this.loginForm = DomAccess.querySelector(document, this.options.formSelector);
        this._togglePasswordField(false);
        if (this.options.allowAccountLookup) {
            this._hideRegisterButton();
        }
        this._registerEvents();
    }

    /**
     * @private
     */
    _togglePasswordField(showField) {
        const passwordField = DomAccess.querySelector(this.loginForm, this.options.passwordFieldSelector);
        if (showField) {
            passwordField.parentElement.classList.remove('d-none');
            passwordField.required = true;
            passwordField.focus();
        } else {
            passwordField.parentElement.classList.add('d-none');
            passwordField.required = false;
        }
    }

    /**
     * @private
     */
    _hideRegisterButton() {
        DomAccess.querySelector(this.loginForm, this.options.registerActionSelector).classList.add('d-none');
    }

    /**
     * @private
     */
    _isPasswordVisible() {
        const passwordField = DomAccess.querySelector(this.loginForm, this.options.passwordFieldSelector).parentElement;
        return !passwordField.classList.contains('d-none');
    }

    /**
     * @private
     */
    _registerEvents() {
        const loginAction = DomAccess.querySelector(this.el, this.options.loginActionSelector);

        loginAction.addEventListener('click', this._onLoginAction.bind(this));
    }

    /**
     * @private
     */
    async _onLoginAction(event) {
        if (this._isPasswordVisible()) {
            return;
        }

        if (!this.options.allowAccountLookup) {
            event.preventDefault();
            this._togglePasswordField(true);

            return;
        }

        const email = DomAccess.querySelector(this.loginForm, this.options.emailFieldSelector).value;
        if (!email || !this.loginForm.checkValidity()) {
            return;
        }

        event.preventDefault();

        ElementLoadingIndicatorUtil.create(event.target);
        const accountExists = await this._doesAccountExist(email);
        if (accountExists) {
            this._togglePasswordField(true);
            ElementLoadingIndicatorUtil.remove(event.target);
        } else {
            const registerActionButton = DomAccess.querySelector(this.loginForm, this.options.registerActionButtonSelector);
            registerActionButton.click();
        }
    }

    /**
     * @private
     */
    async _doesAccountExist(email) {
        return fetch(this.options.accountLookupUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({email}),
        }).then((response) => {
            return response.json();
        }).then((data) => {
            return data.success;
        });
    }
}
