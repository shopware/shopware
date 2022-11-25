/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCmsLayoutModal from 'src/module/sw-cms/component/sw-cms-layout-modal';

Shopware.Component.register('sw-cms-layout-modal', swCmsLayoutModal);

const defaultCategoryId = 'default-category-id';
const defaultProductId = 'default-product-id';

const productMocks = [
    {
        id: 'some-other-id',
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 1'
        }
    },
    {
        id: defaultProductId,
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 2'
        }
    },
    {
        id: defaultCategoryId,
        sections: [],
        categories: [],
        products: [],
        translated: {
            name: 'CMS Page 3'
        }
    }
];

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-layout-modal'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: jest.fn(() => Promise.resolve(productMocks))
                })
            },
            searchRankingService: {},
            systemConfigApiService: {
                getValues: (query) => {
                    if (query !== 'core.cms') {
                        return null;
                    }

                    return {
                        'core.cms.default_category_cms_page': defaultCategoryId,
                        'core.cms.default_product_cms_page': defaultProductId
                    };
                },
                saveValues: () => null
            }
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
            'sw-cms-list-item': {
                template: '<div class="sw-cms-list-item"></div>',
                props: ['isDefault']
            }
        }
    });
}

describe('module/sw-cms/component/sw-cms-layout-modal', () => {
    it('should search cms pages with criteria filters', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: ['page', 'landingpage', 'product_list']
        });
        await wrapper.vm.getList();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: [
                {
                    type: 'equalsAny',
                    field: 'type',
                    value: 'page|landingpage|product_list'
                }
            ]
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should search cms pages without criteria filters', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: []
        });
        await wrapper.vm.getList();
        await wrapper.vm.$nextTick();


        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: []
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should display default status', async () => {
        global.activeAclRoles = ['system_config.read'];

        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();


        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);
        expect(wrapper.vm.defaultCategoryId).toBe(defaultCategoryId);

        const listItems = wrapper.findAll('.sw-cms-list-item');

        expect(listItems.length).toBe(3);

        expect(listItems.at(0).props('isDefault')).toBe(false);
        expect(listItems.at(1).props('isDefault')).toBe(true);
        expect(listItems.at(2).props('isDefault')).toBe(true);
    });
});
