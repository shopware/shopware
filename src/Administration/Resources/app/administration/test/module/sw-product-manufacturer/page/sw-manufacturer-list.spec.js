import { mount } from '@vue/test-utils';
import 'src/module/sw-manufacturer/page/sw-manufacturer-list';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

function createWrapper(privileges = []) {
    return mount(Shopware.Component.build('sw-manufacturer-list'), {
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>'
            },
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowDelete'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-empty-state': true,
            'sw-button': true,
            'sw-loader': true
        },
        provide: {
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            stateStyleDataProviderService: {},
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
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
        },
        mocks: {
            $route: { query: '' }
        }
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled create button', async () => {
        const wrapper = createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = createWrapper();
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.editor'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowEdit).toBeTruthy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowEdit).toBeFalsy();
    });

    it('should be able to inline delete', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowDelete).toBeTruthy();
    });

    it('should not be able to inline delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowDelete).toBeFalsy();
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
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toEqual(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
