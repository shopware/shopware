/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
 */
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-button';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-overview';
import ShopwareDiscountCampaignService from 'src/app/service/discount-campaign.service';

async function createWrapper(options = {}) {
    const { privileges = [], featureActive = false } = Array.isArray(options) ? { privileges: options } : options;

    return mount(await wrapTestComponent('sw-product-detail-variants', { sync: true }), {
        global: {
            provide: {
                feature: {
                    isActive: (featureName) => featureName === 'v6.8.0.0' && featureActive,
                },
                repositoryFactory: {
                    create: () => ({
                        search: jest.fn(() =>
                            Promise.resolve([
                                {
                                    id: '1',
                                    name: 'group-1',
                                },
                            ]),
                        ),
                        delete: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve({
                                configuratorSettings: [
                                    {
                                        option: {
                                            groupId: 1,
                                        },
                                    },
                                ],
                            });
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            mocks: {
                $t: (key) => key,
                $route: {
                    meta: {
                        $module: {
                            icon: 'regular-content',
                        },
                    },
                },
            },
            stubs: {
                'mt-card': {
                    template: `
                    <div class="mt-card">
                        <slot name="tabs"></slot>
                        <slot name="grid"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                  <div class="sw-data-grid">
                  <template v-for="item in dataSource">
                    <slot name="actions" v-bind="{ item }"></slot>
                  </template>
                  </div>
                `,
                },
                'sw-context-menu-item': true,
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-modal': true,
                'sw-skeleton': true,
                'sw-product-variants-overview': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    props: {
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        small: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                    },
                    template: '<div class="sw-tabs"><slot></slot></div>',
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    props: {
                        name: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        activeTab: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                    },
                    template: '<button class="sw-tabs-item"><slot></slot></button>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: [
                        'new-item-active',
                    ],
                    props: {
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                    },
                    template: '<div class="mt-tabs"></div>',
                },
                'sw-product-modal-variant-generation': true,
                'sw-product-modal-delivery': true,
                'sw-product-add-properties-modal': true,
            },
        },
    });
}

describe('src/module/sw-product/view/sw-product-detail-variants', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareDiscountCampaignService', () => {
            return new ShopwareDiscountCampaignService();
        });

        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = {
            media: [],
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
        };
        store.product = {
            id: 'test-product-id',
            isNew: () => false,
            getEntityName: () => 'product',
            media: [],
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
            purchasePrices: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            ],
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 100,
                    net: 84.034,
                },
            ],
            configuratorSettings: [],
            children: [],
        };
        store.modeSettings = [
            'general_information',
            'prices',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
        ];
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'general_information',
                        label: 'sw-product.detailBase.cardTitleProductInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'prices',
                        label: 'sw-product.detailBase.cardTitlePrices',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'deliverability',
                        label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'visibility_structure',
                        label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'labelling',
                        label: 'sw-product.detailBase.cardTitleSettings',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            store.creationStates = 'is-physical';
        }
        store.creationType = 'physical';
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.isLoading = false;
        wrapper.vm.propertiesAvailable = true;
        await nextTick();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-variant-card-tabs');
        expect(tabs.props('small')).toBe(false);
        expect(tabs.props('defaultItem')).toBe('all');
        expect(wrapper.findAllComponents({ name: 'sw-tabs-item' })).toHaveLength(3);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        await flushPromises();
        wrapper.vm.isLoading = false;
        wrapper.vm.propertiesAvailable = true;
        await nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-variant-card-tabs');
        expect(tabs.props('defaultItem')).toBe('all');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-product.variations.variationCard.tabs.allProducts',
                name: 'all',
            },
            {
                label: 'sw-product.variations.variationCard.tabs.physicalProducts',
                name: 'physical',
            },
            {
                label: 'sw-product.variations.variationCard.tabs.digitalProducts',
                name: 'digital',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should switch active tab when meteor tabs emits a new active item', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        await flushPromises();
        wrapper.vm.isLoading = false;
        wrapper.vm.propertiesAvailable = true;
        await nextTick();

        wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'digital');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('digital');
    });

    it('should display a customized empty state if there are neither variants nor properties', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.groups = [{}];
        wrapper.vm.propertiesAvailable = false;
        wrapper.vm.isLoading = false;
        await nextTick();

        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-product.variations.emptyStatePropertyTitle');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe(
            'sw-product.variations.emptyStatePropertyDescription',
        );
    });

    it('should not load data when product.id is missing', async () => {
        const store = Shopware.Store.get('swProductDetail');
        const originalProduct = store.product;

        // Set product without id
        store.product = {
            isNew: () => false,
            getEntityName: () => 'product',
            media: [],
            configuratorSettings: [],
            children: [],
        };

        const wrapper = await createWrapper();
        const loadOptionsSpy = jest.spyOn(wrapper.vm, 'loadOptions');

        await flushPromises();

        // loadOptions should not be called when product.id is missing
        expect(loadOptionsSpy).not.toHaveBeenCalled();

        // Restore original product
        store.product = originalProduct;
    });

    it('should split the product states string into an array', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.activeTab = 'is-foo,is-bar';
        await nextTick();
        await flushPromises();

        expect(wrapper.vm.currentProductStates).toEqual([
            'is-foo',
            'is-bar',
        ]);
    });

    it('should compute configSettingGroups from productEntity.configuratorSettings and groups', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.groups = [
            { id: 'id-1', name: 'group-1' },
            { id: 'id-2', name: 'group-2' },
            { id: 'other', name: 'other' },
        ];
        wrapper.vm.productEntity = {
            configuratorSettings: [
                { option: { groupId: 'id-1' } },
                { option: { groupId: 'id-2' } },
            ],
        };

        expect(wrapper.vm.configSettingGroups).toEqual([
            { id: 'id-1', name: 'group-1' },
            { id: 'id-2', name: 'group-2' },
        ]);
    });

    it('should return empty configSettingGroups when product has no configurator settings', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.groups = [{ id: 'id-1', name: 'group-1' }];
        wrapper.vm.productEntity = { configuratorSettings: [] };

        expect(wrapper.vm.configSettingGroups).toEqual([]);
    });

    it('should filter out missing groups in configSettingGroups when id not in groups', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.groups = [{ id: 'id-1', name: 'group-1' }];
        wrapper.vm.productEntity = {
            configuratorSettings: [
                { option: { groupId: 'id-1' } },
                { option: { groupId: 'id-missing' } },
            ],
        };

        expect(wrapper.vm.configSettingGroups).toEqual([{ id: 'id-1', name: 'group-1' }]);
    });

    it('should not call loadConfigSettingGroups in loadData (deprecated, now computed)', async () => {
        const wrapper = await createWrapper();
        const loadConfigSettingGroupsSpy = jest.spyOn(wrapper.vm, 'loadConfigSettingGroups');

        wrapper.vm.loadData();
        await flushPromises();

        expect(loadConfigSettingGroupsSpy).not.toHaveBeenCalled();
    });

    it('should have loadConfigSettingGroups as no-op when called directly (deprecated)', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.groups = [{ id: '1', name: 'g1' }];
        wrapper.vm.productEntity = {
            configuratorSettings: [{ option: { groupId: '1' } }],
        };

        expect(() => wrapper.vm.loadConfigSettingGroups()).not.toThrow();
        expect(wrapper.vm.configSettingGroups).toEqual([{ id: '1', name: 'g1' }]);
    });

    it('should correctly load and merge paginated results', async () => {
        const wrapper = await createWrapper();
        const loadGroupsSpy = jest.spyOn(wrapper.vm, 'loadGroups');

        wrapper.vm.limit = 5;
        await nextTick();

        // Mock repository to return paginated data
        wrapper.vm.groupRepository.search = jest
            .fn()
            .mockResolvedValueOnce({
                total: 12,
                length: 5,
                map: (fn) =>
                    [
                        { id: '1', name: 'group-1' },
                        { id: '2', name: 'group-2' },
                        { id: '3', name: 'group-3' },
                        { id: '4', name: 'group-4' },
                        { id: '5', name: 'group-5' },
                    ].map(fn),
            })
            .mockResolvedValueOnce({
                total: 7,
                length: 5,
                map: (fn) =>
                    [
                        { id: '6', name: 'group-6' },
                        { id: '7', name: 'group-7' },
                        { id: '8', name: 'group-8' },
                        { id: '9', name: 'group-9' },
                        { id: '10', name: 'group-10' },
                    ].map(fn),
            })
            .mockResolvedValueOnce({
                total: 2,
                length: 2,
                map: (fn) =>
                    [
                        { id: '11', name: 'group-11' },
                        { id: '12', name: 'group-12' },
                    ].map(fn),
            });

        await flushPromises();

        expect(wrapper.vm.groupRepository.search).toHaveBeenCalledTimes(3);
        expect(loadGroupsSpy).toHaveBeenCalledTimes(1);
    });

    it('should handle cases where total items are less than limit', async () => {
        const wrapper = await createWrapper();
        const loadGroupsSpy = jest.spyOn(wrapper.vm, 'loadGroups');

        wrapper.vm.limit = 5;
        await nextTick();

        wrapper.vm.groupRepository.search = jest.fn().mockResolvedValueOnce({
            total: 3,
            length: 3,
            map: (fn) =>
                [
                    { id: '1', name: 'group-1' },
                    { id: '2', name: 'group-2' },
                    { id: '3', name: 'group-3' },
                ].map(fn),
        });

        wrapper.vm.loadGroups = jest.fn();

        await flushPromises();

        // Expect only one API call since everything fits in the first page
        expect(wrapper.vm.groupRepository.search).toHaveBeenCalledTimes(1);
        expect(loadGroupsSpy).toHaveBeenCalledTimes(1);
    });
});
