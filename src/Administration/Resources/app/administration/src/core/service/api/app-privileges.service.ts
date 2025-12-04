/**
 * @sw-package framework
 */
import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 * @sw-package framework
 * Gateway for the API end point "app-permissions"
 */
export default class AppPrivilegesService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'appPrivilegesService';
    }

    public async acceptPrivileges(appName: string, privileges: string[]): Promise<void> {
        await this.httpClient.patch(
            `app-system/${appName}/privileges`,
            {
                accept: privileges,
            },
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
export type { AppPrivilegesService };
