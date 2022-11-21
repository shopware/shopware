import { shallowMount, createLocalVue } from '@vue/test-utils';

import 'src/module/sw-cms/state/cms-page.state';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsDetail from 'src/module/sw-cms/page/sw-cms-detail';
import swCmsCreate from 'src/module/sw-cms/page/sw-cms-create';

Shopware.Component.register('sw-cms-detail', swCmsDetail);
Shopware.Component.extend('sw-cms-create', 'sw-cms-detail', swCmsCreate);

const pageId = 'TEST-PAGE-ID';
const categoryId = 'TEST-CATEGORY-ID';

const pageRepository = {
    create() {
        const categories = [];
        categories.add = function add(entity) {
            this.push(entity);
        };

        return {
            id: pageId,
            name: 'CMS-PAGE-NAME',
            type: 'product_list',
            categories
        };
    },
    save: jest.fn(() => Promise.resolve()),
};

const categoryRepository = {
    get: () => Promise.resolve({ id: categoryId }),
};

async function createWrapper(routeParams = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

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
            'sw-icon': true
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
                        default:
                            throw new Error(`No repository for ${name} configured`);
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
            cmsDataResolverService: {},
            systemConfigApiService: {}
        },
    });
}

describe('module/sw-cms/page/sw-cms-create', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                isSystemDefaultLanguage: true
            }
        });
    });


    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should assign new layout to', async () => {
        const wrapper = await createWrapper({ type: 'category', id: categoryId });

        await wrapper.vm.onSave();

        const mockFn = wrapper.vm.pageRepository.save;
        expect(mockFn).toHaveBeenCalledTimes(1);

        const callArg = mockFn.mock.calls[0][0];
        expect(callArg).toEqual(expect.objectContaining({
            id: 'TEST-PAGE-ID',
            name: 'CMS-PAGE-NAME',
            sections: [],
            type: 'product_list'
        }));

        expect(callArg.categories).toHaveLength(1);
        expect(callArg.categories[0]).toMatchObject({ id: 'TEST-CATEGORY-ID' });
    });
});
