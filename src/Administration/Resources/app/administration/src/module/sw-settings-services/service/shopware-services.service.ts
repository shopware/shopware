/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';

const ApiService = Shopware.Classes.ApiService;

/**
 * API service for service handling
 * @class
 * @extends ApiService
 * @private
 */
export default class ShopwareServicesService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'services');
        this.name = 'ShopwareServices';
    }

    getInstalledServices(): Array<unknown> {
        return [];
    }

    getIsContextGiven(): boolean {
        return false;
    }
}