/**
 * @package content
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';

import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsDetail from 'src/module/sw-cms/page/sw-cms-detail';
import swCmsCreate from 'src/module/sw-cms/page/sw-cms-create';
import CmsPageTypeService from '../../../sw-cms/service/cms-page-type.service';

Shopware.Component.register('sw-cms-detail', swCmsDetail);
Shopware.Component.extend('sw-cms-create', 'sw-cms-detail', swCmsCreate);

const pageId = 'TEST-PAGE-ID';
const categoryId = 'TEST-CATEGORY-ID';
const customEntityId = 'TEST-CUSTOM-ENTITY-ID';

const pageRepository = {
    create() {
        return {
            id: pageId,
            name: 'CMS-PAGE-NAME',
            type: 'product_list',
            categories: [],
            extensions: {
                customEntityTestSwCmsPage: [],
                ceTestSwCmsPage: [],
            },
        };
    },
    save: jest.fn(() => Promise.resolve()),
};

const categoryRepository = {
    get: () => Promise.resolve({ id: categoryId }),
};

const customEntityRepository = {
    get: () => Promise.resolve({ id: customEntityId }),
};

async function createWrapper(routeParams = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    const cmsPageTypeService = new CmsPageTypeService();

    return shallowMount(await Shopware.Component.build('sw-cms-create'), {
        localVue,
        stubs: {
            'sw-cms-create-wizard': {
                template: '<div class="sw-cms-create-wizard"></div>',
                props: ['page'],
            },
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-cms-toolbar': true,
            'router-link': true,
            'sw-page': true,
            'sw-icon': true,
        },
        mocks: {
            $route: { params: routeParams },
        },
        provide: {
            repositoryFactory: {
                create: (name) => {
                    switch (name) {
                        case 'category':
                            return categoryRepository;
                        case 'cms_page':
                            return pageRepository;
                        case 'custom_entity_test':
                        case 'ce_test':
                            return customEntityRepository;
                        default:
                            throw new Error(`No repository for ${name} configured`);
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
            cmsDataResolverService: {},
            systemConfigApiService: {},
        },
    });
}

/**
 * @package content
 */
describe('module/sw-cms/page/sw-cms-create', () => {
    beforeEach(() => {
        if (Shopware.State.get('cmsPageState')) {
            Shopware.State.unregisterModule('cmsPageState');
        }

        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                isSystemDefaultLanguage: true,
            },
            mutations: {
                removeCurrentPage() {},
                removeSelectedBlock() {},
                removeSelectedSection() {},
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should assign new layout to a category', async () => {
        const wrapper = await createWrapper({ type: 'category', id: categoryId });

        await wrapper.vm.onSave();

        const mockFn = wrapper.vm.pageRepository.save;
        expect(mockFn).toHaveBeenCalledTimes(1);

        const callArg = mockFn.mock.calls[0][0];
        expect(callArg).toEqual(expect.objectContaining({
            id: 'TEST-PAGE-ID',
            name: 'CMS-PAGE-NAME',
            sections: [],
            type: 'product_list',
        }));

        expect(callArg.categories).toHaveLength(1);
        expect(callArg.categories[0]).toMatchObject({ id: categoryId });
    });

    it('should assign new layout to a custom entity prefixed with custom_entity_', async () => {
        const wrapper = await createWrapper({ type: 'custom_entity_test', id: customEntityId });

        await wrapper.vm.onSave();

        const mockFn = wrapper.vm.pageRepository.save;
        expect(mockFn).toHaveBeenCalledTimes(1);

        const callArg = mockFn.mock.calls[0][0];
        expect(callArg).toEqual(expect.objectContaining({
            id: 'TEST-PAGE-ID',
            name: 'CMS-PAGE-NAME',
            sections: [],
            type: 'product_list',
        }));

        expect(callArg.extensions.customEntityTestSwCmsPage).toHaveLength(1);
        expect(callArg.extensions.customEntityTestSwCmsPage[0]).toMatchObject({ id: customEntityId });
    });

    it('should assign new layout to a custom entity prefixed with ce_', async () => {
        const wrapper = await createWrapper({ type: 'ce_test', id: customEntityId });

        await wrapper.vm.onSave();

        const mockFn = wrapper.vm.pageRepository.save;
        expect(mockFn).toHaveBeenCalledTimes(1);

        const callArg = mockFn.mock.calls[0][0];
        expect(callArg).toEqual(expect.objectContaining({
            id: 'TEST-PAGE-ID',
            name: 'CMS-PAGE-NAME',
            sections: [],
            type: 'product_list',
        }));

        expect(callArg.extensions.ceTestSwCmsPage).toHaveLength(1);
        expect(callArg.extensions.ceTestSwCmsPage[0]).toMatchObject({ id: customEntityId });
    });

    it('should show a error notification if assignment fails but still save', async () => {
        const wrapper = await createWrapper({ type: 'ce_should_fail', id: customEntityId });
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        const mockFn = wrapper.vm.pageRepository.save;
        expect(mockFn).toHaveBeenCalledTimes(1);

        const callArg = mockFn.mock.calls[0][0];
        expect(callArg).toEqual(expect.objectContaining({
            id: 'TEST-PAGE-ID',
            name: 'CMS-PAGE-NAME',
            sections: [],
            type: 'product_list',
        }));

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-cms.create.notification.assignToEntityError',
        });
    });
});
