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
    domains: string[];
};

/**
 * @private
 */
export type ServicesRevision = {
    revision: string;
    links: {
        'feedback-url': string;
        'docs-url': string;
        'tos-url': string;
    };
};

/**
 * @private
 */
export type RevisionData = {
    'latest-revision': string;
    'available-revisions': ServicesRevision[];
};

type ServiceConfigurationConfigValues = {
    'core.services.disabled'?: boolean;
};

/**
 * @private
 */
export type CategorizedPermissions = { [key: string]: Array<{ entity: string; operation: string }> };

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
        };
    }

    getConsentRevision(locale: string): Promise<RevisionData> {
        return this.httpClient
            .get<RevisionData>('services/consent-revision', {
                headers: this.getBasicHeaders({
                    'Accept-Language': locale,
                }),
            })
            .then((response) => {
                return response.data;
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

    disableAllServices(): Promise<void> {
        return this.httpClient.post(
            'services/disable',
            {},
            {
                headers: this.getBasicHeaders(),
            },
        );
    }

    getCategorizedPermissions(serviceName: string): Promise<{ permissions: CategorizedPermissions }> {
        return this.httpClient
            .get(`services/categorized-permissions/${serviceName}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return (response.data as { permissions: CategorizedPermissions }) ?? {};
            });
    }
}
