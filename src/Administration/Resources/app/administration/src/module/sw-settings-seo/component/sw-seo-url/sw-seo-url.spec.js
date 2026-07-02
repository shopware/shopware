/**
 * @sw-package inventory
 */

import { mount, flushPromises } from '@vue/test-utils';

// from Defaults.php
const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const HEADLESS_TYPE_ID = 'f183ee5650cf4bdb8a774337575067a6';
const PRODUCT_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const AGENTIC_COMMERCE_TYPE_ID = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

const STOREFRONT_SALES_CHANNEL_ID = '863137935ecf48999d69096de547b090';
const HEADLESS_SALES_CHANNEL_ID = 'headless-sales-channel-id';
const PRODUCT_COMPARISON_SALES_CHANNEL_ID = 'product-comparison-sales-channel-id';
const AGENTIC_COMMERCE_SALES_CHANNEL_ID = 'agentic-commerce-sales-channel-id';

const FK = '4066b6039fcf41f089bdf859cc6ce662';
const LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // Shopware.Context.api.languageId in tests

const SALES_CHANNELS = [
    { id: STOREFRONT_SALES_CHANNEL_ID, typeId: STOREFRONT_TYPE_ID },
    { id: HEADLESS_SALES_CHANNEL_ID, typeId: HEADLESS_TYPE_ID },
    { id: PRODUCT_COMPARISON_SALES_CHANNEL_ID, typeId: PRODUCT_COMPARISON_TYPE_ID },
    { id: AGENTIC_COMMERCE_SALES_CHANNEL_ID, typeId: AGENTIC_COMMERCE_TYPE_ID },
];

const STORE_API_CONFIGS = [
    { routeName: 'store-api.product.detail', entityName: 'product', template: '', pathInfo: `/store-api/product/${FK}` },
    { routeName: 'store-api.category.detail', entityName: 'category', template: '', pathInfo: `/store-api/category/${FK}` },
    {
        routeName: 'store-api.landing-page.detail',
        entityName: 'landing_page',
        template: '',
        pathInfo: `/store-api/landing-page/${FK}`,
    },
];

const invalidHeadlessError = { detail: 'sw-seo-url-template-card.general.invalidHeadlessUrlTemplate' };

// store-api configs returned by the mocked seoUrlService.getStoreApiConfigs, overridable per test
let storeApiConfigsResponse = [];

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function setSalesChannels(channels = SALES_CHANNELS) {
    Shopware.Store.get('swSeoUrl').salesChannelCollection = createEntityCollection(channels);
}

function seoUrl(overrides = {}) {
    return {
        id: 'c0221c1f712a4f369a79e924a10fa398',
        foreignKey: FK,
        languageId: '12345678',
        pathInfo: `/detail/${FK}`,
        routeName: 'frontend.detail.page',
        salesChannelId: null,
        seoPathInfo: 'Awesome-product/',
        ...overrides,
    };
}

function expectedCurrentSeoUrl({ routeName, pathInfo, salesChannelId, foreignKey = FK }) {
    return { foreignKey, isCanonical: true, languageId: LANGUAGE_ID, pathInfo, routeName, salesChannelId, isModified: true };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-seo-url', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'mt-card': { template: '<div><slot name="toolbar"></slot></div>' },
                'sw-sales-channel-switch': true,
                'sw-text-field': true,
                'sw-inherit-wrapper': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        search: () =>
                            Promise.resolve(entity === 'sales_channel' ? createEntityCollection(SALES_CHANNELS) : []),
                        create: () => ({}),
                        schema: { entity: {} },
                    }),
                },
                seoUrlService: {
                    getStoreApiConfigs: jest.fn(() => Promise.resolve(storeApiConfigsResponse)),
                },
            },
        },
    });
}

describe('src/module/sw-settings-seo/component/sw-seo-url', () => {
    let wrapper;

    beforeEach(async () => {
        storeApiConfigsResponse = [];
        wrapper = await createWrapper();
        Shopware.Store.get('swSeoUrl').currentSeoUrl = '';
    });

    it('sales channel switch should not be disabled', async () => {
        await wrapper.setData({ showEmptySeoUrlError: false });

        expect(wrapper.find('sw-sales-channel-switch-stub').attributes().disabled).toBeUndefined();
    });

    it('sales channel switch should be disabled', async () => {
        wrapper.vm.showEmptySeoUrlError = false;
        await wrapper.setProps({ disabled: true });

        expect(wrapper.find('sw-sales-channel-switch-stub').attributes().disabled).toBe('true');
    });

    it('should update currentSeoUrl when defaultSeoUrl empty', async () => {
        await wrapper.setProps({
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
                    seoPathInfo: 'Computers/',
                }),
            ],
            salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: STOREFRONT_SALES_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.navigation.page',
                pathInfo: `/navigation/${FK}`,
                salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
            }),
        );
    });

    it('should update currentSeoUrl when defaultSeoUrl empty and the salesChannel has no seo urls yet', async () => {
        await wrapper.setProps({
            // the seo url belongs to another sales channel, so no default seo url exists for the selected one
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    salesChannelId: FK,
                    seoPathInfo: 'Computers/',
                }),
            ],
            salesChannelId: 'a-sales-channel-without-seo-urls',
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: STOREFRONT_SALES_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.navigation.page',
                pathInfo: `/navigation/${FK}`,
                salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
            }),
        );
    });

    it.each([
        ['seo/url%/1'],
        ['foo/bar#baz'],
        ['foo\\bar'],
    ])('reports a validation error for disallowed character in "%s"', async (invalidPath) => {
        Shopware.Store.get('swSeoUrl').currentSeoUrl = {
            seoPathInfo: invalidPath,
        };

        expect(wrapper.vm.seoPathInfoError).toEqual(
            expect.objectContaining({ code: 'CONTENT__SEO_URL_INVALID_CHARACTERS' }),
        );
    });

    it.each([
        ['Computers/Laptops'],
        ['Pepper-white-ground-pearl/SW10098'],
        ['foo/bar?x=1'],
        ['caf%C3%A9/SW10098'],
        [''],
        [null],
    ])('accepts "%s" as SEO path', async (validPath) => {
        Shopware.Store.get('swSeoUrl').currentSeoUrl = {
            seoPathInfo: validPath,
        };

        expect(wrapper.vm.seoPathInfoError).toBeNull();
    });

    it('should update currentSeoUrl when defaultSeoUrl not empty', async () => {
        const defaultSeoUrl = {
            id: '123456789',
            foreignKey: '12345678910111213',
            languageId: '1234567891011',
            pathInfo: '/navigation/123456789',
            routeName: 'frontend.product-detail.page',
            salesChannelId: null,
            seoPathInfo: 'Product-detail/',
        };

        await wrapper.setProps({
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
                    seoPathInfo: 'Computers/',
                }),
                defaultSeoUrl,
            ],
            salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: STOREFRONT_SALES_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual(defaultSeoUrl);
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                foreignKey: '12345678910111213',
                routeName: 'frontend.product-detail.page',
                pathInfo: '/navigation/123456789',
                salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
            }),
        );
    });

    it.each([
        'product',
        'category',
        'landing_page',
    ])('should create a new %s seo url from the resolved store-api config for a headless sales channel', async (entity) => {
        setSalesChannels();
        storeApiConfigsResponse = STORE_API_CONFIGS;
        const config = STORE_API_CONFIGS.find((entry) => entry.entityName === entity);

        await wrapper.setProps({ entity, urls: [seoUrl({ routeName: 'frontend.some.page', salesChannelId: null })] });
        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        expect(wrapper.vm.seoUrlService.getStoreApiConfigs).toHaveBeenCalledWith(FK);
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: config.routeName,
                pathInfo: config.pathInfo,
                salesChannelId: HEADLESS_SALES_CHANNEL_ID,
            }),
        );
    });

    it('should fall back to the default seo url when the entity has no store-api equivalent', async () => {
        setSalesChannels();
        // the endpoint returns configs for other entities only, none matching this component's entity
        storeApiConfigsResponse = [STORE_API_CONFIGS.find((entry) => entry.entityName === 'category')];

        await wrapper.setProps({ entity: 'product', urls: [seoUrl()] });
        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.detail.page',
                pathInfo: `/detail/${FK}`,
                salesChannelId: HEADLESS_SALES_CHANNEL_ID,
            }),
        );
    });

    it('should not request store-api configs for a non-headless sales channel', async () => {
        setSalesChannels();
        storeApiConfigsResponse = STORE_API_CONFIGS;

        await wrapper.setProps({ entity: 'product', urls: [seoUrl()] });
        await wrapper.vm.onSalesChannelChanged(STOREFRONT_SALES_CHANNEL_ID);
        await flushPromises();

        expect(wrapper.vm.seoUrlService.getStoreApiConfigs).not.toHaveBeenCalled();
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.detail.page',
                pathInfo: `/detail/${FK}`,
                salesChannelId: STOREFRONT_SALES_CHANNEL_ID,
            }),
        );
    });

    it.each([
        [
            'storefront',
            STOREFRONT_SALES_CHANNEL_ID,
            false,
        ],
        [
            'headless',
            HEADLESS_SALES_CHANNEL_ID,
            false,
        ],
        [
            'product comparison',
            PRODUCT_COMPARISON_SALES_CHANNEL_ID,
            true,
        ],
        [
            'agentic commerce',
            AGENTIC_COMMERCE_SALES_CHANNEL_ID,
            true,
        ],
        [
            'none selected',
            null,
            false,
        ],
    ])('should flag SEO URL support for a %s sales channel', async (_, salesChannelId, unsupported) => {
        setSalesChannels();

        wrapper.vm.currentSalesChannelId = salesChannelId;

        expect(wrapper.vm.isUnsupportedSalesChannel).toBe(unsupported);
    });

    it('should only expose the not-supported help text for unsupported sales channel types', async () => {
        setSalesChannels();

        // headless is allowed and shows no help text, product comparison is not
        wrapper.vm.currentSalesChannelId = HEADLESS_SALES_CHANNEL_ID;
        expect(wrapper.vm.seoUrlHelptext).toBeNull();

        wrapper.vm.currentSalesChannelId = PRODUCT_COMPARISON_SALES_CHANNEL_ID;
        expect(wrapper.vm.seoUrlHelptext).toBe('sw-seo-url.textSeoUrlsNotSupported');
    });

    it('should not flag the seo path for non-headless sales channels', async () => {
        setSalesChannels();
        Shopware.Store.get('swSeoUrl').currentSeoUrl = { seoPathInfo: 'some/relative/path' };

        wrapper.vm.currentSalesChannelId = STOREFRONT_SALES_CHANNEL_ID;

        expect(wrapper.vm.isHeadlessSalesChannel).toBe(false);
        expect(wrapper.vm.seoPathInfoError).toBeNull();
    });

    it.each([
        [
            'a relative path',
            'some/relative/path',
            invalidHeadlessError,
        ],
        [
            'an empty value',
            '',
            null,
        ],
        [
            'a blank value',
            '   ',
            null,
        ],
        [
            'a null value',
            null,
            null,
        ],
        [
            'a value without protocol',
            'www.example.com/product',
            invalidHeadlessError,
        ],
        [
            'a valid http url',
            'http://example.com/product',
            null,
        ],
        [
            'a valid https url',
            'https://example.com/product',
            null,
        ],
    ])('should flag the seo path for a headless sales channel with %s', async (_, seoPathInfo, expectedError) => {
        setSalesChannels();
        Shopware.Store.get('swSeoUrl').currentSeoUrl = { seoPathInfo };

        wrapper.vm.currentSalesChannelId = HEADLESS_SALES_CHANNEL_ID;

        expect(wrapper.vm.isHeadlessSalesChannel).toBe(true);
        expect(wrapper.vm.seoPathInfoError).toEqual(expectedError);
    });
});
