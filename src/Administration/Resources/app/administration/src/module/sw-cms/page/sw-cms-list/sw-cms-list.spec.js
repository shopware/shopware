/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/component/base/sw-empty-state';
import EntityCollection from 'src/core/data/entity-collection.data';

const defaultCategoryId = 'default-category-id';
const defaultProductId = 'default-product-id';

async function createWrapper(
    privileges = ['user_config:read', 'user_config:create', 'user_config:update', 'cms.editor', 'cms.creator', 'cms.deleter', 'system_config:read'],
    mocks = {},
) {
    return mount(await wrapTestComponent('sw-cms-list', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-tabs': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'sw-select-field': true,
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-pagination': {
                    template: '<div></div>',
                },
                'sw-cms-list-item': await wrapTestComponent('sw-cms-list-item'),
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
                'sw-context-menu': {
                    template: '<div><slot></slot></div>',
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-media-modal-v2': {
                    template: '<div class="sw-media-modal-v2-mock"></div>',
                },
                'sw-button': true,
                'sw-card': {
                    template: '<div><slot name="grid"></slot></div>',
                },
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-data-grid-settings': true,
                'router-link': true,
                'sw-data-grid-skeleton': true,
                'sw-loader': true,
                'sw-skeleton': true,
                'sw-empty-state': true,
                'sw-sorting-select': true,
                'sw-alert': true,
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
                'sw-text-field': {
                    props: ['value', 'label', 'placeholder'],
                    template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                },
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-tabs-item': true,
                'sw-checkbox-field': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
            },
            mocks: {
                $route: { query: '' },
                ...mocks,
            },
            provide: {
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'media_default_folder' || entityName === 'user_config') {
                            return {
                                search: () => Promise.resolve(new EntityCollection(
                                    '',
                                    '',
                                    Shopware.Context.api,
                                    null,
                                    [{}],
                                    1,
                                )),
                                save: () => Promise.resolve(),
                            };
                        }

                        return {
                            search: () => Promise.resolve(),
                            clone: jest.fn(() => Promise.resolve()),
                        };
                    },
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => {
                        return Promise.resolve({
                            name: searchRankingPoint.HIGH_SEARCH_RANKING,
                        });
                    },
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                        return criteria;
                    },
                },
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
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                cmsPageTypeService: {
                    getTypes: () => [{
                        name: 'page',
                        title: 'page',
                    }, {
                        name: 'landingpage',
                        title: 'landingpage',
                    }],
                    getType: (type) => {
                        return {
                            name: type,
                            title: type,
                        };
                    },
                },
            },
        },
        data: () => {
            return {
                cmsPage: {
                    locked: false,
                },
            };
        },
        attachTo: document.body,
    });
}

describe('module/sw-cms/page/sw-cms-list', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('Did not persist user config, as permissions are missing');
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the right list of pageTypes for the filters', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.sortPageTypes).toStrictEqual([
            {
                value: '',
                name: 'sw-cms.sorting.labelSortByAllPages',
                active: true,
            }, {
                name: 'page',
                value: 'page',
            }, {
                name: 'landingpage',
                value: 'landingpage',
            },
        ]);
    });

    it('should show the correct context menu item for default layouts', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const testData = {
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    type: 'product_list',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
                {
                    id: '2a',
                    type: 'product_detail',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2',
                    },
                },
                {
                    id: '3a',
                    type: 'landingpage',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2',
                    },
                },
            ],
        };

        await wrapper.setData(testData);
        await flushPromises();

        const contextButtons = wrapper.findAll('.sw-cms-list-item__options');
        expect(contextButtons).toHaveLength(3);

        const contextButtonChildren = wrapper.findAll('.sw-cms-list-item__options > .sw-cms-list-item__option-set-as-default');
        expect(contextButtonChildren).toHaveLength(2);
        expect(contextButtonChildren.at(0).text())
            .toBe('sw-cms.components.cmsListItem.setAsDefaultProductList');
        expect(contextButtonChildren.at(1).text())
            .toBe('sw-cms.components.cmsListItem.setAsDefaultProductDetail');
    });

    it('should not add a context menu item for default layouts, if the user does not have the necessary privileges', async () => {
        // Assign all roles and privileges besides system_config:read
        const wrapper = await createWrapper([
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'cms.editor',
            'cms.creator',
            'cms.deleter',
        ]);
        await flushPromises();

        const testData = {
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    type: 'product_list',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
                {
                    id: '2a',
                    type: 'product_detail',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2',
                    },
                },
                {
                    id: '3a',
                    type: 'landingpage',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2',
                    },
                },
            ],
        };

        await wrapper.setData(testData);
        await flushPromises();

        const contextButtons = wrapper.findAll('.sw-cms-list-item__options');
        expect(contextButtons).toHaveLength(3);

        const contextButtonChildren = wrapper.findAll('.sw-cms-list-item__options > .sw-cms-list-item__option-set-as-default');
        expect(contextButtonChildren).toHaveLength(0);
    });

    it('should save GridUserSettings with sufficient rights.', async () => {
        const mocks = {
            saveUserSettings: jest.fn(),
        };

        const wrapper = await createWrapper([
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'cms.editor',
            'cms.creator',
            'cms.deleter',
            'system_config:read',
        ], mocks);
        const saveUserSettingsSpy = jest.spyOn(wrapper.vm, 'saveUserSettings');
        await flushPromises();

        const testData = {
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        };

        await wrapper.setData(testData);
        await flushPromises();

        wrapper.vm.saveGridUserSettings();

        expect(saveUserSettingsSpy).toHaveBeenCalled();
    });

    const gridUserSettingsDataProvider = [
        ['no rights', []],
        ['only create', ['user_config:create']],
        ['only update', ['user_config:update']],
    ];
    it.each(gridUserSettingsDataProvider)(
        'should not save GridUserSettings with insufficient rights. [Case: %s]',
        async (caseName, testedPrivileges) => {
            const mocks = {
                saveUserSettings: jest.fn(),
            };

            const defaultPrivileges = [
                'user_config:read',
                'cms.editor',
                'cms.creator',
                'cms.deleter',
                'system_config:read',
            ];

            const wrapper = await createWrapper([
                ...defaultPrivileges,
                ...testedPrivileges,
            ], mocks);
            const saveUserSettingsSpy = jest.spyOn(wrapper.vm, 'saveUserSettings');
            await flushPromises();

            const testData = {
                isLoading: false,
                pages: [
                    {
                        id: '1a',
                        sections: [],
                        categories: [],
                        products: [],
                        translated: {
                            name: 'CMS Page 1',
                        },
                    },
                ],
            };

            await wrapper.setData(testData);
            await flushPromises();

            wrapper.vm.saveGridUserSettings();

            expect(saveUserSettingsSpy).not.toHaveBeenCalled();
        },
    );

    it('should open the media modal when user clicks on edit preview image', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showMediaModal).toBe(false);
        await flushPromises();

        await wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-preview')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBe(true);

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');
        expect(mediaModal.classes()).toContain('sw-media-modal-v2-mock');
    });

    it('should show a disabled create new button', async () => {
        const wrapper = await createWrapper(['user_config:read']);
        await flushPromises();

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should show an enabled create new button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should show disabled context fields in data grid view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update']);
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');
        await flushPromises();

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');


        expect(contextMenuItemEdit.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled edit context fields in data grid view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update', 'cms.editor']);
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.classes('is--disabled')).toBe(false);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled duplicate context fields in data grid view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update', 'cms.creator']);
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(false);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled delete context fields in data grid view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update', 'cms.deleter']);
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemRename = wrapper.find('.sw-cms-list__context-menu-item-rename');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemRename.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(false);
    });

    it('should show disabled context fields in normal view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update']);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled preview context field in normal view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'cms.editor']);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.classes('is--disabled')).toBe(false);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled duplicate context field in normal view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'cms.creator']);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(false);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should show enabled delete context field in normal view', async () => {
        const wrapper = await createWrapper(['user_config:read', 'cms.deleter']);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDuplicate.classes('is--disabled')).toBe(true);
        expect(contextMenuItemDelete.classes('is--disabled')).toBe(false);
    });

    it('should disable the delete menu item when the layout got assigned to at least one product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const pages = [{
            id: '1a',
            sections: [],
            categories: [],
            products: [{ id: 'abc' }],
            translated: {
                name: 'CMS Page 1',
            },
        }];

        pages.aggregations = {
            products: {
                buckets: [{
                    key: '1a',
                    productCount: {
                        count: 1,
                    },
                }],
            },
        };

        await wrapper.setData({
            isLoading: false,
            pages,
        });
        await flushPromises();

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.classes('is--disabled')).toBe(true);
    });

    it('should enable the delete menu item when the layout do not belong to any product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.classes('is--disabled')).toBe(false);
    });

    it('should apply the necessary criteria when aggregating layouts already linked to pages', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isLinkedCriteria).toBeDefined();
        expect(wrapper.vm.assignablePageTypes).toBeDefined();

        const criteria = wrapper.vm.isLinkedCriteria;

        expect(criteria).toHaveLength(1);

        const multiFilter = criteria.pop();

        expect(multiFilter.type).toBe('multi');
        expect(multiFilter.operator).toBe('OR');
        expect(multiFilter.queries).toHaveLength(wrapper.vm.assignablePageTypes.length);
    });

    it('should generate the correct strings for associated elements', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const expectedCategories = ['Category 1', 'Category 3', 'Category 2'];
        const categoryObjects = expectedCategories.map((category, key) => {
            return {
                key,
                name: category,
                categoryCount: {
                    count: 5,
                },
            };
        });

        const expectedProducts = ['Product 1', 'Product 2', 'Product 3'];
        const productObjects = expectedProducts.map((product) => {
            return {
                name: product,
            };
        });

        const mockPage = {
            id: '1',
            sections: [],
            categories: categoryObjects,
            products: [],
            translated: {
                name: 'CMS Page 1',
            },
        };

        expect(wrapper.vm.getPages(mockPage)).toStrictEqual(expectedCategories);
        expect(wrapper.vm.getPagesString(mockPage)).toBe('Category 1, Category 3, Category 2');
        expect(wrapper.vm.getPagesTooltip(mockPage)).toStrictEqual({
            width: 300,
            message: 'Category 1, Category 3, Category 2',
            disabled: true,
        });

        mockPage.products = productObjects;

        expect(wrapper.vm.getPages(mockPage)).toStrictEqual([
            ...expectedCategories,
            ...expectedProducts,
        ]);
        expect(wrapper.vm.getPagesString(mockPage)).toBe('Category 1, Category 3, Category 2, ...');
        expect(wrapper.vm.getPagesTooltip(mockPage)).toStrictEqual({
            width: 300,
            message: 'Category 1, Category 3, Category 2, Product 1, Product 2, Product 3',
            disabled: false,
        });
    });

    it('should indicate layouts already assigned to pages', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const testData = {
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
                {
                    id: '2a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2',
                    },
                },
            ],
            linkedLayouts: [
                {
                    id: '2a',
                },
            ],
        };

        await wrapper.setData(testData);
        await flushPromises();

        expect(wrapper.vm.layoutIsLinked).toBeDefined();

        expect(wrapper.vm.layoutIsLinked('1a')).toBeFalsy();
        expect(wrapper.vm.layoutIsLinked('2a')).toBeTruthy();

        const infoBoxes = wrapper.findAll('.sw-cms-list-item__info');

        expect(infoBoxes).toHaveLength(2);

        const unlinkedLayout = infoBoxes.filter(w => w.text() === 'CMS Page 1').at(0);
        const linkedLayout = infoBoxes.filter(w => w.text() === 'CMS Page 2').at(0);

        expect(() => unlinkedLayout.get('.sw-cms-list-item__status.is--active'))
            .toThrow();
        expect(linkedLayout.get('.sw-cms-list-item__status.is--active'))
            .toBeTruthy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('sw-empty-state-stub');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should duplicate and change the name of the duplicated layout', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    name: 'CMS Page 1',
                    translated: {
                        name: 'CMS Page 1',
                    },
                },
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-cms-list__context-menu-item-duplicate')
            .trigger('click');
        await flushPromises();

        expect(wrapper.vm.pageRepository.clone).toHaveBeenCalledTimes(1);

        const cloneMock = wrapper.vm.pageRepository.clone.mock.calls[0];

        expect(cloneMock[0]).toBe('1a');
        expect(cloneMock[1]).toStrictEqual({
            overwrites: {
                name: 'CMS Page 1 - global.default.copy',
            },
        });
        expect(cloneMock[2]).toStrictEqual(Shopware.Context.api);
    });
});
