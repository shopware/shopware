/**
 * @module core/data/AuthStore
 */
import { Application } from 'src/core/shopware';
import getErrorCode from 'src/core/data/error-codes/login.error-codes';

class AuthStore {
    constructor() {
        this.username = '';
        this.password = '';
        this.errorTitle = '';
        this.errorMessage = '';
        this.lastUrl = '';
    }

    /**
     * Logs in the user with his credentials.
     *
     * @return {Promise<T>}
     */
    loginUserWithPassword() {
        const providerContainer = Application.getContainer('service');
        const loginService = providerContainer.loginService;

        return loginService.loginByUsername(this.username, this.password)
            .then(() => {
                this.loginSuccess();
                return true;
            })
            .catch((response) => {
                this.loginFailure(response);
                return false;
            });
    }

    /**
     * Callback for a successful login. Resets the state object of the store.
     *
     * @return {void}
     */
    loginSuccess() {
        this.errorTitle = '';
        this.errorMessage = '';
        this.password = '';
        this.lastUrl = '';
    }

    /**
     * Callback for a failed login.
     *
     * @param payload
     */
    loginFailure(payload) {
        const generalMessage = 'sw-login.index.messageGeneralRequestError';
        this.lastUrl = payload.config.url;

        if (!payload.response) {
            this.errorTitle = payload.message;
            this.errorMessage = generalMessage;

            return;
        }

        let error = payload.response.data.errors;
        error = error.length > 1 ? error : error[0];

        this.password = '';

        if (error.code && error.code.length) {
            const { message, title } = getErrorCode(parseInt(error.code, 10));
            this.errorTitle = title;
            this.errorMessage = message;
        }
    }
}

export default AuthStore;
