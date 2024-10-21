/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

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
    return mount(
        await wrapTestComponent('sw-cms-layout-modal', {
            sync: true,
        }),
        {
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
                                class: [
                                    'sw-cms-create-wizard__page-type-custom-entity-detail',
                                ],
                                hideInList: false,
                            };
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },

                stubs: {
                    'sw-icon': true,
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),

                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-container': true,
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-sorting-select': true,
                    'sw-pagination': true,
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-inheritance-switch': true,
                    'sw-field-error': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-cms-list-item': await wrapTestComponent('sw-cms-list-item', { sync: true }),
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-help-text': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-layout-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should search cms pages with criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: [
                'page',
                'landingpage',
                'product_list',
            ],
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(
            expect.objectContaining({
                filters: [
                    {
                        type: 'equalsAny',
                        field: 'type',
                        value: 'page|landingpage|product_list',
                    },
                ],
            }),
        );

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should search cms pages without criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: [],
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(
            expect.objectContaining({
                filters: [],
            }),
        );

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should display default status', async () => {
        global.activeAclRoles = ['system_config:read'];

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

    it('should emit "modal-layout-select" and "modal-close" when selecting a layout', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-cms-layout-modal__button-select-layout').trigger('click');

        expect(wrapper.vm.selectedPageObject).toBeNull();
        expect(wrapper.vm.term).toBeNull();

        expect(wrapper.emitted('modal-layout-select')).toHaveLength(1);
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should toggle list mode correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.listMode).toBe('grid');

        wrapper.vm.toggleListMode();
        expect(wrapper.vm.listMode).toBe('list');

        wrapper.vm.toggleListMode();
        expect(wrapper.vm.listMode).toBe('grid');
    });

    it('should set the cmsPageCriteria correctly, when setting a search term', async () => {
        const wrapper = await createWrapper();
        const searchTerm = 'hello world';

        wrapper.vm.term = searchTerm;
        wrapper.vm.onSearch(searchTerm);
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria.term).toBe(searchTerm);
        expect(wrapper.vm.cmsPageCriteria.page).toBe(1);
        expect(wrapper.vm.cmsPageCriteria.limit).toBe(10);
        expect(wrapper.vm.cmsPageCriteria.filters).toEqual([]);
        expect(wrapper.vm.cmsPageCriteria.hasAssociation('previewMedia')).toBe(true);
        expect(wrapper.vm.cmsPageCriteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);

        wrapper.vm.onSearch('');
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria.term).toBeNull();
        expect(wrapper.vm.cmsPageCriteria.page).toBe(1);
        expect(wrapper.vm.cmsPageCriteria.limit).toBe(10);
        expect(wrapper.vm.cmsPageCriteria.filters).toEqual([]);
        expect(wrapper.vm.cmsPageCriteria.hasAssociation('previewMedia')).toBe(true);
        expect(wrapper.vm.cmsPageCriteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);
    });

    it.each(productMocks)('should select the given item and load all variables correctly', async (product) => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: false,
        });

        const productIndex = productMocks.indexOf(product);
        const expected = productMocks[productIndex];

        const column = wrapper.findAll('.sw-cms-layout-modal__content .sw-cms-list-item').at(productIndex);
        await column.find('.sw-cms-list-item__title').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedPageObject).toEqual(expected);
        expect(wrapper.vm.gridPreSelection).toStrictEqual({
            [expected.id]: expected,
        });
    });

    it.each(productMocks)(
        'should set and unset the selectedPageObject correctly, when selecting a column',
        async (product) => {
            const wrapper = await createWrapper();
            await wrapper.setData({
                isLoading: false,
            });

            const productIndex = productMocks.indexOf(product);
            const expected = productMocks[productIndex];

            const checkbox = wrapper
                .findAll('.sw-cms-layout-modal__content-item .sw-field__checkbox input')
                .at(productIndex);

            await checkbox.setChecked(true);
            expect(wrapper.vm.selectedPageObject).toEqual(expected);
        },
    );
});
