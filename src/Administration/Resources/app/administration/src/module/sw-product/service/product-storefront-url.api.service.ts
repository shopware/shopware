/**
 * @private
 * @sw-package inventory
 */
import type { HttpClient } from 'src/core/factory/http-client.types';

import ApiService from 'src/core/service/api.service';
import type { LoginService } from 'src/core/service/login.service';
import type StoreContextService from 'src/core/service/api/store-context.api.service';

/**
 * @private
 */
export type ProductStorefrontUrls = {
    reachable: boolean;
    domains: {
        id: string;
        url: string;
        productUrl: string | null;
    }[];
};

type SalesChannelDomain = {
    id: string;
    url: string;
    languageId: string;
};

type StoreApiProduct = {
    product: {
        seoUrls?: {
            seoPathInfo: string;
            isCanonical: boolean;
        }[];
    };
};

/**
 * @private
 */
export default class ProductStorefrontUrlApiService extends ApiService {
    private readonly contextStoreService: StoreContextService;

    constructor(
        httpClient: HttpClient,
        loginService: LoginService,
        contextStoreService: StoreContextService,
        apiEndpoint = '_proxy/store-api',
    ) {
        super(httpClient, loginService, apiEndpoint);

        this.name = 'productStorefrontUrlApiService';
        this.contextStoreService = contextStoreService;
    }

    /**
     * Returns whether the Storefront shows the product in the sales channel, and the product URL per domain.
     * Returns null when the sales channel has no domain.
     */
    async fetchProductUrls(productId: string, salesChannelId: string): Promise<ProductStorefrontUrls | null> {
        const domains = await this.fetchDomains(salesChannelId);

        if (domains.length === 0) {
            return null;
        }

        const paths: Record<string, string> = {};
        for (const languageId of new Set(domains.map((domain) => domain.languageId))) {
            const path = await this.fetchProductPath(productId, salesChannelId, languageId);

            if (path === null) {
                return {
                    reachable: false,
                    domains: domains.map(({ id, url }) => ({ id, url, productUrl: null })),
                };
            }

            paths[languageId] = path;
        }

        return {
            reachable: true,
            domains: domains.map(({ id, url, languageId }) => ({
                id,
                url,
                productUrl: ProductStorefrontUrlApiService.buildProductUrl(url, paths[languageId]),
            })),
        };
    }

    private async fetchDomains(salesChannelId: string): Promise<SalesChannelDomain[]> {
        const response = await this.contextStoreService.getSalesChannelContext(
            salesChannelId as EntityKey<'sales_channel'>,
            null,
        );
        const context = response.data as { salesChannel?: { domains?: SalesChannelDomain[] } };

        return [...(context.salesChannel?.domains ?? [])].sort((a, b) => a.url.localeCompare(b.url));
    }

    private static buildProductUrl(domainUrl: string, path: string): string | null {
        try {
            const { protocol } = new URL(domainUrl);

            if (protocol !== 'http:' && protocol !== 'https:') {
                return null;
            }
        } catch {
            return null;
        }

        return `${domainUrl.replace(/\/+$/, '')}${path}`;
    }

    private async fetchProductPath(productId: string, salesChannelId: string, languageId: string): Promise<string | null> {
        try {
            const response = await this.httpClient.get<StoreApiProduct>(
                `${this.apiEndpoint}/${salesChannelId}/product/${productId}`,
                {
                    headers: {
                        ...this.getBasicHeaders(),
                        'sw-language-id': languageId,
                        'sw-include-seo-urls': '1',
                    },
                    params: { skipCmsPage: 1, skipConfigurator: 1 },
                },
            );
            const seoUrl = response.data.product.seoUrls?.find(({ isCanonical }) => isCanonical);

            return seoUrl ? `/${seoUrl.seoPathInfo.replace(/^\/+/, '')}` : `/detail/${productId}`;
        } catch (error) {
            if ((error as { response?: { status?: number } }).response?.status === 404) {
                return null;
            }

            throw error;
        }
    }
}
