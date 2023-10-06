/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';

import CMS from 'src/module/sw-cms/constant/sw-cms.constant';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsDetail from 'src/module/sw-cms/page/sw-cms-detail';
import swCmsToolbar from 'src/module/sw-cms/component/sw-cms-toolbar';
import CmsPageTypeService from '../../../sw-cms/service/cms-page-type.service';

Shopware.Component.register('sw-cms-detail', swCmsDetail);
Shopware.Component.register('sw-cms-toolbar', swCmsToolbar);

const categoryID = 'TEST-CATEGORY-ID';
const productID = 'TEST-PRODUCT-ID';
const mediaID = 'TEST-MEDIA-ID';

const defaultRepository = {
    search: () => Promise.resolve(new EntityCollection(
        '',
        '',
        Shopware.Context.api,
        null,
        [{ name: 'defaultRepository' }],
        1,
    )),
    get: () => Promise.resolve({
        sections: [{
            blocks: [],
        }],
        type: CMS.PAGE_TYPES.LANDING,
    }),
    save: jest.fn(() => Promise.resolve()),
    clone: jest.fn(() => Promise.resolve()),
};

const categoryRepository = {
    search: () => Promise.resolve([{ id: categoryID, products: { entity: 'product', source: 'source' }, mediaId: mediaID }]),
};

const productRepository = {
    search: () => Promise.resolve([{ id: productID }]),

};

const mediaRepository = {
    get: () => Promise.resolve({ id: mediaID }),
};


async function createWrapper() {
    const cmsPageTypeService = new CmsPageTypeService();

    return shallowMount(await Shopware.Component.build('sw-cms-detail'), {
        stubs: {
            'sw-page': true,
            'sw-cms-toolbar': await Shopware.Component.build('sw-cms-toolbar'),
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
            'sw-button': true,
            'sw-app-actions': true,
            'sw-modal': {
                template: `
                    <div class="sw-modal-stub">
                        <slot></slot>

                        <div class="modal-footer">
                            <slot name="modal-footer"></slot>
                        </div>
                    </div>
                `,
            },
            'sw-confirm-modal': {
                template: '<div></div>',
                props: ['text'],
            },
        },
        mocks: {
            $route: { params: { id: '1a' } },
            $device: {
                getSystemKey: () => 'Strg',
            },
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
                },
            },
            cmsPageTypeService,
            entityFactory: {},
            entityHydrator: {},
            loginService: {},
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {
                        'product-listing': {},
                    };
                },
            },
            appCmsService: {},
            cmsDataResolverService: {
                // eslint-disable-next-line prefer-promise-reject-errors
                resolve: () => Promise.reject('foo'),
            },
            systemConfigApiService: {},
        },
    });
}

describe('module/sw-cms/page/sw-cms-detail', () => {
    const cmsPageStateBackup = { ...Shopware.State._store.state.cmsPageState };

    let wrapper;

    beforeEach(async () => {
        Shopware.State._store.state.cmsPageState = { ...cmsPageStateBackup };

        jest.spyOn(global.console, 'warn').mockImplementation(() => {});
        jest.resetModules();
        jest.clearAllMocks();

        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all fields when ACL rights are missing', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            isLoading: false,
        });

        const formIcon = wrapper.find('sw-icon-stub[name="regular-bars-square"]');
        expect(formIcon.classes()).toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBe('true');

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections).toHaveLength(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBe('true');
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBe('true');

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBe('true');
    });

    it('should enable all fields when ACL rights are missing', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            isLoading: false,
        });

        const formIcon = wrapper.find('sw-icon-stub[name="regular-bars-square"]');
        expect(formIcon.classes()).not.toContain('is--disabled');

        const saveAction = wrapper.find('.sw-cms-detail__save-action');
        expect(saveAction.attributes().disabled).toBeUndefined();

        const cmsStageAddSections = wrapper.findAll('sw-cms-stage-add-section-stub');
        expect(cmsStageAddSections).toHaveLength(2);
        cmsStageAddSections.wrappers.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBeUndefined();
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBeUndefined();

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBeUndefined();
    });

    it('should have warning message if there are more than 1 product page element in product page layout', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            page: {
                type: 'product_detail',
                name: 'Product page',
                sections: [{
                    blocks: [{
                        slots: [{ type: 'buy-box' }, { type: 'buy-box' }],
                    }],
                }],
            },
        });

        const { uniqueSlotCount } = wrapper.vm.getSlotValidations(wrapper.vm.page.sections);
        const buyBoxElements = uniqueSlotCount.buyBox;
        expect(buyBoxElements.count).toBe(2);

        expect(wrapper.vm.slotValidation()).toBe(false);
        expect(wrapper.vm.validationWarnings).toHaveLength(2);
    });

    it('should not show layout assignment when saving', async () => {
        global.activeAclRoles = [
            'cms.editor',
        ];

        wrapper = await createWrapper();
        await flushPromises();
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
                                slots: [],
                            },
                        ],
                    },
                ],
            },
        });

        // Save the current layout
        await wrapper.vm.onSave();

        // Layout assignment should not be shown and save operation should be executed
        expect(openLayoutAssignmentModalSpy).toHaveBeenCalledTimes(0);
        expect(SaveSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        expect(wrapper.find('sw-cms-layout-assignment-modal-stub').exists()).toBe(false);
    });

    it('should get preview entity for categories', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_list',

            },
        });

        const State = Shopware.State._store.state.cmsPageState;

        await wrapper.vm.$nextTick();

        wrapper.vm.loadFirstDemoEntity();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({
            id: categoryID,
            media: {
                id: mediaID,
            },
        });
        expect(State.currentDemoProducts).toMatchObject([{ id: productID }]);

        wrapper.vm.onDemoEntityChange('TEST-ID');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({
            id: categoryID,
            media: {
                id: mediaID,
            },
        });
        expect(State.currentDemoProducts).toMatchObject([{ id: productID }]);
    });

    it('should get preview entity for products', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_detail',

            },
        });

        const State = Shopware.State._store.state.cmsPageState;

        await wrapper.vm.$nextTick();

        wrapper.vm.loadFirstDemoEntity();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toBeNull();
        expect(State.currentDemoProducts).toEqual([]);

        wrapper.vm.onDemoEntityChange('TEST-ID');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(State.currentDemoEntity).toMatchObject({ id: productID });
        expect(State.currentDemoProducts).toEqual([]);
    });

    it('should allow setting the default layout', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const idStub = 'some-id';
        await wrapper.setData({
            page: { id: idStub },
        });

        wrapper.vm.createNotificationError = () => {};

        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        wrapper.find('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.find('.sw-cms-detail__confirm-set-as-default-modal').vm.$emit('confirm');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        expect(saveSpy).toHaveBeenCalledTimes(1);
    });

    it('should not set the default layout when canceling and closing', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        wrapper.find('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        const confirmModal = wrapper.find('.sw-cms-detail__confirm-set-as-default-modal');

        expect(confirmModal.props('text')).toBe('sw-cms.components.setDefaultLayoutModal.infoText');

        confirmModal.vm.$emit('close');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        wrapper.find('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.find('.sw-cms-detail__confirm-set-as-default-modal').vm.$emit('cancel');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);
        expect(saveSpy).toHaveBeenCalledTimes(0);
    });

    it('should limit association loading in the loadPageCriteria', async () => {
        wrapper = await createWrapper();
        const criteria = wrapper.vm.loadPageCriteria;

        ['categories', 'landingPages', 'products', 'products.manufacturer'].forEach((association) => {
            expect(criteria.getAssociation(association).getLimit()).toBe(25);
        });
    });

    it('should set the currentPageType in the cmsPageState', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        let State = Shopware.State._store.state.cmsPageState;
        expect(State.currentPageType).toBe(CMS.PAGE_TYPES.LANDING);

        wrapper.get('sw-cms-sidebar-stub').vm.$emit('page-type-change', CMS.PAGE_TYPES.SHOP);
        await flushPromises();

        State = Shopware.State._store.state.cmsPageState;
        expect(State.currentPageType).toBe(CMS.PAGE_TYPES.SHOP);
        expect(wrapper.vm.page.type).toBe(CMS.PAGE_TYPES.SHOP);
    });
});
