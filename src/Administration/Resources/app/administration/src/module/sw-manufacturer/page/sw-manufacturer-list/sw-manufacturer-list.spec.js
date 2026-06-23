/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    const manufacturerRepository = {
        search: jest.fn(() => Promise.resolve([])),
    };

    return mount(await wrapTestComponent('sw-manufacturer-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>',
                },
                'sw-meteor-entity-data-table': {
                    props: [
                        'repository',
                        'columns',
                        'criteriaResolver',
                        'initialPage',
                        'initialLimit',
                        'initialSearchTerm',
                        'initialSortBy',
                        'initialSortDirection',
                        'initialNaturalSorting',
                        'allowEdit',
                        'allowInlineEdit',
                        'allowDelete',
                        'showSelections',
                        'detailRoute',
                        'disableSearch',
                    ],
                    template:
                        '<div class="sw-meteor-entity-data-table"><slot name="preview-name" :item="{ mediaId: \'media-id\' }"></slot></div>',
                    methods: {
                        reload: jest.fn(),
                    },
                },
                'sw-loader': true,
                'router-link': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-media-preview-v2': true,
            },
            provide: {
                acl: {
                    can: (key) => (key ? privileges.includes(key) : true),
                },
                stateStyleDataProviderService: {},
                repositoryFactory: {
                    create: () => manufacturerRepository,
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
            mocks: {
                $route: {
                    name: 'sw.manufacturer.index',
                    params: {},
                    query: {},
                    meta: {
                        $module: {
                            icon: 'solid-content',
                            description: 'Manufacturer module description',
                        },
                    },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                    resolve: jest.fn(() => ({ href: '/search-preferences' })),
                },
            },
        },
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should have an enabled create button', async () => {
        const wrapper = await createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to inline edit', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');
        expect(entityListing.props('allowInlineEdit')).toBe(true);
    });

    it('should not be able to inline edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');
        expect(entityListing.props('allowInlineEdit')).toBe(false);
    });

    it('should be able to inline delete', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');
        expect(entityListing.props('allowDelete')).toBe(true);
    });

    it('should not be able to inline delete', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');
        expect(entityListing.props('allowDelete')).toBe(false);
    });

    it('should use the meteor entity table wrapper without the listing mixin', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
            'product_manufacturer.deleter',
        ]);
        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');

        expect(wrapper.find('sw-entity-listing-stub').exists()).toBe(false);
        expect(wrapper.vm.$options.mixins).toBeUndefined();
        expect(entityListing.props('repository')).toBe(wrapper.vm.manufacturerRepository);
        expect(entityListing.props('columns')).toEqual(wrapper.vm.manufacturerColumns);
        expect(entityListing.props('criteriaResolver')).toBe(wrapper.vm.resolveManufacturerCriteria);
        expect(entityListing.props('initialPage')).toBe(1);
        expect(entityListing.props('initialLimit')).toBe(25);
        expect(entityListing.props('initialSortBy')).toBe('name');
        expect(entityListing.props('initialSortDirection')).toBe('ASC');
        expect(entityListing.props('detailRoute')).toBe('sw.manufacturer.detail');
        expect(entityListing.props('disableSearch')).toBe(true);
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

        await wrapper.vm.resolveManufacturerCriteria(wrapper.vm.manufacturerCriteria);

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

        await wrapper.vm.resolveManufacturerCriteria(wrapper.vm.manufacturerCriteria);

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

        await wrapper.vm.resolveManufacturerCriteria(wrapper.vm.manufacturerCriteria);

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
        await wrapper.vm.resolveManufacturerCriteria(wrapper.vm.manufacturerCriteria);

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state')).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.sw-meteor-entity-data-table').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should update total and loading state from wrapper load success', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent('.sw-manufacturer-list__grid').vm.$emit('load-success', {
            total: 7,
        });

        expect(wrapper.vm.total).toBe(7);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should synchronize route query explicitly on page changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onPageChange({
            page: 3,
            limit: 50,
        });

        expect(wrapper.vm.page).toBe(3);
        expect(wrapper.vm.limit).toBe(50);
        expect(wrapper.vm.$router.replace).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                limit: 50,
                page: 3,
                term: undefined,
                sortBy: 'name',
                sortDirection: 'ASC',
                naturalSorting: false,
            },
        });
    });
});
