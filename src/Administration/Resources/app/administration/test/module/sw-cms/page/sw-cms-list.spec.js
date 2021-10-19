import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/page/sw-cms-list';
import 'src/module/sw-cms/component/sw-cms-list-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/data-grid/sw-data-grid';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/component/base/sw-empty-state';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-cms-list'), {
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
            'sw-field': {
                template: '<div></div>'
            },
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-pagination': {
                template: '<div></div>'
            },
            'sw-cms-list-item': Shopware.Component.build('sw-cms-list-item'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-popover': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
            'sw-media-modal-v2': {
                template: '<div class="sw-media-modal-v2-mock"></div>'
            },
            'sw-button': true,
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'router-link': true,
            'sw-data-grid-skeleton': true,
            'sw-loader': true,
            'sw-empty-state': true
        },
        mocks: {
            $route: { query: '' }
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve() })
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
            }
        }
    });
}

describe('module/sw-cms/page/sw-cms-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the media modal when user clicks on edit preview image', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeFalsy();

        await wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-preview')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeTruthy();

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');
        expect(mediaModal.classes()).toContain('sw-media-modal-v2-mock');
    });

    it('should show a disabled create new button', async () => {
        const wrapper = createWrapper();

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
        const wrapper = createWrapper([
            'cms.creator'
        ]);

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
        const wrapper = createWrapper();

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
        const wrapper = createWrapper([
            'cms.editor'
        ]);

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
        const wrapper = createWrapper([
            'cms.creator'
        ]);

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
        const wrapper = createWrapper([
            'cms.deleter'
        ]);

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
        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should show disabled context fields in normal view', async () => {
        const wrapper = createWrapper();

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled preview context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(false);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled duplicate context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.creator'
        ]);

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(false);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled delete context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.deleter'
        ]);

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should disable the delete menu item when the layout got assigned to at least one product', async () => {
        const wrapper = createWrapper(
            'cms.deleter'
        );

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [{}],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button').trigger('click');

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should enable the delete menu item when the layout do not belong to any product', async () => {
        const wrapper = createWrapper(
            'cms.deleter'
        );

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

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button').trigger('click');

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should apply the necessary criteria when aggregating layouts already linked to pages', () => {
        const wrapper = createWrapper();

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
        const wrapper = createWrapper();
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
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];
        const wrapper = createWrapper();
        await wrapper.setData({
            term: 'foo'
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria();
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
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria();
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

    it('should not build query score when search ranking field is null ', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];
        const wrapper = createWrapper();
        await wrapper.setData({
            term: 'foo'
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria();
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
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];
        const wrapper = createWrapper();
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
        expect(emptyState.attributes().subline).toBe('sw-empty-state.messageNoResultSubline');
        expect(wrapper.vm.entitySearchable).toEqual(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
