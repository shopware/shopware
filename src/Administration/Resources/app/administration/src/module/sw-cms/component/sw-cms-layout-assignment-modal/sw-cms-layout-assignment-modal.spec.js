/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const mockCategories = [
    {
        name: 'Computers',
        id: 'uuid1',
        cmsPageId: null,
    },
    {
        name: 'Home',
        id: 'uuid2',
        cmsPageId: null,
    },
    {
        name: 'Garden',
        id: 'uuid3',
        cmsPageId: null,
    },
];

const mockExtraCategories = [
    {
        name: 'New Category',
        id: 'uuid4',
        cmsPageId: null,
        attributes: {
            id: 'uuid4',
        },
        relationships: [],
    },
    {
        name: 'Another New Category',
        id: 'uuid5',
        cmsPageId: null,
        attributes: {
            id: 'uuid5',
        },
        relationships: [],
    },
];

const mockProducts = [
    {
        name: 'Product 1',
        id: 'uuid1',
        cmsPageId: null,
    },
    {
        name: 'Product 2',
        id: 'uuid2',
        cmsPageId: null,
    },
    {
        name: 'Product 3',
        id: 'uuid3',
        cmsPageId: null,
    },
];

const mockLandingPages = [
    {
        name: 'Landing Page 1',
        url: 'landingpage1',
        id: 'uuidLand1',
        cmsPageId: null,
    },
    {
        name: 'Landing Page 2',
        url: 'landingpage2',
        id: 'uuidLand2',
        cmsPageId: null,
    },
    {
        name: 'Landing Page 3',
        url: 'landingpage3',
        id: 'uuidLand3',
        cmsPageId: null,
    },
];

const responses = global.repositoryFactoryMock.responses;
responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: mockExtraCategories,
    },
});

async function createWrapper(layoutType = 'product_list', systemConfigApiServiceOverrides = {}, products = mockProducts) {
    const origin = {
        categories: new EntityCollection(null, null, Shopware.Context.api, new Criteria(1, 25), mockCategories),
    };

    return mount(
        await wrapTestComponent('sw-cms-layout-assignment-modal', {
            sync: true,
        }),
        {
            attachTo: document.body,
            props: {
                page: {
                    categories: new EntityCollection(null, null, Shopware.Context.api, new Criteria(1, 25), mockCategories),
                    products: new EntityCollection(null, null, Shopware.Context.api, new Criteria(1, 25), products),
                    landingPages: new EntityCollection(
                        null,
                        null,
                        Shopware.Context.api,
                        new Criteria(1, 25),
                        mockLandingPages,
                    ),
                    type: layoutType,
                    id: 'uuid007',
                    getOrigin: () => origin,
                },
            },
            global: {
                stubs: {
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
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
                        template: '<div class="mt-tabs"></div>',
                    },
                    'mt-banner': {
                        name: 'mt-banner',
                        props: ['variant'],
                        template: '<div class="mt-banner"><slot /></div>',
                    },
                    'sw-category-tree-field': {
                        props: {
                            allowedTypes: {
                                type: Array,
                                required: false,
                            },
                        },
                        template: `
                        <div class="sw-category-tree-field-stub">
                          <div class="sw-category-tree-field-label" @click="$emit(\'categories-load-more\')"></div>
                        </div>
                    `,
                    },
                    'sw-entity-single-select': {
                        props: ['value'],
                        template: `
                        <input
                           class="sw-entity-single-select"
                           value="value"
                           @change="$emit(\'update:value\', this.value)"
                        />
                      `,
                    },
                    'sw-multi-select': true,
                    'sw-entity-multi-select': true,
                    'sw-loader': {
                        name: 'sw-loader',
                        template: '<div class="sw-loader"></div>',
                    },
                    'sw-cms-product-assignment': {
                        template: `
                        <div class="sw-cms-product-assignment">
                            <slot name="content"></slot>
                            <slot
                                name="empty-state">
                                <img
                                    :src="assetFilter('/administration/administration/static/img/empty-states/products-empty-state.svg')"
                                    alt=""
                                >
                                <p>{{ $t('sw-cms.components.cmsLayoutAssignmentModal.products.productAssignmentEmptyStateDescription') }}</p>
                            </slot>
                        </div>
                    `,
                    },
                    'sw-inheritance-switch': true,
                    'sw-label': true,
                    transition: false,
                    'router-link': true,
                    'sw-extension-component-section': true,
                    'sw-product-variant-info': true,
                    'sw-help-text': true,
                    'sw-inherit-wrapper': true,
                },
                provide: {
                    systemConfigApiService: {
                        getValues: jest.fn((domain, salesChannelId) => {
                            if (salesChannelId === null) {
                                return Promise.resolve({
                                    'core.basicInformation.contactPage': 'uuid007',
                                    'core.basicInformation.imprintPage': 'uuid2',
                                    'core.basicInformation.revocationPage': 'uuid3',
                                    'core.basicInformation.newsletterPage': 'uuid007',
                                });
                            }

                            if (salesChannelId === 'storefront_id') {
                                return Promise.resolve({
                                    'core.basicInformation.contactPage': 'uuid007',
                                    'core.basicInformation.imprintPage': 'uuid2',
                                    'core.basicInformation.revocationPage': 'uuid3',
                                });
                            }

                            if (salesChannelId === 'headless_id') {
                                return Promise.resolve({
                                    'core.basicInformation.contactPage': 'uuid1',
                                    'core.basicInformation.imprintPage': 'uuid2',
                                    'core.basicInformation.revocationPage': 'uuid3',
                                });
                            }

                            return Promise.resolve({});
                        }),
                        saveValues: jest.fn(() => Promise.resolve()),
                        batchSave: jest.fn(() => Promise.resolve()),
                        ...systemConfigApiServiceOverrides,
                    },
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
                languageId: 'idontcare',
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-layout-assignment-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should render category selection', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-layout-assignment-modal__category-select').exists()).toBeTruthy();
        expect(wrapper.getComponent('.sw-category-tree-field-stub').props('allowedTypes')).toEqual(['page']);
    });

    it('should load inherited names for assigned variant products', async () => {
        responses.addResponse({
            method: 'Post',
            url: '/search/product',
            status: 200,
            response: {
                data: [
                    {
                        id: 'variant-id',
                        parentId: 'parent-id',
                        attributes: {
                            id: 'variant-id',
                            parentId: 'parent-id',
                            translated: {
                                name: 'Parent product',
                            },
                        },
                        relationships: [],
                    },
                ],
            },
        });

        const wrapper = await createWrapper('product_detail', {}, [
            {
                id: 'variant-id',
                parentId: 'parent-id',
                translated: {},
            },
        ]);

        await flushPromises();

        expect(wrapper.vm.page.products[0].translated.name).toBe('Parent product');
    });

    it('should allow variant products in the product assignment criteria', async () => {
        const wrapper = await createWrapper('product_detail');

        expect(wrapper.vm.productCriteria.filters).toEqual([]);
    });

    it.each([
        [
            'system configuration',
            'isLoading',
        ],
        [
            'assigned products',
            'isLoadingProducts',
        ],
    ])('should report loading while loading %s', async (_label, loadingProperty) => {
        const wrapper = await createWrapper('product_detail', {});

        wrapper.vm[loadingProperty] = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isModalLoading).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-loader' }).exists()).toBe(true);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should render tabs when type is shop page', async () => {
        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').exists()).toBeTruthy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should render deprecated tabs', async () => {
        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        const wrapper = await createWrapper('page');

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-layout-assignment-modal');
        expect(tabs.props('defaultItem')).toBe('categories');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabCategories',
                name: 'categories',
            },
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabShopPages',
                name: 'shop_pages',
                disabled: true,
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-layout-assignment-modal__category-select').exists()).toBe(true);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should provide landing page meteor tabs', async () => {
        const wrapper = await createWrapper('landingpage');
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabCategories',
                name: 'categories',
            },
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabLandingPages',
                name: 'landing_pages',
                disabled: true,
            },
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0']).each([
        'page',
        'landingpage',
    ])(
        'should render a tab permission warning banner for %s meteor tabs without system config permission',
        async (layoutType) => {
            const wrapper = await createWrapper(layoutType);
            const banner = wrapper.get('.sw-cms-layout-assignment-modal__tab-permission-warning');

            expect(banner.text()).toBe('sw-privileges.tooltip.warning');
            expect(wrapper.getComponent({ name: 'mt-banner' }).props('variant')).toBe('attention');
        },
    );

    it.activeFeatureFlags(['v6.8.0.0']).each([
        'page',
        'landingpage',
    ])(
        'should not render a tab permission warning banner for %s meteor tabs with system config permission',
        async (layoutType) => {
            global.activeAclRoles = ['system.system_config'];

            const wrapper = await createWrapper(layoutType);

            expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-permission-warning').exists()).toBe(false);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should switch meteor tab content when the active tab changes', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'shop_pages');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('shop_pages');
        expect(wrapper.find('.sw-cms-layout-assignment-modal__category-select').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').exists()).toBe(true);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should disable shop pages tab with missing system config permission', async () => {
        const wrapper = await createWrapper('page');

        expect(
            wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').classes('sw-tabs-item--is-disabled'),
        ).toBeTruthy();
    });

    it('should not render tabs when type is not shop page', async () => {
        const wrapper = await createWrapper();

        // Tab container should exist but not the individual tabs
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-landing-pages').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').exists()).toBeFalsy();
    });

    it('should store previous categories on component creation', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.previousCategoryIds).toEqual([
            'uuid1',
            'uuid2',
            'uuid3',
        ]);
    });

    it('should add categories', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                categories: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockCategories,
                    {
                        name: 'New category',
                        id: 'uuid4',
                    },
                ]),
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        expect(wrapper.vm.page.categories).toEqual(
            expect.arrayContaining([
                {
                    name: 'New category',
                    id: 'uuid4',
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toEqual([[true]]);
    });

    it('should add a category which already has a different assigned layout', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                categories: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockCategories,
                    {
                        name: 'New category',
                        id: 'uuid4',
                        cmsPageId: 'totallyDifferentId',
                    },
                    {
                        name: 'Also very new category',
                        id: 'uuid4',
                        cmsPageId: null,
                    },
                ]),
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').exists()).toBe(true);

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page.categories).toEqual(
            expect.arrayContaining([
                {
                    name: 'New category',
                    id: 'uuid4',
                    cmsPageId: 'totallyDifferentId',
                },
                {
                    name: 'Also very new category',
                    id: 'uuid4',
                    cmsPageId: null,
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove categories and confirm', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                categories: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Computers',
                        id: 'uuid1',
                    },
                    {
                        name: 'Home',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove categories but discard changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                categories: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Computers',
                        id: 'uuid1',
                    },
                    {
                        name: 'Home',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Discard changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Verify categories are restored to initial categories
        expect(wrapper.vm.page.categories).toEqual(expect.arrayContaining(mockCategories));
        expect(wrapper.emitted('modal-close')).toStrictEqual([[false]]);
    });

    it('should remove categories but keep editing', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                categories: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Computers',
                        id: 'uuid1',
                    },
                    {
                        name: 'Home',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        // Confirm
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Keep editing
        await wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify categories are still the same modified categories
        expect(wrapper.vm.page.categories).toEqual(
            expect.arrayContaining([
                {
                    name: 'Computers',
                    id: 'uuid1',
                },
                {
                    name: 'Home',
                    id: 'uuid2',
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should add shop pages', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        await wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage',
                    'core.basicInformation.newsletterPage',
                    'core.basicInformation.imprintPage', // New shop page
                ],
            },
        });
        await flushPromises();

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should not appear when adding new shop pages
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith({
            null: {
                'core.basicInformation.contactPage': 'uuid007',
                'core.basicInformation.newsletterPage': 'uuid007',
                'core.basicInformation.imprintPage': 'uuid007', // New shop page should be in api request
            },
        });

        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove shop pages and save, when data can be iterated', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        await wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage',
                ],
                'storefront_test-id': null,
            },
            previousShopPages: {
                null: [
                    'core.basicInformation.contactPage',
                    'core.basicInformation.newsletterPage',
                ],
                'storefront_test-id': null,
            },
        });
        await flushPromises();

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await flushPromises();

        // Change warning should appear because of deleted shop page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        // Change warning should be gone
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith({
            null: {
                'core.basicInformation.contactPage': 'uuid007',
                'core.basicInformation.newsletterPage': null, // Set removed item to null
            },
            'storefront_test-id': {},
        });

        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    const checkErrorHandlingDataProvider = [
        'saveShopPages',
        'loadSystemConfig',
    ];
    it.each(checkErrorHandlingDataProvider)('should catch error, when executing %s fails', async (systemConfigFunction) => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page', {
            batchSave: jest.fn(() => Promise.reject()),
            getValues: jest.fn(() => Promise.reject()),
        });
        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await wrapper.vm[systemConfigFunction]();

        expect(notificationMock).toHaveBeenCalled();
    });

    it('should show an empty state, when product_detail page has no products', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('product_detail');
        await wrapper.setProps({
            products: new EntityCollection('/products', 'products', null, new Criteria(1, 25), mockProducts),
        });
        await flushPromises();

        expect(wrapper.find('.sw-cms-product-assignment__empty-state').exists()).toBeTruthy();
    });

    it('should remove shop pages but discard changes', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');

        await flushPromises();

        await wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage',
                ],
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of deleted shop page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBeTruthy();

        // Discard changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Change warning should be gone
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();

        // Expect selected shop pages to have previous value
        expect(wrapper.vm.selectedShopPages).toEqual({
            null: [
                'core.basicInformation.contactPage',
                'core.basicInformation.newsletterPage',
            ],
        });

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(0);

        // Main modal should also be closed
        expect(wrapper.emitted('modal-close')).toStrictEqual([[false]]);
    });

    it('should load system config when layout type is shop page', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        expect(wrapper.vm.selectedShopPages.null).toEqual([
            'core.basicInformation.contactPage',
            'core.basicInformation.newsletterPage',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should load system config with different sales channel', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');

        // Select shop page tab
        await wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

        // Set new sales channel id
        await wrapper.setData({
            shopPageSalesChannelId: 'storefront_id',
        });

        // Trigger sales channel select change
        await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        expect(wrapper.vm.selectedShopPages.storefront_id).toEqual([
            'core.basicInformation.contactPage',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should load system config with different sales channel', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');

        // Select shop page tab
        await wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'shop_pages');
        await flushPromises();

        // Set new sales channel id
        await wrapper.setData({
            shopPageSalesChannelId: 'storefront_id',
        });

        // Trigger sales channel select change
        await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        expect(wrapper.vm.selectedShopPages.storefront_id).toEqual([
            'core.basicInformation.contactPage',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')(
        'should load system config with different sales channel without matching shop pages',
        async () => {
            global.activeAclRoles = ['system.system_config'];

            const wrapper = await createWrapper('page');

            // Select shop page tab
            await wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

            // Set new sales channel id
            await wrapper.setData({
                shopPageSalesChannelId: 'headless_id',
            });

            // Trigger sales channel select change
            await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

            // Value should be null for inheritance switch
            expect(wrapper.vm.selectedShopPages.headless_id).toBeNull();
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should load system config with different sales channel without matching shop pages',
        async () => {
            global.activeAclRoles = ['system.system_config'];

            const wrapper = await createWrapper('page');

            // Select shop page tab
            await wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'shop_pages');
            await flushPromises();

            // Set new sales channel id
            await wrapper.setData({
                shopPageSalesChannelId: 'headless_id',
            });

            // Trigger sales channel select change
            await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

            // Value should be null for inheritance switch
            expect(wrapper.vm.selectedShopPages.headless_id).toBeNull();
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should load system config when changing sales channel', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const onInputSalesChannelSelectSpy = jest.spyOn(wrapper.vm, 'onInputSalesChannelSelect');

        // Select shop page tab
        await wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

        // Trigger sales channel select change
        await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        expect(onInputSalesChannelSelectSpy).toHaveBeenCalledTimes(1);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should load system config when changing sales channel', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const onInputSalesChannelSelectSpy = jest.spyOn(wrapper.vm, 'onInputSalesChannelSelect');

        // Select shop page tab
        await wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'shop_pages');
        await flushPromises();

        // Trigger sales channel select change
        await wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        expect(onInputSalesChannelSelectSpy).toHaveBeenCalledTimes(1);
    });

    it('should contain all available shop pages', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.shopPages).toHaveLength(10);

        expect(wrapper.vm.shopPages).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    value: 'core.basicInformation.privacyPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.maintenancePage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.shippingPaymentInfoPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.imprintPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.tosPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.404Page',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.newsletterPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.revocationPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.contactPage',
                    label: expect.any(String),
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.revocationRequestPage',
                    label: expect.any(String),
                }),
            ]),
        );
    });

    it('should close modal and discard all changes on abort', async () => {
        const wrapper = await createWrapper();
        const discardCategoryChangesSpy = jest.spyOn(wrapper.vm, 'discardCategoryChanges');
        const discardShopPageChangesSpy = jest.spyOn(wrapper.vm, 'discardShopPageChanges');
        const discardLandingPageChangesSpy = jest.spyOn(wrapper.vm, 'discardLandingPageChanges');
        const onModalCloseSpy = jest.spyOn(wrapper.vm, 'onModalClose');

        await wrapper.find('.sw-cms-layout-assignment-modal__action-cancel').trigger('click');

        expect(discardCategoryChangesSpy).toHaveBeenCalledTimes(1);
        expect(discardShopPageChangesSpy).toHaveBeenCalledTimes(1);
        expect(discardLandingPageChangesSpy).toHaveBeenCalledTimes(1);
        expect(onModalCloseSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('modal-close')).toStrictEqual([[false]]);
    });

    it('should render product selection', async () => {
        const wrapper = await createWrapper('product_detail');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBeTruthy();
    });

    it('should store previous products on component creation', async () => {
        const wrapper = await createWrapper('product_detail');

        expect(wrapper.vm.previousProducts).toEqual(mockProducts);
        expect(wrapper.vm.previousProductIds).toEqual(
            expect.arrayContaining([
                'uuid1',
                'uuid2',
            ]),
        );
    });

    it('should add products', async () => {
        const wrapper = await createWrapper('product_detail');

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                products: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockProducts,
                    {
                        name: 'New product',
                        id: 'uuid4',
                    },
                ]),
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        expect(wrapper.vm.page.products).toEqual(
            expect.arrayContaining([
                {
                    name: 'New product',
                    id: 'uuid4',
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should add a product which already has a different assigned layout', async () => {
        const wrapper = await createWrapper('product_detail');

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                products: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockProducts,
                    {
                        name: 'New product',
                        id: 'uuid4',
                        cmsPageId: 'differentId',
                    },
                    {
                        name: 'Also new product',
                        id: 'uuid5',
                        cmsPageId: null,
                    },
                ]),
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(
            wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products-assigned-layouts').exists(),
        ).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page.products).toEqual(
            expect.arrayContaining([
                {
                    name: 'New product',
                    id: 'uuid4',
                    cmsPageId: 'differentId',
                },
                {
                    name: 'Also new product',
                    id: 'uuid5',
                    cmsPageId: null,
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove products and save the changes', async () => {
        const wrapper = await createWrapper('product_detail');

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                products: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Product 1',
                        id: 'uuid1',
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove products but discard changes', async () => {
        const wrapper = await createWrapper('product_detail');

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                products: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Product 1',
                        id: 'uuid1',
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Discard changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Verify categories are restored to initial categories
        expect(wrapper.vm.page.products).toEqual(expect.arrayContaining(mockProducts));
        expect(wrapper.emitted('modal-close')).toStrictEqual([[false]]);
    });

    it('should remove products but keep editing', async () => {
        const wrapper = await createWrapper('product_detail');

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                products: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Product 1',
                        id: 'uuid1',
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        // Confirm
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Keep editing
        await wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify categories are still the same modified categories
        expect(wrapper.vm.page.products).toEqual(
            expect.arrayContaining([
                {
                    name: 'Product 1',
                    id: 'uuid1',
                },
                {
                    name: 'Product 1',
                    id: 'uuid2',
                },
            ]),
        );
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy layout-assignment tabs.
    it.deprecated('v6.8.0.0')('should render tabs when type is landing pages', async () => {
        const wrapper = await createWrapper('landingpage');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-landing-pages').exists()).toBeTruthy();
    });

    it('should store previous landing pages on component creation', async () => {
        const wrapper = await createWrapper('landingpage');

        expect(wrapper.vm.previousLandingPages).toEqual(mockLandingPages);
        expect(wrapper.vm.previousLandingPageIds).toEqual(
            expect.arrayContaining([
                'uuidLand1',
                'uuidLand2',
                'uuidLand3',
            ]),
        );
    });

    it('should add landing pages', async () => {
        const wrapper = await createWrapper();
        const newPage = {
            name: 'New Landing Page',
            id: 'uuidLand4',
        };

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                landingPages: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockLandingPages,
                    newPage,
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');
        expect(wrapper.vm.page.landingPages).toEqual(
            expect.arrayContaining([
                newPage,
            ]),
        );
        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should add a landing page which already has a different assigned layout', async () => {
        const wrapper = await createWrapper();

        const newPage1 = {
            name: 'New Landing Page',
            id: 'uuidLand4',
            cmsPageId: 'totallyDifferentId',
        };

        const newPage2 = {
            name: 'New Landing Page',
            id: 'uuidLand4',
            cmsPageId: 'totallyDifferentId',
        };

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                landingPages: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    ...mockLandingPages,
                    newPage1,
                    newPage2,
                ]),
            },
        });

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').exists()).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page.landingPages).toEqual(
            expect.arrayContaining([
                newPage1,
                newPage2,
            ]),
        );
        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove landing pages and save', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                landingPages: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Computers',
                        id: 'uuid1',
                    },
                    {
                        name: 'Home',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed landing page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Confirm changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('modal-close')).toStrictEqual([[true]]);
    });

    it('should remove landing pages but discard changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                landingPages: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        name: 'Computers',
                        id: 'uuid1',
                    },
                    {
                        name: 'Home',
                        id: 'uuid2',
                    },
                ]),
            },
        });

        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed landing page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Discard changes
        await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Verify landing pages are restored to initial landing pages
        expect(wrapper.vm.page.landingPages).toEqual(expect.arrayContaining(mockLandingPages));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should remove landing pages but keep editing', async () => {
        const wrapper = await createWrapper();
        const page1 = {
            name: 'Computers',
            id: 'uuid1',
        };
        const page2 = {
            name: 'Home',
            id: 'uuid2',
        };

        await wrapper.setProps({
            page: {
                ...wrapper.vm.page,
                landingPages: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    page1,
                    page2,
                ]),
            },
        });

        // Confirm
        await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Change warning should appear because of removed landing pages
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Keep editing
        await wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify landing pages are still the same modified landing pages
        expect(wrapper.vm.page.landingPages).toEqual(
            expect.arrayContaining([
                page1,
                page2,
            ]),
        );
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should increment categoryIndex and update page.categories', async () => {
        const wrapper = await createWrapper();
        const initialCategories = wrapper.vm.page.categories;

        expect(wrapper.vm.categoryIndex).toBe(1);
        expect(wrapper.vm.page.categories).toHaveLength(3);

        await wrapper.find('.sw-category-tree-field-label').trigger('click');
        await flushPromises();

        expect(wrapper.vm.categoryIndex).toBe(2);
        expect(wrapper.vm.page.categories).toHaveLength(5);
        expect(wrapper.vm.page.categories).toBe(initialCategories);
    });

    it('should preserve paginated category assignments when removing a loaded category', async () => {
        const wrapper = await createWrapper();
        const extraCategories = Array.from({ length: 50 }, (_, index) => {
            return {
                id: `extra-category-${index}`,
                cmsPageId: wrapper.vm.page.id,
            };
        });
        const removedCategory = extraCategories.at(-1);

        wrapper.vm.categoryRepository.search = jest
            .fn()
            .mockResolvedValueOnce(extraCategories.slice(0, 25))
            .mockResolvedValueOnce(extraCategories.slice(25));

        await wrapper.vm.onExtraCategories();
        await wrapper.vm.onExtraCategories();

        wrapper.vm.page.categories.remove(removedCategory.id);
        wrapper.vm.onCategoryRemove(removedCategory);

        expect(wrapper.vm.page.getOrigin().categories).toHaveLength(53);
        expect(wrapper.vm.page.categories).toHaveLength(52);
        expect(wrapper.vm.page.categories.has(removedCategory.id)).toBe(false);

        await expect(wrapper.vm.validateCategories()).rejects.toBeUndefined();
        expect(wrapper.vm.hasDeletedCategories).toBe(true);
    });

    it('should not re-add a removed category when loading another category page', async () => {
        const wrapper = await createWrapper();
        const removedCategory = {
            id: 'uuid3',
            cmsPageId: wrapper.vm.page.id,
        };

        wrapper.vm.page.categories.remove(removedCategory.id);
        wrapper.vm.onCategoryRemove(removedCategory);
        wrapper.vm.categoryRepository.search = jest.fn().mockResolvedValue([removedCategory]);

        await wrapper.vm.onExtraCategories();

        expect(wrapper.vm.page.categories.has(removedCategory.id)).toBe(false);
    });

    it('should restore the complete category baseline when discarding changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onExtraCategories();

        expect(wrapper.vm.page.getOrigin().categories).toHaveLength(5);

        wrapper.vm.discardCategoryChanges();

        expect(wrapper.vm.page.categories).toHaveLength(5);
        expect(wrapper.vm.page.getOrigin().categories).toHaveLength(5);
    });
});
