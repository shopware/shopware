import { createLocalVue, shallowMount } from '@vue/test-utils';
import swPromotionV2List from 'src/module/sw-promotion-v2/page/sw-promotion-v2-list';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

Shopware.Component.register('sw-promotion-v2-list', swPromotionV2List);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('asset', key => key);

    return shallowMount(await Shopware.Component.build('sw-promotion-v2-list'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
            },
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-promotion-v2-empty-state-hero': true,
            'sw-context-menu-item': true,
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                },
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    get: () => Promise.resolve([]),
                    create: () => {},
                }),
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
    });
}

describe('src/module/sw-promotion-v2/page/sw-promotion-v2-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable create button when privilege not available', async () => {
        const wrapper = await createWrapper();
        const smartBarButton = wrapper.find('.sw-promotion-v2-list__smart-bar-button-add');

        expect(smartBarButton.exists()).toBeTruthy();
        expect(smartBarButton.attributes().disabled).toBeTruthy();
    });

    it('should enable create button when privilege available', async () => {
        const wrapper = await createWrapper([
            'promotion.creator',
        ]);
        const smartBarButton = wrapper.find('.sw-promotion-v2-list__smart-bar-button-add');

        expect(smartBarButton.exists()).toBeTruthy();
        expect(smartBarButton.attributes().disabled).toBeFalsy();
    });

    it('should disable editing of entries when privilege not set', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeUndefined();
        expect(element.attributes()['allow-view']).toBeUndefined();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeUndefined();
    });

    it('should enable editing of entries when privilege is set', async () => {
        const wrapper = await createWrapper([
            'promotion.viewer',
            'promotion.editor',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should enable deletion of entries when privilege is set', async () => {
        const wrapper = await createWrapper([
            'promotion.viewer',
            'promotion.editor',
            'promotion.deleter',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeTruthy();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
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

        const emptyState = wrapper.find('sw-promotion-v2-empty-state-hero-stub');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(emptyState.attributes().description).toBe('sw-empty-state.messageNoResultSubline');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
