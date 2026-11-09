/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/service/sales-channel-favorites.service';

const PRODUCT_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const STOREFRONT_ID = 'storefront-sales-channel-id';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-detail-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': { template: '<div class="mt-card"><slot></slot></div>' },
                'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
                'sw-entity-single-select': {
                    name: 'sw-entity-single-select',
                    props: ['entity', 'criteria'],
                    template: '<div class="sw-entity-single-select-stub"></div>',
                },
                'sw-text-field': true,
                'mt-number-field': true,
                'sw-single-select': true,
                'sw-sales-channel-defaults-select': true,
                'router-link': true,
                'sw-radio-field': true,
                'sw-multi-tag-ip-select': true,
                'sw-select-number-field': true,
                'sw-select-field': true,
                'sw-help-text': true,
                'sw-sales-channel-detail-hreflang': true,
                'sw-sales-channel-detail-domains': true,
                'sw-agentic-commerce-tracking-config': true,
                'sw-category-tree-field': true,
                'mt-select': true,
                'sw-custom-field-set-renderer': true,
                'sw-form-field-renderer': true,
                'mt-banner': true,
                'sw-sales-channel-measurement': true,
                'sw-time-ago': true,
            },
            provide: {
                salesChannelService: {},
                productExportService: {},
                knownIpsService: { getKnownIps: () => Promise.resolve() },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve([]),
                    getValues: () => Promise.resolve({}),
                    saveValues: () => Promise.resolve(),
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        get: () => Promise.resolve(null),
                        delete: () => Promise.resolve(),
                    }),
                },
            },
            mocks: {
                $router: { resolve: () => ({ href: '/' }) },
            },
        },
        props: {
            salesChannel: { typeId: PRODUCT_COMPARISON_TYPE_ID },
            productExport: {
                storefrontSalesChannelId: STOREFRONT_ID,
                salesChannelDomain: {},
                currencyId: 'currency-id',
            },
            customFieldSets: [],
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-base/product-comparison-currency', () => {
    beforeAll(() => {
        Shopware.Service().register('timezoneService', () => ({
            getTimezoneOptions: () => [{ label: 'UTC', value: 'UTC' }],
        }));
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ id: '8fe88c269c214ea68badf7ebe678ab96' });
        global.activeAclRoles = ['sales_channel.editor'];
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'filters the currencies by the storefront without loading the sales channels of each currency',
        async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const currencySelect = wrapper
                .findAllComponents({ name: 'sw-entity-single-select' })
                .find((select) => select.props('entity') === 'currency');

            const criteria = currencySelect.props('criteria').parse();
            expect(criteria.associations).toBeUndefined();
            expect(criteria.filter).toEqual([{ type: 'equals', field: 'salesChannels.id', value: STOREFRONT_ID }]);
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed when the data is always limited.
    it.deprecated('v6.8.0.0')('loads the sales channels of each currency before 6.8', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const currencySelect = wrapper
            .findAllComponents({ name: 'sw-entity-single-select' })
            .find((select) => select.props('entity') === 'currency');

        const criteria = currencySelect.props('criteria').parse();
        expect(criteria.associations).toHaveProperty('salesChannels');
        expect(criteria.filter).toEqual([{ type: 'equals', field: 'salesChannels.id', value: STOREFRONT_ID }]);
    });
});
