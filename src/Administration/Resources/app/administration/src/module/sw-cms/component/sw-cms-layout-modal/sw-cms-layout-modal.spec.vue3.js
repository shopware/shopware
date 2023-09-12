/**
 * @package content
 */
import { mount } from '@vue/test-utils_v3';

const defaultCategoryId = 'default-category-id';
const defaultProductId = 'default-product-id';

const productMocks = [
    {
        id: 'some-other-id',
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 1',
        },
    },
    {
        id: defaultProductId,
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 2',
        },
    },
    {
        id: defaultCategoryId,
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 3',
        },
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-layout-modal', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: jest.fn(() => Promise.resolve(productMocks)),
                    }),
                },
                searchRankingService: {},
                systemConfigApiService: {
                    getValues: (query) => {
                        if (query !== 'core.cms') {
                            return null;
                        }

                        return {
                            'core.cms.default_category_cms_page': defaultCategoryId,
                            'core.cms.default_product_cms_page': defaultProductId,
                        };
                    },
                    saveValues: () => null,
                },
                cmsPageTypeService: {
                    getType: () => {
                        return {
                            name: 'custom_entity_detail',
                            icon: 'regular-tag',
                            title: 'sw-cms.detail.label.pageType.customEntityDetail',
                            class: ['sw-cms-create-wizard__page-type-custom-entity-detail'],
                            hideInList: false,
                        };
                    },
                },
            },

            stubs: {
                'sw-icon': true,
                'sw-modal': true,
                'sw-simple-search-field': true,
                'sw-loader': true,
                'sw-container': true,
                'sw-button': true,
                'sw-sorting-select': true,
                'sw-pagination': true,
                'sw-checkbox-field': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-cms-list-item': {
                    template: '<div class="sw-cms-list-item"></div>',
                    props: ['isDefault'],
                },
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-layout-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should search cms pages with criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: ['page', 'landingpage', 'product_list'],
        });
        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: [
                {
                    type: 'equalsAny',
                    field: 'type',
                    value: 'page|landingpage|product_list',
                },
            ],
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should search cms pages without criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: [],
        });
        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: [],
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should display default status', async () => {
        global.activeAclRoles = ['system_config.read'];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);
        expect(wrapper.vm.defaultCategoryId).toBe(defaultCategoryId);

        const listItems = wrapper.findAllComponents('.sw-cms-list-item');

        expect(listItems).toHaveLength(3);

        expect(listItems[0].props('isDefault')).toBe(false);
        expect(listItems[1].props('isDefault')).toBe(true);
        expect(listItems[2].props('isDefault')).toBe(true);
    });

    it('should return the correct page type', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.listMode = 'list';
        await flushPromises();

        const typeCell = wrapper.find('.sw-data-grid__cell--type > .sw-data-grid__cell-content');
        expect(typeCell.text()).toBe('sw-cms.detail.label.pageType.customEntityDetail');
    });
});
