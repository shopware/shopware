/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type SystemConfigApiService from 'src/core/service/api/system-config.api.service';
import type { PermissionsConsent, ServiceConfiguration } from '../store/shopware-services.store';

/**
 * @private
 */
export type ServiceDescription = {
    id: string;
    active: boolean;
    name: string;
    label: string;
    icon: string;
    description: string;
    updated_at: string;
    version: string;
    requested_privileges: string[];
    privileges: string[];
};

type ServiceConfigurationConfigValues = {
    'core.services.disabled'?: boolean;
    'core.services.permissionsConsent'?: string;
};

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
        let languageId = Shopware.Store.get('session').languageId;
        if (!languageId) {
            languageId = Shopware.Context.api.languageId!;
        }

        const additionalHeaders = {
            'sw-language-id': languageId,
        };

        return this.httpClient
            .get('service/list', {
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return response.data as ServiceDescription[];
            });
    }

    async getServicesContext(): Promise<ServiceConfiguration> {
        const configValues = (await this.systemConfigService.getValues('core.services')) as ServiceConfigurationConfigValues;

        return {
            disabled: configValues['core.services.disabled'],
            permissionsConsent:
                typeof configValues['core.services.permissionsConsent'] === 'string'
                    ? (JSON.parse(configValues['core.services.permissionsConsent']) as PermissionsConsent)
                    : undefined,
        };
    }

    acceptRevision(revision: string): Promise<ServiceConfiguration> {
        return this.httpClient
            .post(
                `services/permissions/grant/${revision}`,
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(() => {
                return this.getServicesContext();
            });
    }

    revokePermissions(): Promise<ServiceConfiguration> {
        return this.httpClient
            .post(
                `services/permissions/revoke`,
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(() => {
                return this.getServicesContext();
            });
    }

    enableAllServices(): Promise<ServiceConfiguration> {
        return this.httpClient
            .post(
                'services/enable',
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(() => {
                return this.getServicesContext();
            });
    }

    disableAllServices(): Promise<ServiceConfiguration> {
        return this.httpClient
            .post(
                'services/disable',
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(() => {
                return this.getServicesContext();
            });
    }
}
