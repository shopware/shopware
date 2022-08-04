import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/page/sw-cms-detail';

const categoryID = 'TEST-CATEGORY-ID';
const productID = 'TEST-PRODUCT-ID';
const mediaID = 'TEST-MEDIA-ID';

const defaultRepository = {
    search: () => Promise.resolve([{ name: 'defaultRepository' }]),
    get: () => Promise.resolve({
        sections: [{}]
    }),
    save: jest.fn(() => Promise.resolve()),
    clone: jest.fn(() => Promise.resolve())
};

const categoryRepository = {
    search: () => Promise.resolve([{ id: categoryID, products: { entity: 'product', source: 'source' } }]),
};

const productRepository = {
    search: () => Promise.resolve([{ id: productID }])

};

const mediaRepository = {
    get: () => Promise.resolve({ id: mediaID })
};


function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-cms-detail'), {
        localVue,
        stubs: {
            'sw-page': true,
            'sw-cms-toolbar': true,
            'sw-alert': true,
            'sw-language-switch': true,
            'sw-router-link': true,
            'sw-icon': true,
            'router-link': true,
            'sw-button-process': true,
            'sw-cms-stage-add-section': true,
            'sw-cms-sidebar': true,
            'sw-loader': true,
            'sw-cms-section': true,
            'sw-cms-layout-assignment-modal': true,
            'sw-cms-missing-element-modal': true
        },
        mocks: {
            $route: { params: { id: '1a' } },
            $device: {
                getSystemKey: () => 'Strg'
            }
        },
        provide: {
            repositoryFactory: {
                create: (name) => {
                    switch (name) {
                        case 'category':
                            return categoryRepository;
                        case 'product':
                            return productRepository;
                        case 'media':
                            return mediaRepository;
                        default:
                            return defaultRepository;
                    }
                }
            },
            entityFactory: {},
            entityHydrator: {},
            loginService: {},
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {
                        'product-listing': {}
                    };
                }
            },
            appCmsService: {},
            cmsDataResolverService: {}
        }
    });
}

describe('module/sw-cms/page/sw-cms-detail', () => {
    const cmsPageStateBackup = { ...Shopware.State._store.state.cmsPageState };


    beforeEach(() => {
        Shopware.State._store.state.cmsPageState = { ...cmsPageStateBackup };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all fields when acl rights are missing', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false
        });

        const formIcon = wrapper.find('sw-icon-stub[name="regular-bars-square"]');
        expect(formIcon.classes()).toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBe('true');

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections.length).toBe(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBe('true');
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBe('true');

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBe('true');
    });

    it('should enable all fields when acl rights are missing', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false
        });

        const formIcon = wrapper.find('sw-icon-stub[name="regular-bars-square"]');
        expect(formIcon.classes()).not.toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBeUndefined();

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections.length).toBe(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBeUndefined();
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBeUndefined();

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBeUndefined();
    });

    it('should have warning message if there are more than 1 product page element in product page layout', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            page: {
                type: 'product_detail',
                name: 'Product page',
                sections: [{
                    blocks: [{
                        slots: [{ type: 'buy-box' }, { type: 'buy-box' }]
                    }]
                }]
            }
        });

        const { uniqueSlotCount } = wrapper.vm.getSlotValidations(wrapper.vm.page.sections);
        const buyBoxElements = uniqueSlotCount.buyBox;
        expect(buyBoxElements.count).toBe(2);

        expect(wrapper.vm.slotValidation()).toBe(false);
        expect(wrapper.vm.validationWarnings.length).toBe(2);
    });

    it('should not show layout assignment when saving after create wizard', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        const wrapper = createWrapper();
        const openLayoutAssignmentModalSpy = jest.spyOn(wrapper.vm, 'openLayoutAssignmentModal');

        await wrapper.vm.$nextTick();

        const from = { path: '/sw/cms/create', name: 'sw.cms.create' };
        const to = { path: '/sw/cms/detail', name: 'sw.cms.detail' };

        // Ensure `previousRoute` dataProp will be set by navigation guard
        wrapper.vm.$options.beforeRouteEnter(to, from, cb => cb(wrapper.vm));

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            page: {
                name: 'My custom layout',
                type: 'product_list',
                categories: new EntityCollection(null, null, null, new Criteria(1, 25)),
                sections: [
                    {
                        name: 'Section 1',
                        blocks: [
                            {
                                name: 'Test block',
                                type: 'product-listing',
                                slots: []
                            }
                        ]
                    }
                ]
            }
        });

        wrapper.vm.createNotificationError = jest.fn();

        // Save the current layout
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.previousRoute).toBe('sw.cms.create');
        expect(openLayoutAssignmentModalSpy).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        expect(wrapper.find('sw-cms-layout-assignment-modal-stub').exists()).toBeFalsy();
    });

    it('should not show layout assignment when saving and not coming from create wizard', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        const wrapper = createWrapper();
        const openLayoutAssignmentModalSpy = jest.spyOn(wrapper.vm, 'openLayoutAssignmentModal');
        const SaveSpy = jest.spyOn(wrapper.vm.pageRepository, 'save');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            page: {
                name: 'My custom layout',
                type: 'product_list',
                categories: new EntityCollection(null, null, null, new Criteria(1, 25)),
                sections: [
                    {
                        name: 'Section 1',
                        blocks: [
                            {
                                name: 'Test block',
                                type: 'product-listing',
                                slots: []
                            }
                        ]
                    }
                ]
            }
        });

        // Save the current layout
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();

        // Layout assignment should not be shown and save operation should be executed
        expect(wrapper.vm.previousRoute).toBe('');
        expect(openLayoutAssignmentModalSpy).toHaveBeenCalledTimes(0);
        expect(SaveSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        expect(wrapper.find('sw-cms-layout-assignment-modal-stub').exists()).toBeFalsy();
    });

    it('should not show layout assignment when saving and not coming from create wizard', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        const wrapper = createWrapper();
        const SaveSpy = jest.spyOn(wrapper.vm.pageRepository, 'save');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            page: {
                name: 'My custom layout',
                type: 'product_list',
                categories: new EntityCollection(null, null, null, new Criteria(1, 25)),
                sections: [
                    {
                        name: 'Section 1',
                        blocks: [
                            {
                                name: 'Test block',
                                type: 'product-listing',
                                slots: []
                            }
                        ]
                    }
                ]
            }
        });

        wrapper.vm.closeLayoutAssignmentModal(true);

        await wrapper.vm.$nextTick();

        expect(SaveSpy).toHaveBeenCalledTimes(1);
    });

    it('should show the missing element modal when saving a product detail page layout', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            page: {
                type: 'product_detail',
                name: 'Product page',
                categories: [],
                sections: [{
                    blocks: [{
                        slots: [
                            { type: 'buy-box' }
                        ]
                    }]
                }]
            }
        });

        wrapper.vm.onSave();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.validationWarnings).toEqual([
            'productDescriptionReviews', 'crossSelling'
        ].map(element => `sw-cms.elements.${element}.label`));
        expect(wrapper.vm.showMissingElementModal).toBe(true);
        expect(wrapper.find('sw-cms-missing-element-modal-stub').exists()).toBeTruthy();
    });

    it('should not show the missing element modal when saving a product detail page layout', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            page: {
                type: 'product_detail',
                name: 'Product page',
                categories: [],
                sections: [{
                    blocks: [{
                        slots: [
                            { type: 'buy-box', config: {} },
                            { type: 'product-description-reviews', config: {} },
                            { type: 'cross-selling', config: {} }
                        ]
                    }]
                }]
            }
        });

        wrapper.vm.onSave();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.validationWarnings).toEqual([]);
        expect(wrapper.vm.showMissingElementModal).toBe(false);
        expect(wrapper.find('sw-cms-missing-element-modal-stub').exists()).toBeFalsy();
    });

    it('should get preview entity for categories', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_list',

            }
        });

        const State = Shopware.State._store.state.cmsPageState;

        await wrapper.vm.$nextTick();

        wrapper.vm.loadFirstDemoEntity();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({
            id: categoryID,
            media: {
                id: mediaID
            }
        });
        expect(State.currentDemoProducts).toMatchObject([{ id: productID }]);

        wrapper.vm.onDemoEntityChange('TEST-ID');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({
            id: categoryID,
            media: {
                id: mediaID
            }
        });
        expect(State.currentDemoProducts).toMatchObject([{ id: productID }]);
    });

    it('should get preview entity for products', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_detail',

            }
        });

        const State = Shopware.State._store.state.cmsPageState;

        await wrapper.vm.$nextTick();

        wrapper.vm.loadFirstDemoEntity();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toEqual(null);
        expect(State.currentDemoProducts).toEqual([]);

        wrapper.vm.onDemoEntityChange('TEST-ID');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({ id: productID });
        expect(State.currentDemoProducts).toEqual([]);
    });
});
