/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const mockGet = jest.fn();

const defaultSalesChannelResponse = {
    id: '1a2b3c4d',
    typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
    name: 'Storefront',
    customerGroupId: 'customer-group-id',
    currencyId: 'currency-id',
    languageId: 'language-id',
    paymentMethodId: 'payment-method-id',
    shippingMethodId: 'shipping-method-id',
    countryId: 'country-id',
    navigationCategoryId: 'navigation-category-id',
    productExports: {
        first: () => ({}),
    },
};

async function createWrapper({
    salesChannelResponse = {},
    featureActive = false,
    routeName = '',
    routerPush = jest.fn(),
} = {}) {
    mockGet.mockResolvedValue({
        ...defaultSalesChannelResponse,
        ...salesChannelResponse,
        productExports: salesChannelResponse.productExports ?? defaultSalesChannelResponse.productExports,
    });

    return mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-button-process': true,
                'sw-language-switch': true,
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot /></div>',
                },
                'sw-language-info': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot /></div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'route',
                        'title',
                        'disabled',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
                'router-view': true,
                'sw-skeleton': true,
                'mt-banner': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: mockGet,
                        search: () => Promise.resolve([]),
                        delete: () => Promise.resolve(),
                        save: () => Promise.resolve(),
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve([]),
                    getValues: () => Promise.resolve({}),
                    batchSave: () => Promise.resolve(),
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
            mocks: {
                $route: {
                    params: { id: '1a2b3c4d' },
                    name: routeName,
                },
                $router: {
                    push: routerPush,
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail tabs', () => {
    beforeEach(() => {
        mockGet.mockClear();
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-sales-channel-detail');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs for storefront channels when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.sales.channel.detail.products',
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-sales-channel-detail');
        expect(tabs.props('defaultItem')).toBe('sw.sales.channel.detail.products');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-sales-channel.detail.tabBase',
                name: 'sw.sales.channel.detail.base',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.tabProducts',
                name: 'sw.sales.channel.detail.products',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.tabTheme',
                name: 'sw.sales.channel.detail.theme',
                disabled: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.tabAnalytics',
                name: 'sw.sales.channel.detail.analytics',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.tabAgenticFiles',
                name: 'sw.sales.channel.detail.agenticFiles',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs for agentic commerce channels when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.sales.channel.detail.productExportInsights',
            salesChannelResponse: {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
            },
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-sales-channel-detail');
        expect(tabs.props('defaultItem')).toBe('sw.sales.channel.detail.productExportInsights');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-sales-channel.detail.tabBase',
                name: 'sw.sales.channel.detail.base',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.productExport.tabInsights',
                name: 'sw.sales.channel.detail.productExportInsights',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.agenticCommerce.tabIntegration',
                name: 'sw.sales.channel.detail.agenticCommerceIntegration',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-sales-channel.detail.tabProductComparison',
                name: 'sw.sales.channel.detail.productComparison',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor route tab is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });
        await flushPromises();

        const productsTab = wrapper.vm.salesChannelDetailTabs.find((tab) => {
            return tab.name === 'sw.sales.channel.detail.products';
        });

        productsTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.sales.channel.detail.products',
            params: { id: '1a2b3c4d' },
        });
    });
});
