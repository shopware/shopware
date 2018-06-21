/**
 * @module core/data/AuthStore
 */
import { Application } from 'src/core/shopware';

class AuthStore {
    constructor() {
        this.username = '';
        this.password = '';
        this.token = '';
        this.expiry = -1;
        this.errorTitle = '';
        this.errorMessage = '';
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
    }

    /**
     * Callback for a failed login.
     *
     * @param payload
     */
    loginFailure(payload) {
        if (!payload.response) {
            this.errorTitle = payload.message;
            this.errorMessage = `Something went wrong requesting "${payload.config.url}".`;
            return;
        }

        let error = payload.response.data.errors;
        error = error.length > 1 ? error : error[0];

        this.token = '';
        this.expiry = -1;
        this.password = '';

        this.errorTitle = error.title;
        this.errorMessage = error.detail;
    }
}

export default AuthStore;
