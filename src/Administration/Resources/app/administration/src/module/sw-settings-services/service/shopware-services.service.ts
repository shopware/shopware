/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type SystemConfigApiService from 'src/core/service/api/system-config.api.service';

import type { ServiceConfiguration } from '../store/shopware-services.store';

/**
 * @private
 */
export type ServiceDescription = {
    id: string,
    active: boolean,
    name: string,
    label: string,
    icon: string,
    description: string,
    updated_at: Date,
    version: string
    requested_privileges: string[],
    privileges: string[],
}

type ServiceConfigurationConfigValues = {
    'core.services.disabled'?: boolean,
    'core.services.acceptedPermissionsRevision'?: string,
}

/**
 * API service for service handling
 * @class
 * @extends ApiService
 * @private
 */
export default class ShopwareServicesService extends ApiService {
    constructor(
        httpClient: AxiosInstance,
        loginService: LoginService,
        private readonly systemConfigService: SystemConfigApiService,
    ) {
        super(httpClient, loginService, 'service', 'application/json');
        this.name = 'ShopwareServices';
    }

    getInstalledServices(): Promise<ServiceDescription[]> {
        return this.httpClient.get('service/list', {
            headers: this.getBasicHeaders(),
        }).then((response) => {
            return response.data as ServiceDescription[];
        });
    }

    async getServicesContext(): Promise<ServiceConfiguration> {
        const configValues = await this.systemConfigService.getValues('core.services') as ServiceConfigurationConfigValues;

        return {
            disabled: configValues['core.services.disabled'],
            acceptedPermissionsRevision: configValues['core.services.acceptedPermissionsRevision'],
        };
    }

    activateService(technicalName: string): Promise<void> {
        return this.httpClient.post(`service/activate/${technicalName}`);
    }

    deactivateService(technicalName: string): Promise<void> {
        return this.httpClient.post(`service/deactivate/${technicalName}`);
    }

    acceptRevision(revision: string): Promise<ServiceConfiguration> {
        return this.httpClient.post(
            `services/permissions/grant/${revision}`, {}, {
                headers: this.getBasicHeaders(),
            }).then(() => {
                return this.getServicesContext();
            });
    }

    revokePermissions(): Promise<ServiceConfiguration> {
        return this.httpClient.post(
            `services/permissions/revoke`, {}, {
                headers: this.getBasicHeaders(),
            }).then(() => {
                return this.getServicesContext();
            });
    }
}