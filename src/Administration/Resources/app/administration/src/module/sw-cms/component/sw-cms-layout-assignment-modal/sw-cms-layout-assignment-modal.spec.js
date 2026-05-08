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

async function createWrapper(layoutType = 'product_list', systemConfigApiServiceOverrides = {}) {
    return mount(
        await wrapTestComponent('sw-cms-layout-assignment-modal', {
            sync: true,
        }),
        {
            attachTo: document.body,
            props: {
                page: {
                    categories: new EntityCollection(null, null, Shopware.Context.api, new Criteria(1, 25), mockCategories),
                    products: new EntityCollection(null, null, Shopware.Context.api, new Criteria(1, 25), mockProducts),
                    landingPages: new EntityCollection(
                        null,
                        null,
                        Shopware.Context.api,
                        new Criteria(1, 25),
                        mockLandingPages,
                    ),
                    type: layoutType,
                    id: 'uuid007',
                },
            },
            global: {
                stubs: {
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: ['items', 'defaultItem', 'positionIdentifier', 'routeExtensionTabs'],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" :route-extension-tabs="routeExtensionTabs" @new-item-active="$emit(\'new-item-active\', $event)" />',
                    },
                    'sw-category-tree-field': {
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
                    'sw-loader': true,
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
                    'sw-extension-component-section': {
                        name: 'sw-extension-component-section',
                        props: ['positionIdentifier'],
                        template: '<div class="sw-extension-component-section"></div>',
                    },
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
        global.activeFeatureFlags = [];
    });

    it('should render category selection', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-layout-assignment-modal__category-select').exists()).toBeTruthy();
    });

    it('should render tabs when type is shop page', async () => {
        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').exists()).toBeTruthy();
    });

    it('renders legacy tabs when V6_8_0_0 is inactive', async () => {
        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs assignment items when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('page');

        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(mtTabs.exists()).toBe(true);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-cms-layout-assignment-modal');
        expect(mtTabs.props('routeExtensionTabs')).toBe(false);
        expect(mtTabs.props('defaultItem')).toBe('categories');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabCategories',
                name: 'categories',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.tabShopPages',
                name: 'shop_pages',
                disabled: true,
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('does not activate disabled shop pages tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', 'shop_pages');

        expect(wrapper.vm.activeAssignmentTab).toBe('categories');
        expect(wrapper.find('.sw-cms-layout-assignment-modal__shop-page-select').exists()).toBe(false);
    });

    it('does not activate disabled landing pages tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('landingpage');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'landing_pages' });

        expect(wrapper.vm.activeAssignmentTab).toBe('categories');
        expect(wrapper.find('.sw-cms-layout-assignment-modal__landing-page-select').exists()).toBe(false);
    });

    it('activates enabled assignment tabs from mt-tabs string and object payloads', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', 'shop_pages');
        expect(wrapper.vm.activeAssignmentTab).toBe('shop_pages');

        await mtTabs.vm.$emit('new-item-active', { name: 'categories' });
        expect(wrapper.vm.activeAssignmentTab).toBe('categories');
    });

    it('activates enabled landing pages tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('landingpage');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'landing_pages' });

        expect(wrapper.vm.activeAssignmentTab).toBe('landing_pages');
    });

    it('renders local extension tab content when an extension assignment tab is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-layout-assignment-modal'] = [
            { label: 'Extension', componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });

        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );
    });

    it('keeps the previous assignment tab active when mt-tabs emits an unknown tab id', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', 'shop_pages');
        await mtTabs.vm.$emit('new-item-active', 'unknown-tab');

        expect(wrapper.vm.activeAssignmentTab).toBe('shop_pages');
        expect(wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').exists()).toBe(true);
    });

    it('keeps registered extension assignment tabs active when mt-tabs later emits an unknown tab id', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-layout-assignment-modal'] = [
            { label: 'Extension', componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });
        await mtTabs.vm.$emit('new-item-active', 'unknown-tab');

        expect(wrapper.vm.activeAssignmentTab).toBe('extension-tab');
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );
    });

    it('renders only extension content for product detail pages when an extension assignment tab is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-layout-assignment-modal'] = [
            { label: 'Extension', componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper('product_detail');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });

        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );
    });

    it('keeps product assignment reachable when product detail pages have extension tabs', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-layout-assignment-modal'] = [
            { label: 'Extension', componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper('product_detail');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(mtTabs.props('defaultItem')).toBe('product_assignment');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-cms.components.cmsLayoutAssignmentModal.products.productAssignmentLabel',
                name: 'product_assignment',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBe(true);

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });

        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );

        await mtTabs.vm.$emit('new-item-active', { name: 'product_assignment' });

        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).exists()).toBe(false);
    });

    it('does not render extension tab content for the default tab when no core tabs exist', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('product_list');

        expect(wrapper.vm.assignmentTabItems).toEqual([]);
        expect(wrapper.vm.activeAssignmentTab).toBe('categories');
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).exists()).toBe(false);
    });

    it('keeps an accessible permission warning for disabled mt-tabs items', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-permission-warning').text()).toBe(
            'sw-privileges.tooltip.warning',
        );
    });

    it('should disable shop pages tab with missing system config permission', async () => {
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

        expect(wrapper.vm.previousCategories).toEqual(mockCategories);
        expect(wrapper.vm.previousCategoryIds).toEqual(
            expect.arrayContaining([
                'uuid1',
                'uuid2',
            ]),
        );
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

    it('should load system config with different sales channel', async () => {
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

    it('should load system config with different sales channel without matching shop pages', async () => {
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
    });

    it('should load system config when changing sales channel', async () => {
        global.activeAclRoles = ['system.system_config'];

        const wrapper = await createWrapper('page');
        const onInputSalesChannelSelectSpy = jest.spyOn(wrapper.vm, 'onInputSalesChannelSelect');

        // Select shop page tab
        await wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

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

    it('should render tabs when type is landing pages', async () => {
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

        expect(wrapper.vm.categoryIndex).toBe(1);
        expect(wrapper.vm.page.categories).toHaveLength(3);

        await wrapper.find('.sw-category-tree-field-label').trigger('click');
        await flushPromises();

        expect(wrapper.vm.categoryIndex).toBe(2);
        expect(wrapper.vm.page.categories).toHaveLength(5);
    });
});
