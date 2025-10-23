/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper() {
    const repositoryClone = jest.fn(() => Promise.resolve({ id: 'new-promotion-id' }));

    return mount(await wrapTestComponent('sw-promotion-v2-list', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template:
                        '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-entity-listing': true,
                'sw-context-menu-item': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-sidebar-item': true,
                'sw-sidebar': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        get: () => Promise.resolve([]),
                        create: () => {},
                        clone: repositoryClone,
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
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
        },
    });
}

describe('src/module/sw-promotion-v2/page/sw-promotion-v2-list', () => {
    it('should disable create button when privilege not available', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        const smartBarButton = wrapper.find('.sw-promotion-v2-list__smart-bar-button-add');

        expect(smartBarButton.exists()).toBeTruthy();
        expect(smartBarButton.attributes('disabled')).toBeDefined();
    });

    it('should enable create button when privilege available', async () => {
        global.activeAclRoles = ['promotion.creator'];

        const wrapper = await createWrapper();
        const smartBarButton = wrapper.find('.sw-promotion-v2-list__smart-bar-button-add');

        expect(smartBarButton.exists()).toBeTruthy();
        expect(smartBarButton.attributes().disabled).toBeFalsy();
    });

    it('should disable editing of entries when privilege not set', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            total: 2,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeUndefined();
        expect(element.attributes()['allow-view']).toBeUndefined();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeUndefined();
    });

    it('should enable editing of entries when privilege is set', async () => {
        global.activeAclRoles = [
            'promotion.viewer',
            'promotion.editor',
        ];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            total: 2,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should enable deletion of entries when privilege is set', async () => {
        global.activeAclRoles = [
            'promotion.viewer',
            'promotion.editor',
            'promotion.deleter',
        ];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            total: 2,
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeTruthy();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should add query score to the criteria', async () => {
        global.activeAclRoles = [];

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
        global.activeAclRoles = [];

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
        global.activeAclRoles = [];

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
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state')).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe('sw-empty-state.messageNoResultSubline');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should duplicate promotion and navigate to the new promotion detail page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const referencePromotion = {
            id: 'reference-promotion-id',
            name: 'Reference Promotion',
        };

        await wrapper.vm.onDuplicatePromotion(referencePromotion);

        expect(wrapper.vm.promotionRepository.clone).toHaveBeenCalledWith(
            'reference-promotion-id',
            {
                overwrites: {
                    name: 'Reference Promotion global.default.copy',
                    code: null,
                    useCodes: false,
                    useIndividualCodes: false,
                    individualCodePattern: '',
                    individualCodes: null,
                    active: false,
                    orderCount: 0,
                    ordersPerCustomerCount: null,
                },
            },
            Shopware.Context.api,
        );

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail',
            params: { id: 'new-promotion-id' },
        });
    });

    it('should return correct tooltip for delete button', async () => {
        const wrapper = await createWrapper();

        const promotionWithOrders = { orderCount: 1 };
        const promotionWithoutOrders = { orderCount: 0 };

        const tooltipWithOrders = wrapper.vm.deleteDisabledTooltip(promotionWithOrders);
        expect(tooltipWithOrders).toEqual({
            showDelay: 300,
            message: 'sw-promotion-v2.list.deleteDisabledToolTip',
            disabled: false,
        });

        const tooltipWithoutOrders = wrapper.vm.deleteDisabledTooltip(promotionWithoutOrders);
        expect(tooltipWithoutOrders).toEqual({
            showDelay: 300,
            message: 'sw-promotion-v2.list.deleteDisabledToolTip',
            disabled: true,
        });
    });

    it('should disable bulk delete with undeletable promotions', async () => {
        const wrapper = await createWrapper();

        const promotionWithOrders = { orderCount: 1 };
        const promotionWithoutOrders = { orderCount: 0 };

        wrapper.vm.updateSelection({ 0: promotionWithoutOrders });
        await flushPromises();
        expect(wrapper.find('sw-entity-listing-stub').attributes()['allow-delete']).toBe('true');

        wrapper.vm.updateSelection({ 0: promotionWithoutOrders, 1: promotionWithOrders });
        await flushPromises();
        expect(wrapper.find('sw-entity-listing-stub').attributes()['allow-delete']).toBe('false');
    });
});
