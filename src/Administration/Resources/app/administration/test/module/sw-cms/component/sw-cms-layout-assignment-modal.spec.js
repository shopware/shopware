import { createLocalVue, shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { cloneDeep } from 'src/core/service/utils/object.utils';
import 'src/app/mixin/notification.mixin';
import 'src/module/sw-cms/component/sw-cms-layout-assignment-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

const mockCategories = [
    {
        name: 'Computers',
        id: 'uuid1',
        cmsPageId: null
    },
    {
        name: 'Home',
        id: 'uuid2',
        cmsPageId: null
    },
    {
        name: 'Garden',
        id: 'uuid3',
        cmsPageId: null
    }
];

const mockProducts = [
    {
        name: 'Product 1',
        id: 'uuid1',
        cmsPageId: null
    },
    {
        name: 'Product 2',
        id: 'uuid2',
        cmsPageId: null
    },
    {
        name: 'Product 3',
        id: 'uuid3',
        cmsPageId: null
    }
];

const mockLandingPages = [
    {
        name: 'Landing Page 1',
        url: 'landingpage1',
        id: 'uuidLand1',
        cmsPageId: null
    },
    {
        name: 'Landing Page 2',
        url: 'landingpage2',
        id: 'uuidLand2',
        cmsPageId: null
    },
    {
        name: 'Landing Page 3',
        url: 'landingpage3',
        id: 'uuidLand3',
        cmsPageId: null
    }
];

function createWrapper(layoutType = 'product_list', privileges = []) {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-cms-layout-assignment-modal'), {
        localVue,
        propsData: {
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), mockCategories),
                products: new EntityCollection(null, null, null, new Criteria(), mockProducts),
                landingPages: new EntityCollection(null, null, null, new Criteria(), mockLandingPages),
                type: layoutType,
                id: 'uuid007'
            }
        },
        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-category-tree-field': true,
            'sw-inherit-wrapper': true,
            'sw-entity-single-select': {
                props: ['value'],
                template: `
                        <input
                           value="value"
                           @change="$emit(\'change\', $event.target.value)"
                           class="sw-entity-single-select" />
                      `
            },
            'sw-multi-select': true,
            'sw-entity-multi-select': true,
            'sw-loader': true,
            'sw-icon': true,
            'sw-cms-product-assignment': true
        },
        mocks: {
            $tc: (value) => value,
            $device: {
                onResize: () => {}
            },
            cloneDeep: cloneDeep
        },
        provide: {
            systemConfigApiService: {
                getValues: jest.fn((domain, salesChannelId) => {
                    if (salesChannelId === null) {
                        return Promise.resolve({
                            'core.basicInformation.contactPage': 'uuid007',
                            'core.basicInformation.imprintPage': 'uuid2',
                            'core.basicInformation.revocationPage': 'uuid3',
                            'core.basicInformation.newsletterPage': 'uuid007'
                        });
                    }

                    if (salesChannelId === 'storefront_id') {
                        return Promise.resolve({
                            'core.basicInformation.contactPage': 'uuid007',
                            'core.basicInformation.imprintPage': 'uuid2',
                            'core.basicInformation.revocationPage': 'uuid3'
                        });
                    }

                    if (salesChannelId === 'headless_id') {
                        return Promise.resolve({
                            'core.basicInformation.contactPage': 'uuid1',
                            'core.basicInformation.imprintPage': 'uuid2',
                            'core.basicInformation.revocationPage': 'uuid3'
                        });
                    }

                    return Promise.resolve({});
                }),
                saveValues: jest.fn(() => Promise.resolve()),
                batchSave: jest.fn(() => Promise.resolve())
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {}
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        }
    });
}

describe('module/sw-cms/component/sw-cms-sidebar', () => {
    beforeAll(() => {
        Shopware.Feature.isActive = () => true;
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render category selection', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-cms-layout-assignment-modal__category-select').exists()).toBeTruthy();
    });

    it('should emit modal close event', async () => {
        const wrapper = createWrapper();

        wrapper.vm.onModalClose();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should render tabs when type is shop page', async () => {
        const wrapper = createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').exists()).toBeTruthy();
    });

    it('should disable shop pages tab with missing system config permission', async () => {
        const wrapper = createWrapper('page');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages')
            .classes('sw-tabs-item--is-disabled')).toBeTruthy();
    });

    it('should not render tabs when type is not shop page', async () => {
        const wrapper = createWrapper();

        // Tab container should exist but not the individual tabs
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-landing-pages').exists()).toBeFalsy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').exists()).toBeFalsy();
    });

    it('should store previous categories on component creation', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.previousCategories).toEqual(mockCategories);
        expect(wrapper.vm.previousCategoryIds).toEqual(expect.arrayContaining(['uuid1', 'uuid2']));
    });

    it('should add categories', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockCategories,
                    {
                        name: 'New category',
                        id: 'uuid4'
                    }
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for main modal

        expect(wrapper.vm.page.categories).toEqual(expect.arrayContaining([
            {
                name: 'New category',
                id: 'uuid4'
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should add a category which already has a different assigned layout', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockCategories,
                    {
                        name: 'New category',
                        id: 'uuid4',
                        cmsPageId: 'totallyDifferentId'
                    },
                    {
                        name: 'Also very new category',
                        id: 'uuid4',
                        cmsPageId: null
                    }
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.vm.page.categories).toEqual(expect.arrayContaining([
            {
                name: 'New category',
                id: 'uuid4',
                cmsPageId: 'totallyDifferentId'
            },
            {
                name: 'Also very new category',
                id: 'uuid4',
                cmsPageId: null
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove categories and confirm', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Computers',
                        id: 'uuid1'
                    },
                    {
                        name: 'Home',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove categories but discard changes', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Computers',
                        id: 'uuid1'
                    },
                    {
                        name: 'Home',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Discard changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Wait for warning modal to disappear
        await wrapper.vm.$nextTick();

        // Verify categories are restored to initial categories
        expect(wrapper.vm.page.categories).toEqual(expect.arrayContaining(mockCategories));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should remove categories but keep editing', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                categories: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Computers',
                        id: 'uuid1'
                    },
                    {
                        name: 'Home',
                        id: 'uuid2'
                    }
                ])
            }
        });

        // Confirm
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-categories').exists()).toBeTruthy();

        // Keep editing
        wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify categories are still the same modified categories
        expect(wrapper.vm.page.categories).toEqual(expect.arrayContaining([
            {
                name: 'Computers',
                id: 'uuid1'
            },
            {
                name: 'Home',
                id: 'uuid2'
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeFalsy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should add shop pages', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        await wrapper.vm.$nextTick(); // Wait for shop pages to load
        await wrapper.vm.$nextTick(); // Wait for shop pages to be converted

        wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage',
                    'core.basicInformation.newsletterPage',
                    'core.basicInformation.imprintPage' // New shop page
                ]
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for shop page request
        await wrapper.vm.$nextTick(); // Wait for isLoading to finish

        // Change warning should not appear when adding new shop pages
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith(
            {
                null: {
                    'core.basicInformation.contactPage': 'uuid007',
                    'core.basicInformation.newsletterPage': 'uuid007',
                    'core.basicInformation.imprintPage': 'uuid007' // New shop page should be in api request
                }
            }
        );

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove shop pages and confirm', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        await wrapper.vm.$nextTick(); // Wait for shop pages to load
        await wrapper.vm.$nextTick(); // Wait for shop pages to be converted

        wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage'
                ]
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of deleted shop page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for warning modal to disappear
        await wrapper.vm.$nextTick(); // Wait for shop page request
        await wrapper.vm.$nextTick(); // Wait for isLoading to finish

        // Change warning should be gone
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith(
            {
                null: {
                    'core.basicInformation.contactPage': 'uuid007',
                    'core.basicInformation.newsletterPage': null // Set removed item to null
                }
            }
        );

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove shop pages but discard changes', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        await wrapper.vm.$nextTick(); // Wait for shop pages to load
        await wrapper.vm.$nextTick(); // Wait for shop pages to be converted

        wrapper.setData({
            selectedShopPages: {
                null: [
                    'core.basicInformation.contactPage'
                ]
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of deleted shop page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBeTruthy();

        // Discard changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Wait for warning modal to disappear
        await wrapper.vm.$nextTick();

        // Change warning should be gone
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeFalsy();

        // Expect selected shop pages to have previous value
        expect(wrapper.vm.selectedShopPages).toEqual({
            null: [
                'core.basicInformation.contactPage',
                'core.basicInformation.newsletterPage'
            ]
        });

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledTimes(0);

        // Main modal should also be closed
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should load system config when layout type is shop page', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        // Wait for system config to load
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedShopPages.null).toEqual([
            'core.basicInformation.contactPage',
            'core.basicInformation.newsletterPage'
        ]);
    });

    it('should load system config with different sales channel', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        // Select shop page tab
        wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

        // Wait for tab content to open
        await wrapper.vm.$nextTick();

        // Set new sales channel id
        wrapper.setData({
            shopPageSalesChannelId: 'storefront_id'
        });

        // Trigger sales channel select change
        wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        // Wait for system config to be loaded
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedShopPages.storefront_id).toEqual([
            'core.basicInformation.contactPage'
        ]);
    });

    it('should load system config with different sales channel without matching shop pages', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);

        // Select shop page tab
        wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

        // Wait for tab content to open
        await wrapper.vm.$nextTick();

        // Set new sales channel id
        wrapper.setData({
            shopPageSalesChannelId: 'headless_id'
        });

        // Trigger sales channel select change
        wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        // Wait for system config to be loaded
        await wrapper.vm.$nextTick();

        // Value should be null for inheritance switch
        expect(wrapper.vm.selectedShopPages.headless_id).toEqual(null);
    });

    it('should load system config when changing sales channel', async () => {
        const wrapper = createWrapper('page', ['system.system_config']);
        const onInputSalesChannelSelectSpy = jest.spyOn(wrapper.vm, 'onInputSalesChannelSelect');

        // Select shop page tab
        wrapper.find('.sw-cms-layout-assignment-modal__tab-shop-pages').trigger('click');

        // Wait for tab content to open
        await wrapper.vm.$nextTick();

        // Trigger sales channel select change
        wrapper.find('.sw-cms-layout-assignment-modal__sales-channel-select').trigger('change');

        expect(onInputSalesChannelSelectSpy).toHaveBeenCalledTimes(1);
    });

    it('should contain all available shop pages', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.shopPages.length).toBe(9);

        expect(wrapper.vm.shopPages).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    value: 'core.basicInformation.privacyPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.maintenancePage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.shippingPaymentInfoPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.imprintPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.tosPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.404Page',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.newsletterPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.revocationPage',
                    label: expect.any(String)
                }),
                expect.objectContaining({
                    value: 'core.basicInformation.contactPage',
                    label: expect.any(String)
                })
            ])
        );
    });

    it('should close modal and discard all changes on abort', async () => {
        const wrapper = createWrapper();
        const discardCategoryChangesSpy = jest.spyOn(wrapper.vm, 'discardCategoryChanges');
        const discardShopPageChangesSpy = jest.spyOn(wrapper.vm, 'discardShopPageChanges');
        const discardLandingPageChangesSpy = jest.spyOn(wrapper.vm, 'discardLandingPageChanges');
        const onModalCloseSpy = jest.spyOn(wrapper.vm, 'onModalClose');

        wrapper.find('.sw-cms-layout-assignment-modal__action-cancel').trigger('click');

        expect(discardCategoryChangesSpy).toHaveBeenCalledTimes(1);
        expect(discardShopPageChangesSpy).toHaveBeenCalledTimes(1);
        expect(discardLandingPageChangesSpy).toHaveBeenCalledTimes(1);
        expect(onModalCloseSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should render product selection', () => {
        const wrapper = createWrapper('product_detail');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__product-select').exists()).toBeTruthy();
    });

    it('should store previous products on component creation', () => {
        const wrapper = createWrapper('product_detail');

        expect(wrapper.vm.previousProducts).toEqual(mockProducts);
        expect(wrapper.vm.previousProductIds).toEqual(expect.arrayContaining(['uuid1', 'uuid2']));
    });

    it('should add products', async () => {
        const wrapper = createWrapper('product_detail');

        await wrapper.setData({
            page: {
                products: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockProducts,
                    {
                        name: 'New product',
                        id: 'uuid4'
                    }
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for main modal

        expect(wrapper.vm.page.products).toEqual(expect.arrayContaining([
            {
                name: 'New product',
                id: 'uuid4'
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should add a product which already has a different assigned layout', async () => {
        const wrapper = createWrapper('product_detail');

        await wrapper.setData({
            page: {
                products: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockProducts,
                    {
                        name: 'New product',
                        id: 'uuid4',
                        cmsPageId: 'differentId'
                    },
                    {
                        name: 'Also new product',
                        id: 'uuid5',
                        cmsPageId: null
                    }
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products-assigned-layouts').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.vm.page.products).toEqual(expect.arrayContaining([
            {
                name: 'New product',
                id: 'uuid4',
                cmsPageId: 'differentId'
            },
            {
                name: 'Also new product',
                id: 'uuid5',
                cmsPageId: null
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });
    //
    it('should remove products and confirm', async () => {
        const wrapper = createWrapper('product_detail');

        await wrapper.setData({
            page: {
                products: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Product 1',
                        id: 'uuid1'
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove products but discard changes', async () => {
        const wrapper = createWrapper('product_detail');

        await wrapper.setData({
            page: {
                products: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Product 1',
                        id: 'uuid1'
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Discard changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Verify categories are restored to initial categories
        expect(wrapper.vm.page.products).toEqual(expect.arrayContaining(mockProducts));
        expect(wrapper.emitted('modal-close')).toBeFalsy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should remove products but keep editing', async () => {
        const wrapper = createWrapper('product_detail');

        await wrapper.setData({
            page: {
                products: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Product 1',
                        id: 'uuid1'
                    },
                    {
                        name: 'Product 1',
                        id: 'uuid2'
                    }
                ])
            }
        });

        // Confirm
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed category
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-products').exists()).toBeTruthy();

        // Keep editing
        wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify categories are still the same modified categories
        expect(wrapper.vm.page.products).toEqual(expect.arrayContaining([
            {
                name: 'Product 1',
                id: 'uuid1'
            },
            {
                name: 'Product 1',
                id: 'uuid2'
            }
        ]));
        expect(wrapper.emitted('modal-close')).toBeFalsy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should render tabs when type is landing pages', async () => {
        const wrapper = createWrapper('landingpage');

        expect(wrapper.find('.sw-cms-layout-assignment-modal__tabs').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-categories').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__tab-landing-pages').exists()).toBeTruthy();
    });

    it('should store previous landing pages on component creation', async () => {
        const wrapper = createWrapper('landingpage');

        expect(wrapper.vm.previousLandingPages).toEqual(mockLandingPages);
        expect(wrapper.vm.previousLandingPageIds).toEqual(expect.arrayContaining(['uuidLand1', 'uuidLand2', 'uuidLand3']));
    });

    it('should add landing pages', async () => {
        const wrapper = createWrapper();
        const newPage = {
            name: 'New Landing Page',
            id: 'uuidLand4'
        };

        wrapper.setData({
            page: {
                landingPages: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockLandingPages,
                    newPage
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for main modal

        expect(wrapper.vm.page.landingPages).toEqual(expect.arrayContaining([
            newPage
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should add a landing page which already has a different assigned layout', async () => {
        const wrapper = createWrapper();

        const newPage1 = {
            name: 'New Landing Page',
            id: 'uuidLand4',
            cmsPageId: 'totallyDifferentId'
        };

        const newPage2 = {
            name: 'New Landing Page',
            id: 'uuidLand4',
            cmsPageId: 'totallyDifferentId'
        };

        wrapper.setData({
            page: {
                landingPages: new EntityCollection(null, null, null, new Criteria(), [
                    ...mockLandingPages,
                    newPage1,
                    newPage2
                ])
            }
        });

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because one new category has already an assigned layout
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.vm.page.landingPages).toEqual(expect.arrayContaining([
            newPage1,
            newPage2
        ]));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove landing pages and confirm', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                landingPages: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Computers',
                        id: 'uuid1'
                    },
                    {
                        name: 'Home',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed landing page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Confirm changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');

        await wrapper.vm.$nextTick(); // Wait for validation
        await wrapper.vm.$nextTick(); // Wait for warning modal to close
        await wrapper.vm.$nextTick(); // Wait for main modal to close

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('should remove landing pages but discard changes', async () => {
        const wrapper = createWrapper();

        wrapper.setData({
            page: {
                landingPages: new EntityCollection(null, null, null, new Criteria(), [
                    {
                        name: 'Computers',
                        id: 'uuid1'
                    },
                    {
                        name: 'Home',
                        id: 'uuid2'
                    }
                ])
            }
        });

        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed landing page
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Discard changes
        wrapper.find('.sw-cms-layout-assignment-modal__action-changes-discard').trigger('click');

        // Wait for warning modal to disappear
        await wrapper.vm.$nextTick();

        // Verify landing pages are restored to initial landing pages
        expect(wrapper.vm.page.landingPages).toEqual(expect.arrayContaining(mockLandingPages));
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });

    it('should remove landing pages but keep editing', async () => {
        const wrapper = createWrapper();
        const page1 = {
            name: 'Computers',
            id: 'uuid1'
        };
        const page2 = {
            name: 'Home',
            id: 'uuid2'
        };

        wrapper.setData({
            page: {
                landingPages: new EntityCollection(null, null, null, new Criteria(), [
                    page1,
                    page2
                ])
            }
        });

        // Confirm
        wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');

        // Wait for warning modal
        await wrapper.vm.$nextTick();

        // Change warning should appear because of removed landing pages
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-changes-modal').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-landing-pages').exists()).toBeTruthy();

        // Keep editing
        wrapper.find('.sw-cms-layout-assignment-modal__action-keep-editing').trigger('click');

        // Verify landing pages are still the same modified landing pages
        expect(wrapper.vm.page.landingPages).toEqual(expect.arrayContaining([
            page1,
            page2
        ]));
        expect(wrapper.emitted('modal-close')).toBeFalsy();
        expect(wrapper.emitted('confirm')).toBeFalsy();
    });
});
