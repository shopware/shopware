/**
 * @sw-package framework
 */
import type {AxiosInstance} from "axios";
import type {LoginService} from "../login.service";
import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppPermissionsService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'appPermissionsService';
    }

    acceptPermissions(name: string, permissions: string[]) {
        return this.httpClient.post(
            `app-system/${name}/permissions/accept`,
            permissions,
            {
                headers: this.getBasicHeaders(),
            },
        );
    }
}

/**
 * @private
 */
export type { AppPermissionsService }
