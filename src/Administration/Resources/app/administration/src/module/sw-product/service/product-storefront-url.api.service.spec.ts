/**
 * @sw-package inventory
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type CriteriaType from 'src/core/data/criteria.data';
import ProductStorefrontUrlApiService from './product-storefront-url.api.service';

const GERMAN_LANGUAGE_ID = 'german-language-id';
const ENGLISH_LANGUAGE_ID = 'english-language-id';

type Domain = { id: string; url: string; languageId: string; currencyId?: string };

function createService({
    domains = [] as Domain[],
    product = (languageId: string): Promise<unknown> =>
        Promise.resolve({
            data: {
                product: {
                    seoUrls: [
                        { seoPathInfo: 'Shirt-old/SW10000', isCanonical: false },
                        { seoPathInfo: `Shirt-${languageId}/SW10000`, isCanonical: true },
                    ],
                },
            },
        }),
} = {}) {
    const httpClient = {
        post: jest.fn((url: string, data: unknown, config: { headers: Record<string, string> }) =>
            product(config.headers['sw-language-id']),
        ),
    };
    const domainRepository = {
        search: jest.fn((criteria: CriteriaType) => {
            void criteria;
            return Promise.resolve(domains);
        }),
    };
    const repositoryFactory = { create: jest.fn(() => domainRepository) };
    const service = new ProductStorefrontUrlApiService(
        httpClient as unknown as HttpClient,
        { getToken: () => 'token' } as unknown as LoginService,
        repositoryFactory as unknown as RepositoryFactory,
    );

    return { service, httpClient, domainRepository, repositoryFactory };
}

function notFound(): Error {
    return Object.assign(new Error('Not found'), { response: { status: 404 } });
}

describe('module/sw-product/service/product-storefront-url.api.service', () => {
    it('asks the Storefront once per domain language and links the canonical SEO URL of each domain', async () => {
        const { service, httpClient } = createService({
            domains: [
                { id: 'domain-en', url: 'https://shop.test/en/', languageId: ENGLISH_LANGUAGE_ID },
                { id: 'domain-de', url: 'https://shop.test/de', languageId: GERMAN_LANGUAGE_ID, currencyId: 'eur' },
                { id: 'domain-www', url: 'https://www.shop.test', languageId: ENGLISH_LANGUAGE_ID },
            ],
        });

        const result = await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(httpClient.post).toHaveBeenCalledTimes(2);
        expect(httpClient.post).toHaveBeenCalledWith(
            '_proxy/store-api/sales-channel-id/product/product-id',
            {
                includes: {
                    product: ['id', 'seoUrls'],
                    seo_url: ['seoPathInfo', 'isCanonical', 'salesChannelId'],
                },
            },
            {
                headers: expect.objectContaining({
                    'sw-language-id': GERMAN_LANGUAGE_ID,
                    'sw-include-seo-urls': '1',
                }) as Record<string, string>,
                params: { skipCmsPage: 1, skipConfigurator: 1 },
            },
        );
        expect(result).toEqual({
            reachable: true,
            domains: [
                {
                    id: 'domain-de',
                    url: 'https://shop.test/de',
                    languageId: GERMAN_LANGUAGE_ID,
                    productUrl: `https://shop.test/de/Shirt-${GERMAN_LANGUAGE_ID}/SW10000`,
                },
                {
                    id: 'domain-en',
                    url: 'https://shop.test/en/',
                    languageId: ENGLISH_LANGUAGE_ID,
                    productUrl: `https://shop.test/en/Shirt-${ENGLISH_LANGUAGE_ID}/SW10000`,
                },
                {
                    id: 'domain-www',
                    url: 'https://www.shop.test',
                    languageId: ENGLISH_LANGUAGE_ID,
                    productUrl: `https://www.shop.test/Shirt-${ENGLISH_LANGUAGE_ID}/SW10000`,
                },
            ],
        });
    });

    it('loads the domains of the sales channel through the Admin API', async () => {
        const { service, domainRepository, repositoryFactory } = createService({
            domains: [{ id: 'domain-en', url: 'https://shop.test', languageId: ENGLISH_LANGUAGE_ID }],
        });

        await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(repositoryFactory.create).toHaveBeenCalledWith('sales_channel_domain');
        const [criteria] = domainRepository.search.mock.calls[0];
        expect(criteria.parse()).toEqual(
            expect.objectContaining({
                filter: [{ type: 'equals', field: 'salesChannelId', value: 'sales-channel-id' }],
            }),
        );
    });

    it('asks the Storefront for all domain languages at the same time', async () => {
        let respond = (): void => {};
        const response = new Promise((resolve) => {
            respond = () => resolve({ data: { product: { seoUrls: [] } } });
        });
        const { service, httpClient } = createService({
            domains: [
                { id: 'domain-en', url: 'https://shop.test/en', languageId: ENGLISH_LANGUAGE_ID },
                { id: 'domain-de', url: 'https://shop.test/de', languageId: GERMAN_LANGUAGE_ID },
            ],
            product: () => response,
        });

        const result = service.fetchProductUrls('product-id', 'sales-channel-id');
        await flushPromises();

        expect(httpClient.post).toHaveBeenCalledTimes(2);

        respond();
        expect((await result)?.reachable).toBe(true);
    });

    it('prefers the SEO URL of the sales channel over one for all sales channels, like the Storefront', async () => {
        const { service } = createService({
            domains: [{ id: 'domain-en', url: 'https://shop.test', languageId: ENGLISH_LANGUAGE_ID }],
            product: () =>
                Promise.resolve({
                    data: {
                        product: {
                            seoUrls: [
                                { seoPathInfo: 'Shirt-all/SW10000', isCanonical: true, salesChannelId: null },
                                { seoPathInfo: 'Shirt-own/SW10000', isCanonical: true, salesChannelId: 'sales-channel-id' },
                            ],
                        },
                    },
                }),
        });

        const result = await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(result?.domains[0].productUrl).toBe('https://shop.test/Shirt-own/SW10000');
    });

    it('links the technical product URL when the product has no SEO URL', async () => {
        const { service } = createService({
            domains: [{ id: 'domain-en', url: 'https://shop.test', languageId: ENGLISH_LANGUAGE_ID }],
            product: () => Promise.resolve({ data: { product: { seoUrls: [] } } }),
        });

        const result = await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(result?.domains[0].productUrl).toBe('https://shop.test/detail/product-id');
    });

    it.each([
        'javascript:alert(document.domain)//',
        'data:text/html,shop',
        'not a url',
    ])('links no product URL for the domain URL %s', async (url) => {
        const { service } = createService({
            domains: [{ id: 'domain-en', url, languageId: ENGLISH_LANGUAGE_ID }],
        });

        const result = await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(result?.reachable).toBe(true);
        expect(result?.domains[0]).toEqual({ id: 'domain-en', url, languageId: ENGLISH_LANGUAGE_ID, productUrl: null });
    });

    it('marks a sales channel whose product page responds with 404 as not reachable', async () => {
        const { service, httpClient } = createService({
            domains: [
                { id: 'domain-en', url: 'https://shop.test/en', languageId: ENGLISH_LANGUAGE_ID },
                { id: 'domain-de', url: 'https://shop.test/de', languageId: GERMAN_LANGUAGE_ID },
            ],
            product: () => Promise.reject(notFound()),
        });

        const result = await service.fetchProductUrls('product-id', 'sales-channel-id');

        expect(httpClient.post).toHaveBeenCalledTimes(2);
        expect(result).toEqual({
            reachable: false,
            domains: [
                { id: 'domain-de', url: 'https://shop.test/de', languageId: GERMAN_LANGUAGE_ID, productUrl: null },
                { id: 'domain-en', url: 'https://shop.test/en', languageId: ENGLISH_LANGUAGE_ID, productUrl: null },
            ],
        });
    });

    it('returns nothing for a sales channel without domain', async () => {
        const { service, httpClient } = createService();

        expect(await service.fetchProductUrls('product-id', 'sales-channel-id')).toBeNull();
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it('passes on errors other than 404', async () => {
        const { service } = createService({
            domains: [{ id: 'domain-en', url: 'https://shop.test', languageId: ENGLISH_LANGUAGE_ID }],
            product: () => Promise.reject(new Error('Server error')),
        });

        await expect(service.fetchProductUrls('product-id', 'sales-channel-id')).rejects.toThrow('Server error');
    });
});
