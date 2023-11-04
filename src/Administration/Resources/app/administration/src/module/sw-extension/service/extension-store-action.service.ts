import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import type { ContextState } from 'src/app/state/context.store';
import type { BasicHeaders } from 'src/core/service/api.service';

import ApiService from 'src/core/service/api.service';

type ExtensionVariantType = 'rent' | 'buy' | 'free';
type ExtensionType = 'app' | 'plugin';
type ExtensionSource = 'local' | 'store';

type ExtensionStoreActionHeaders = BasicHeaders & {
    'sw-language-id'?: string,
}

interface DiscountCampaign {
    name: string,
    startDate: string,
    endDate: string|null,
    discount: number,
    discountedPrice: number|null,
    discountAppliesForMonths: number|null,
}

interface ExtensionVariant {
    id: number,
    type: ExtensionVariantType,
    netPrice: number,
    trialPhaseIncluded: boolean,
    discountCampaign: DiscountCampaign|null,
}

interface StoreCategory {
    id: string,
    name: string,
    parent: string|null,
    details: { [key: string]: string }
}

interface License {
    id: number,
    creationDate: string,
    variant: ExtensionVariantType,
    paymentText: string,
    netPrice: number,
    nextBookingDate: string|null,
    // eslint-disable-next-line no-use-before-define
    licensedExtension: Extension
}

interface Extension {
    id: number|null,
    localId: string|null,
    source: ExtensionSource,
    name: string,
    label: string|null,
    description: string|null,
    shortDescription: string|null,
    producerName: string|null,
    license: string|null,
    version: string|null,
    latestVersion: string|null,
    privacyPolicyLink: string|null,
    languages: string[],
    rating: number|null,
    numberOfRatings: number,
    variants: ExtensionVariant[]|null
    faq: Array<{ question: string, answer: string }>|null,
    binaries: Array<{ version: string, text: string, creationDate: string }>|null,
    images: Array<{ remoteLink: string, raw: string|null }>|null,
    icon: string|null,
    iconRaw: string|null,
    categories: StoreCategory[]|null,
    permissions: Array<{ entity: string, operation: string }>|null,
    active: boolean,
    type: ExtensionType,
    isTheme: boolean,
    configurable: boolean,
    privacyPolicyExtension: string|null,
    storeLicense: License|null,
    storeExtension: Extension|null,
    installedAt: string,
    updatedAt: string,
    notices: string[],
}

/**
 * @package merchant-services
 * @private
 */
export default class ExtensionStoreActionService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'extension', 'application/json');
        this.name = 'extensionStoreActionService';
    }

    public downloadExtension(technicalName: string): Promise<AxiosResponse<void>> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/download/${technicalName}`, {}, {
                headers: this.storeHeaders(Shopware.Context.api),
                version: 3,
            });
    }

    public installExtension(technicalName: string, type: ExtensionType): Promise<AxiosResponse<void>> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/install/${type}/${technicalName}`, {}, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public updateExtension(
        technicalName: string,
        type: ExtensionType,
        allowNewPermissions = false,
    ): Promise<AxiosResponse<void>> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/update/${type}/${technicalName}`, { allowNewPermissions }, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public activateExtension(technicalName: string, type: ExtensionType): Promise<AxiosResponse<void>> {
        return this.httpClient
            .put(`_action/${this.getApiBasePath()}/activate/${type}/${technicalName}`, {}, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public deactivateExtension(technicalName: string, type: ExtensionType): Promise<AxiosResponse<void>> {
        return this.httpClient
            .put(`_action/${this.getApiBasePath()}/deactivate/${type}/${technicalName}`, {}, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public uninstallExtension(
        technicalName: string,
        type: ExtensionType,
        removeData: boolean,
    ): Promise<AxiosResponse<void>> {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/uninstall/${type}/${technicalName}`, { keepUserData: !removeData }, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public removeExtension(technicalName: string, type: ExtensionType): Promise<AxiosResponse<void>> {
        return this.httpClient
            .delete(`_action/${this.getApiBasePath()}/remove/${type}/${technicalName}`, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public cancelLicense(licenseId: number): Promise<void> {
        return this.httpClient
            .delete(`/license/cancel/${licenseId}`, {
                headers: this.storeHeaders(),
                version: 3,
            });
    }

    public rateExtension({ authorName, extensionId, headline, rating, text, tocAccepted, version }: {
        authorName: string,
        extensionId: number,
        headline: string,
        rating: number,
        text: string,
        tocAccepted: boolean,
        version: string
    }): Promise<AxiosResponse<void>> {
        return this.httpClient.post(
            `/license/rate/${extensionId}`,
            { authorName, headline, rating, text, tocAccepted, version },
            {
                headers: this.storeHeaders(),
                version: 3,
            },
        );
    }

    public async getMyExtensions() {
        const headers = this.getBasicHeaders();

        const { data } = await this.httpClient.get<Extension[]>(
            `/_action/${this.getApiBasePath()}/installed`,
            { headers, version: 3 },
        );

        return data;
    }

    public async upload(formData: FormData): Promise<unknown> {
        const additionalHeaders = { 'Content-Type': 'application/zip' };
        const headers = this.getBasicHeaders(additionalHeaders);

        const response = await this.httpClient.post<unknown>(
            `/_action/${this.getApiBasePath()}/upload`,
            formData,
            { headers },
        );

        return ApiService.handleResponse(response);
    }

    public async refresh(): Promise<unknown> {
        const headers = this.getBasicHeaders();

        const response = await this.httpClient.post(
            `/_action/${this.getApiBasePath()}/refresh`,
            {},
            { params: { }, headers },
        );

        return ApiService.handleResponse(response);
    }

    private storeHeaders(context: ContextState['api']|null = null): ExtensionStoreActionHeaders {
        const headers = super.getBasicHeaders();

        if (context?.languageId) {
            headers['sw-language-id'] = context.languageId;
        }

        return headers;
    }
}

/**
 * @package merchant-services
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type {
    ExtensionStoreActionService,
    ExtensionVariantType,
    ExtensionType,
    ExtensionSource,
    DiscountCampaign,
    ExtensionVariant,
    StoreCategory,
    License,
    Extension,
};
