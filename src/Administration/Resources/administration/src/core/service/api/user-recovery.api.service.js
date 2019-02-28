import { Application } from 'src/core/shopware';
import ApiService from '../api.service';

/**
 * Custom gateway for the "user-recovery" routes
 * @class
 * @extends ApiService
 */
class UserRecoveryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'user-recovery') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userRecoveryService';
        this.contextService = Application.getContainer('init').contextService;
        this.baseUrl = this.contextService.installationPath;
    }

    createRecovery(email) {
        const apiRoute = '/admin/create-user-recovery';

        return this.httpClient.post(
            apiRoute,
            {
                email: email
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
                baseURL: this.baseUrl
            }
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }

    checkHash(hash) {
        const apiRoute = '/admin/check-user-recovery';

        return this.httpClient.post(
            apiRoute,
            {
                hash: hash
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
                baseURL: this.baseUrl
            }
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }

    updateUserPassword(hash, password, passwordConfirm) {
        const apiRoute = '/admin/user-recovery';

        return this.httpClient.post(
            apiRoute,
            {
                hash: hash,
                password: password,
                passwordConfirm: passwordConfirm
            },
            {
                params: {},
                headers: this.getBasicHeaders(),
                baseURL: this.baseUrl
            }
        ).then((response) => {
            ApiService.handleResponse(response);
        });
    }
}

export default UserRecoveryApiService;
