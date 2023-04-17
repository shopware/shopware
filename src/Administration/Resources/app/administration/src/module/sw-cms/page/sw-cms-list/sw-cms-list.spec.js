/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/component/base/sw-empty-state';
import EntityCollection from 'src/core/data/entity-collection.data';

import swCmsList from 'src/module/sw-cms/page/sw-cms-list';
import swCmsListItem from 'src/module/sw-cms/component/sw-cms-list-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/data-grid/sw-data-grid';

Shopware.Component.register('sw-cms-list', swCmsList);
Shopware.Component.register('sw-cms-list-item', swCmsListItem);

const defaultCategoryId = 'default-category-id';
const defaultProductId = 'default-product-id';

async function createWrapper(privileges = ['user_config:read', 'user_config:create', 'user_config:update', 'cms.editor', 'cms.creator', 'cms.deleter', 'system_config:read']) {
    return shallowMount(await Shopware.Component.build('sw-cms-list'), {
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-tabs': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-select-field': true,
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-pagination': {
                template: '<div></div>'
            },
            'sw-cms-list-item': await Shopware.Component.build('sw-cms-list-item'),
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-popover': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-media-modal-v2': {
                template: '<div class="sw-media-modal-v2-mock"></div>'
            },
            'sw-button': true,
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
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
                `
            },
            'sw-confirm-modal': {
                template: '<div></div>',
                props: ['text']
            },
            'sw-text-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />'
            },
        },
        mocks: {
            $route: { query: '' },
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
                                1
                            )),
                            save: () => Promise.resolve(),
                        };
                    }

                    return {
                        search: () => Promise.resolve(),
                        clone: jest.fn(() => Promise.resolve())
                    };
                }
            },
            searchRankingService: {
                getSearchFieldsByEntity: () => {
                    return Promise.resolve({
                        name: searchRankingPoint.HIGH_SEARCH_RANKING
                    });
                },
                buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                    return criteria;
                }
            },
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
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
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
                }
            },
        },
        data: () => {
            return {
                cmsPage: {
                    locked: false,
                },
            };
        },
        attachTo: document.body
    });
}

describe('module/sw-cms/page/sw-cms-list', () => {
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
                active: true
            }, {
                name: 'page',
                value: 'page',
            }, {
                name: 'landingpage',
                value: 'landingpage',
            },
        ]);
    });

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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showMediaModal).toBe(false);

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
                        name: 'CMS Page 1'
                    }
                }
            ]
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
                        name: 'CMS Page 1'
                    }
                }
            ]
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(false);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(false);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemRename = wrapper.find('.sw-cms-list__context-menu-item-rename');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemRename.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(false);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(false);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(false);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(false);
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
                name: 'CMS Page 1'
            },
        }];

        pages.aggregations = {
            products: {
                buckets: [{
                    key: '1a',
                    productCount: {
                        count: 1
                    }
                }]
            }
        };

        await wrapper.setData({
            isLoading: false,
            pages
        });

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(true);
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should apply the necessary criteria when aggregating layouts already linked to pages', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isLinkedCriteria).toBeDefined();
        expect(wrapper.vm.assignablePageTypes).toBeDefined();

        const criteria = wrapper.vm.isLinkedCriteria;

        expect(criteria).toHaveLength(1);

        const multiFilter = criteria.pop();

        expect(multiFilter.type).toEqual('multi');
        expect(multiFilter.operator).toEqual('OR');
        expect(multiFilter.queries).toHaveLength(wrapper.vm.assignablePageTypes.length);
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
                        name: 'CMS Page 1'
                    }
                },
                {
                    id: '2a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 2'
                    }
                }
            ],
            linkedLayouts: [
                {
                    id: '2a'
                }
            ]
        };

        await wrapper.setData(testData);

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
            term: 'foo'
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
            term: 'foo'
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
            term: 'foo'
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
        expect(wrapper.vm.entitySearchable).toEqual(false);

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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        await wrapper.find('.sw-cms-list__context-menu-item-duplicate').trigger('click');

        expect(wrapper.vm.pageRepository.clone).toHaveBeenCalledTimes(1);

        const cloneMock = wrapper.vm.pageRepository.clone.mock.calls[0];

        expect(cloneMock[0]).toBe('1a');
        expect(cloneMock[1]).toStrictEqual(Shopware.Context.api);
        expect(cloneMock[2]).toStrictEqual({
            overwrites: {
                name: 'CMS Page 1 - global.default.copy'
            }
        });
    });

    it('should indicate which layout is set as default', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const testData = {
            isLoading: false,
            pages: [
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
            ],
        };

        await wrapper.setData(testData);
        await wrapper.vm.getDefaultLayouts();

        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);
        expect(wrapper.vm.defaultCategoryId).toBe(defaultCategoryId);

        const listItems = wrapper.findAll('.sw-cms-list-item');

        expect(listItems.length).toBe(3);

        expect(listItems.at(0).props('isDefault')).toBe(false);
        expect(listItems.at(1).props('isDefault')).toBe(true);
        expect(listItems.at(2).props('isDefault')).toBe(true);
    });

    it('should allow setting a default layout', async () => {
        const someCategoryID = 'someCategoryID';
        const someProductID = 'someOtherID';

        const wrapper = await createWrapper(['user_config:read', 'user_config:create', 'user_config:update', 'system_config:read']);
        await flushPromises();

        const saveValuesSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveValuesSpy;

        const testData = {
            isLoading: false,
            pages: [
                {
                    id: someProductID,
                    sections: [],
                    categories: [],
                    products: [],
                    type: 'product_detail',
                    translated: {
                        name: 'CMS Page 1'
                    }
                },
                {
                    id: someCategoryID,
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
            ],
        };

        await wrapper.setData(testData);
        await wrapper.vm.getDefaultLayouts();

        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);
        expect(wrapper.vm.defaultCategoryId).toBe(defaultCategoryId);

        const listItems = wrapper.findAll('.sw-cms-list-item');

        expect(listItems.length).toBe(4);

        expect(listItems.at(0).props('isDefault')).toBe(false);
        expect(listItems.at(1).props('isDefault')).toBe(false);
        expect(listItems.at(2).props('isDefault')).toBe(true);
        expect(listItems.at(3).props('isDefault')).toBe(true);


        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        // Assign new default product layout
        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);
        wrapper.vm.onOpenLayoutSetAsDefault({ id: someProductID });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.find('.sw-cms-list__confirm-set-as-default-modal').vm.$emit('confirm');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        expect(saveSpy).toHaveBeenCalledTimes(1);
        expect(saveSpy).toHaveBeenCalledWith({ 'core.cms.default_category_cms_page': someProductID });

        expect(listItems.length).toBe(4);

        expect(listItems.at(0).props('isDefault')).toBe(true);
        expect(listItems.at(1).props('isDefault')).toBe(false);
        expect(listItems.at(2).props('isDefault')).toBe(true);
        expect(listItems.at(3).props('isDefault')).toBe(false);

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        // Assign new default category layout
        wrapper.vm.onOpenLayoutSetAsDefault({ id: someCategoryID, type: 'product_detail' });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.find('.sw-cms-list__confirm-set-as-default-modal').vm.$emit('confirm');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        expect(saveSpy).toHaveBeenCalledTimes(2);
        expect(saveSpy).toHaveBeenLastCalledWith({ 'core.cms.default_product_cms_page': someCategoryID });

        expect(listItems.length).toBe(4);

        expect(listItems.at(0).props('isDefault')).toBe(true);
        expect(listItems.at(1).props('isDefault')).toBe(true);
        expect(listItems.at(2).props('isDefault')).toBe(false);
        expect(listItems.at(3).props('isDefault')).toBe(false);
    });

    it('should reset after canceling setting a default layout', async () => {
        const someOtherID = 'someOtherID';

        const layoutSetAsDefaultConfig = { id: someOtherID, type: 'someOtherType' };

        const wrapper = await createWrapper();
        await flushPromises();

        const saveValuesSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveValuesSpy;

        const testData = {
            isLoading: false,
            pages: [
                {
                    id: someOtherID,
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
                }
            ],
        };

        await wrapper.setData(testData);
        await wrapper.vm.getDefaultLayouts();

        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);

        const listItems = wrapper.findAll('.sw-cms-list-item');

        expect(listItems.length).toBe(2);

        expect(listItems.at(0).props('isDefault')).toBe(false);
        expect(listItems.at(1).props('isDefault')).toBe(true);

        const saveSpy = jest.fn();
        wrapper.vm.systemConfigApiService.saveValues = saveSpy;

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);

        // test resetting with close
        wrapper.vm.onOpenLayoutSetAsDefault(layoutSetAsDefaultConfig);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.newDefaultLayout).toStrictEqual(layoutSetAsDefaultConfig);
        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        const confirmModal = wrapper.find('.sw-cms-list__confirm-set-as-default-modal');
        expect(confirmModal.props('text')).toBe('sw-cms.components.setDefaultLayoutModal.infoText');

        confirmModal.vm.$emit('close');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);
        expect(wrapper.vm.newDefaultLayout).toBe(undefined);

        // test resetting with cancel
        wrapper.vm.onOpenLayoutSetAsDefault(layoutSetAsDefaultConfig);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.newDefaultLayout).toStrictEqual(layoutSetAsDefaultConfig);
        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(true);

        wrapper.find('.sw-cms-list__confirm-set-as-default-modal').vm.$emit('cancel');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showLayoutSetAsDefaultModal).toBe(false);
        expect(wrapper.vm.nextDefaultProductListLayoutId).toBe(undefined);

        expect(saveSpy).toHaveBeenCalledTimes(0);
        expect(listItems.at(0).props('isDefault')).toBe(false);
        expect(listItems.at(1).props('isDefault')).toBe(true);
    });

    it('should rename the layout', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-cms-list__actions-mode').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.pageRepository.save = jest.fn(() => Promise.resolve());
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
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        await wrapper.find('.sw-cms-list__context-menu-item-rename').trigger('click');
        await wrapper.find('.sw-text-field').setValue('CMD Page 1 New');
        await wrapper.vm.$nextTick();

        wrapper.vm.onConfirmPageRename();

        expect(wrapper.vm.pageRepository.save).toHaveBeenCalledTimes(1);

        const renameMock = wrapper.vm.pageRepository.save.mock.calls[0];

        expect(renameMock[0].name).toBe('CMD Page 1 New');
        expect(renameMock[0].name).not.toBe('CMD Page 1');
    });
});
