/*
 * @package inventory
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-manufacturer-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>',
                },
                'sw-entity-listing': true,
                'sw-empty-state': true,
                'sw-button': true,
                'sw-loader': true,
                'router-link': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-media-preview-v2': true,
            },
            provide: {
                acl: {
                    can: key => (key ? privileges.includes(key) : true),
                },
                stateStyleDataProviderService: {},
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve([]) }),
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
            },
            mocks: {
                $route: { query: '' },
            },
        },
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled create button', async () => {
        const wrapper = await createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should be able to inline edit', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes('allow-inline-edit')).toBe('true');
    });

    it('should not be able to inline edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes('allow-inline-edit')).toBeFalsy();
    });

    it('should be able to inline delete', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes('allow-delete')).toBe('true');
    });

    it('should not be able to inline delete', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes('allow-delete')).toBeFalsy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
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
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
