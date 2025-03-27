/**
 * @sw-package framework
 */
import type {AxiosInstance} from "axios";
import type {LoginService} from "../login.service";
import ApiService from '../api.service';

/**
 * @private
 * @sw-package framework
 * Gateway for the API end point "app-permissions"
 */
export default class AppPermissionsService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'appPermissionsService';
    }

    public async acceptPermissions(name: string, permissions: string[]) : Promise<void> {
        await this.httpClient.post(
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
 * @sw-package framework
 */
export type { AppPermissionsService }
