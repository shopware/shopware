/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

import CMS from 'src/module/sw-cms/constant/sw-cms.constant';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import CmsPageTypeService from '../../../sw-cms/service/cms-page-type.service';

const categoryID = 'TEST-CATEGORY-ID';
const productID = 'TEST-PRODUCT-ID';
const mediaID = 'TEST-MEDIA-ID';

async function createWrapper(versionId = '0fa91ce3e96a4bc2be4bd9ce752c3425') {
    const cmsPageTypeService = new CmsPageTypeService();

    return mount(await wrapTestComponent('sw-cms-detail', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                },
                'sw-cms-toolbar': await wrapTestComponent('sw-cms-toolbar'),
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
                systemConfigApiService: {
                    getValues: () => {
                        return {
                            'core.cms.default_category_cms_page': '1a',
                            'core.cms.default_product_cms_page': '1a',
                        };
                    },
                },
                repositoryFactory: {
                    create: (name) => {
                        switch (name) {
                            case 'category':
                                return {
                                    search: () => Promise.resolve([
                                        {
                                            id: categoryID,
                                            products: {
                                                entity: 'product',
                                                source: 'source',
                                            },
                                            mediaId: mediaID,
                                            media: {
                                                id: mediaID,
                                            },
                                        },
                                    ]),
                                };
                            case 'product':
                                return {
                                    search: () => Promise.resolve([{ id: productID }]),
                                };
                            default:
                                return {
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
                                        versionId: versionId,
                                    }),
                                    save: jest.fn(() => Promise.resolve()),
                                    clone: jest.fn(() => Promise.resolve()),
                                };
                        }
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/page/sw-cms-detail', () => {
    beforeEach(async () => {
        Shopware.Store.get('cmsPageState').$reset();

        jest.spyOn(global.console, 'warn').mockImplementation(() => {});
        jest.resetModules();
        jest.clearAllMocks();

        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all fields when ACL rights are missing', async () => {
        const wrapper = await createWrapper();
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
        cmsStageAddSections.forEach(cmsStageAddSection => {
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

        const wrapper = await createWrapper();
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
        cmsStageAddSections.forEach(cmsStageAddSection => {
            expect(cmsStageAddSection.attributes().disabled).toBeUndefined();
        });

        const stageSection = wrapper.find('.sw-cms-stage-section');
        expect(stageSection.attributes().disabled).toBeUndefined();

        const cmsSidebar = wrapper.find('sw-cms-sidebar-stub');
        expect(cmsSidebar.attributes().disabled).toBeUndefined();
    });

    it('should have warning message if there are more than 1 product page element in product page layout', async () => {
        const wrapper = await createWrapper();
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

        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_list',
            },
        });

        const State = Shopware.Store._rootState.state.value.cmsPageState;

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
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        await wrapper.setData({
            page: {
                type: 'product_detail',

            },
        });

        const State = Shopware.Store._rootState.state.value.cmsPageState;

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
        const wrapper = await createWrapper();
        await flushPromises();

        const idStub = 'some-id';
        await wrapper.setData({
            page: { id: idStub },
        });

        wrapper.vm.createNotificationError = () => {};

        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        wrapper.findComponent('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.findComponent('.sw-cms-detail__confirm-set-as-default-modal').vm.$emit('confirm');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        expect(saveSpy).toHaveBeenCalledTimes(1);
    });

    it('should not assign as default layout if is not on live version', async () => {
        global.activeAclRoles = ['system_config:read'];
        const wrapper = await createWrapper('not-live-version-id');

        expect(wrapper.vm.isDefaultLayout).toBe(false);
    });

    it('should assign as default layout if is on live version', async () => {
        global.activeAclRoles = ['system_config:read'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            page: {
                id: '1a',
                versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
            },
        });

        expect(wrapper.vm.isDefaultLayout).toBe(true);
    });


    it('should not set the default layout when canceling and closing', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = () => {};

        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        expect(wrapper.vm.showLayoutAssignmentModal).toBe(false);
        wrapper.findComponent('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        const confirmModal = wrapper.findComponent('.sw-cms-detail__confirm-set-as-default-modal');

        expect(confirmModal.props('text')).toBe('sw-cms.components.setDefaultLayoutModal.infoText');

        confirmModal.vm.$emit('close');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        wrapper.findComponent('sw-cms-sidebar-stub').vm.$emit('open-layout-set-as-default');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.findComponent('.sw-cms-detail__confirm-set-as-default-modal').vm.$emit('cancel');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);
        expect(saveSpy).toHaveBeenCalledTimes(0);
    });

    it('should limit association loading in the loadPageCriteria', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.loadPageCriteria;

        ['categories', 'landingPages', 'products', 'products.manufacturer'].forEach((association) => {
            expect(criteria.getAssociation(association).getLimit()).toBe(25);
        });
    });

    it('should set the currentPageType in the cmsPageState', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let State = Shopware.Store._rootState.state.value.cmsPageState;
        expect(State.currentPageType).toBe(CMS.PAGE_TYPES.LANDING);

        wrapper.findComponent('sw-cms-sidebar-stub').vm.$emit('page-type-change', CMS.PAGE_TYPES.SHOP);
        await flushPromises();

        State = Shopware.Store._rootState.state.value.cmsPageState;
        expect(State.currentPageType).toBe(CMS.PAGE_TYPES.SHOP);
        expect(wrapper.vm.page.type).toBe(CMS.PAGE_TYPES.SHOP);
    });

    it('should emulate the browser back button if there is browser history', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const backSpy = jest.fn();
        const pushSpy = jest.fn();

        wrapper.vm.$router.back = backSpy;
        wrapper.vm.$router.push = pushSpy;

        await wrapper.get('.sw-cms-detail__back-btn').trigger('click');

        expect(backSpy).toHaveBeenCalledTimes(0);
        expect(pushSpy).toHaveBeenCalledWith({ name: 'sw.cms.index' });
    });

    it('should go to the cms listing page if the browser history is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        window.history.pushState({ name: 'Product Listing' }, null, null);
        window.history.pushState({ name: 'Product Detail' }, null, null);
        window.history.pushState({ name: 'CMS Detail' }, null, null);

        const backSpy = jest.fn();
        const pushSpy = jest.fn();

        wrapper.vm.$router.back = backSpy;
        wrapper.vm.$router.push = pushSpy;

        await wrapper.get('.sw-cms-detail__back-btn').trigger('click');

        expect(backSpy).toHaveBeenCalledTimes(1);
        expect(pushSpy).toHaveBeenCalledTimes(0);
    });
});
