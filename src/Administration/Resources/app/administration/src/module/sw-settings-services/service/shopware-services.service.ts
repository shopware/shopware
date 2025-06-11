/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type SystemConfigApiService from 'src/core/service/api/system-config.api.service';

import type { ServiceConfiguration } from '../store/shopware-services.store';

import imageEditor from '../component/sw-settings-services-hero/assets/image-editor.svg?no-inline';
import previewGenerator from '../component/sw-settings-services-hero/assets/3d-preview-generator.svg?no-inline';
import copilot from '../component/sw-settings-services-hero/assets/copilot.svg?no-inline';

/**
 * @private
 */
export type ServiceDescription = {
    name: string,
    technicalName: string,
    icon: string,
    description: string,
    active: boolean,
    lastUpdatedAt: Date,
    version: string
    requestedPermissions: string[]
}

type ServiceConfigurationConfigValues = {
    'core.service.disabled'?: boolean,
    'core.service.permissionsGrantedAt'?: string,
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

    getInstalledServices(): Promise<Array<ServiceDescription>> {
        return Promise.resolve([{
            name: 'Shopware Image Editor',
            technicalName: 'SwagImageEditor',
            icon: imageEditor,
            description: 'Fast wie Gimp. Kann auch nichts',
            lastUpdatedAt: new Date('2025-05-10'),
            active: false,
            version: '1.0.0',
            requestedPermissions: [],
        }, {
            name: 'Preview Generator',
            technicalName: 'SwagPreviewGenerator',
            icon: previewGenerator,
            description: 'Man weiß vorher nie was am Ende dabei rauskommt',
            lastUpdatedAt: new Date('2625-05-10'),
            active: true,
            version: '9001.0.0',
            requestedPermissions: [],
        }, {
            name: 'Copilot',
            technicalName: 'SwagCopilot',
            icon: copilot,
            description: 'Nervt an jeder Ampel.',
            lastUpdatedAt: new Date('2025-05-10'),
            active: true,
            version: '4.1.0',
            requestedPermissions: ['system_config:write'],
        }]);
    }

    async getServicesContext(): Promise<ServiceConfiguration> {
        const configValues = await this.systemConfigService.getValues('core.service') as ServiceConfigurationConfigValues;

        return {
            disabled: configValues['core.service.disabled'],
            permissionsGrantedAt: configValues['core.service.permissionsGrantedAt'],
        };
    }

    activateService(technicalName: string): Promise<void> {
        return this.httpClient.post(`service/activate/${technicalName}`);
    }

    deactivateService(technicalName: string): Promise<void> {
        return this.httpClient.post(`service/deactivate/${technicalName}`);
    }
}