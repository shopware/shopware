/**
 * @module core/data/AuthStore
 */
import { Application } from 'src/core/shopware';
import getErrorCode from 'src/core/data/error-codes/login.error-codes';

class AuthStore {
    constructor() {
        this.username = '';
        this.password = '';
        this.token = '';
        this.expiry = -1;
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
            .then((response) => {
                loginService.setBearerAuthentication(response.data.access_token, response.data.expires_in);

                this.loginSuccess(response);
                return true;
            })
            .catch((response) => {
                this.loginFailure(response);
                return false;
            });
    }

    /**
     * Callback for a successful login.
     *
     * @param payload
     */
    loginSuccess(payload) {
        this.token = payload.data.access_token;
        this.expiry = payload.data.expires_in;
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

        this.token = '';
        this.expiry = -1;
        this.password = '';

        if (error.code && error.code.length) {
            const { message, title } = getErrorCode(parseInt(error.code, 10));
            this.errorTitle = title;
            this.errorMessage = message;
        }
    }
}

export default AuthStore;
