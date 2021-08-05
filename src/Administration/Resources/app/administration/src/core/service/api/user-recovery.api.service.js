import ApiService from '../api.service';

/**
 * Custom gateway for the "user/user-recovery" routes
 * @class
 * @extends ApiService
 */
class UserRecoveryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'user') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userRecoveryService';
        this.context = Shopware.Context;
    }

    createRecovery(email) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery`;

        return this.httpClient.post(
            apiRoute,
            {
                email: email,
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }

    checkHash(hash) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery/hash`;

        return this.httpClient.get(
            apiRoute,
            {
                params: { hash: hash },
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }

    updateUserPassword(hash, password, passwordConfirm) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery/password`;

        return this.httpClient.patch(
            apiRoute,
            {
                hash: hash,
                password: password,
                passwordConfirm: passwordConfirm,
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }
}

export default UserRecoveryApiService;
