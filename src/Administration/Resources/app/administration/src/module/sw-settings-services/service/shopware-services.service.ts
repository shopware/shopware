/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';

import imageEditor from '../component/sw-settings-services-hero/assets/image-editor.png';
import previewGenerator from '../component/sw-settings-services-hero/assets/3d-preview-generator.png';
import copilot from '../component/sw-settings-services-hero/assets/copilot.png';

/**
 * @private
 */
export type ServiceDescription = {
    name: string,
    icon: string,
    description: string,
    active: boolean,
    lastUpdatedAt: Date,
    version: string
    needsPermissions: boolean,
}

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

    getInstalledServices(): Promise<Array<ServiceDescription>> {
        return Promise.resolve([{
            name: 'Shopware Image Editor',
            icon: imageEditor,
            description: 'Fast wie Gimp. Kann auch nichts',
            lastUpdatedAt: new Date('2025-05-10'),
            active: true,
            version: '1.0.0',
            needsPermissions: true,
        }, {
            name: 'Preview Generator',
            icon: previewGenerator,
            description: 'Man weiß vorher nie was am Ende dabei rauskommt',
            lastUpdatedAt: new Date('2625-05-10'),
            active: false,
            version: '9001.0.0',
            needsPermissions: true,
        }, {
            name: 'Copilot',
            icon: copilot,
            description: 'Nervt an jeder Ampel.',
            lastUpdatedAt: new Date('2025-05-10'),
            active: true,
            version: '4.1.0',
            needsPermissions: false,
        }]);
    }

    getServicesContext(): Promise<{ consentVersion: Date, consentGivenAt: Date|null }> {
        return Promise.resolve({
            consentGivenAt: new Date('2025-05-10'),
            consentVersion: new Date('2025-05-10'),
        });
    }

    getLegalDocumentLinks() {
        return Promise.resolve({
            feedbackLink: 'https://www.shopware.com/en/shopware-6/feedback/',
            documentationLink: 'https://docs.shopware.com',
            tosLink: 'https://www.shopware.com/en/gtc/',
        });
    }
}