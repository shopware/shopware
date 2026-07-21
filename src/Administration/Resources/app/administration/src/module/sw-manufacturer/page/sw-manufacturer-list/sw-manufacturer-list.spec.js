/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = [], options = {}) {
    const searchRankingService = {
        getSearchFieldsByEntity: jest.fn(() =>
            Promise.resolve({
                name: searchRankingPoint.HIGH_SEARCH_RANKING,
            }),
        ),
        buildSearchQueriesForEntity: jest.fn((searchFields, term, criteria) => {
            return criteria;
        }),
        isValidTerm: jest.fn((term) => {
            return term && term.trim().length >= 1;
        }),
        ...options.searchRankingService,
    };

    return mount(await wrapTestComponent('sw-manufacturer-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="content">CONTENT</slot>
                        </div>
                    `,
                },
                'sw-meteor-entity-data-table': {
                    props: [
                        'entity',
                        'columns',
                        'criteria',
                        'criteriaTransform',
                        'searchTerm',
                        'defaultSortBy',
                        'allowEdit',
                        'allowDelete',
                        'showSelections',
                        'detailRoute',
                        'disableSearch',
                    ],
                    emits: [
                        'search-term-change',
                        'total-change',
                        'loading-change',
                    ],
                    template: `
                        <div class="sw-meteor-entity-data-table">
                            <slot
                                name="empty-state"
                                :search-term="searchTerm"
                                :state="{ searchTerm }"
                                :total="0"
                                :loading="false"
                            ></slot>
                        </div>
                    `,
                },
                'mt-empty-state': {
                    props: [
                        'centered',
                        'icon',
                        'headline',
                        'description',
                        'linkText',
                        'linkHref',
                    ],
                    template: `
                        <div class="mt-empty-state" :data-centered="centered" :data-icon="icon">
                            <h3 class="mt-empty-state__headline">{{ headline }}</h3>
                            <p class="mt-empty-state__description">{{ description }}</p>
                            <a class="mt-empty-state__link" :href="linkHref">{{ linkText }}</a>
                        </div>
                    `,
                },
                'sw-search-bar': {
                    props: [
                        'initialSearchType',
                        'initialSearch',
                    ],
                    emits: ['search'],
                    template: `
                        <input
                            class="sw-search-bar-stub"
                            :value="initialSearch"
                            @input="$emit('search', $event.target.value)"
                        />
                    `,
                },
                'sw-language-switch': {
                    template: '<div class="sw-language-switch-stub"></div>',
                },
                'sw-loader': true,
                'router-link': true,
                'sw-media-preview-v2': true,
            },
            provide: {
                acl: {
                    can: (key) => (key ? privileges.includes(key) : true),
                },
                stateStyleDataProviderService: {},
                repositoryFactory: {
                    create: jest.fn(),
                },
                searchRankingService,
            },
            mocks: {
                $route: {
                    name: 'sw.manufacturer.index',
                    params: {},
                    query: options.query ?? {},
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

    it('uses the meteor entity table wrapper as an entity-owned listing', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
            'product_manufacturer.deleter',
        ]);
        const entityListing = wrapper.getComponent('.sw-manufacturer-list__grid');

        expect(wrapper.find('sw-entity-listing-stub').exists()).toBe(false);
        expect(wrapper.vm.$options.mixins).toBeUndefined();
        expect(entityListing.props('entity')).toBe('product_manufacturer');
        expect(entityListing.props('columns')).toEqual(wrapper.vm.manufacturerColumns);
        expect(entityListing.props('criteria')).toBe(wrapper.vm.manufacturerCriteria);
        expect(entityListing.props('criteriaTransform')).toBe(wrapper.vm.transformManufacturerCriteria);
        expect(entityListing.props('searchTerm')).toBe(wrapper.vm.term);
        expect(entityListing.props('defaultSortBy')).toBe('name');
        expect(entityListing.props('detailRoute')).toBe('sw.manufacturer.detail');
        expect(entityListing.props('disableSearch')).toBe(true);
    });

    it('adds query score to the criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.transformManufacturerCriteria(new Criteria(1, 25));

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledWith('product_manufacturer');
    });

    it('does not get search ranking fields when term is null', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.transformManufacturerCriteria(new Criteria(1, 25));

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();
    });

    it('returns null criteria when search ranking fields are empty', async () => {
        const wrapper = await createWrapper([], {
            searchRankingService: {
                getSearchFieldsByEntity: jest.fn(() => Promise.resolve({})),
            },
        });
        await wrapper.setData({
            term: 'foo',
        });

        const transformedCriteria = await wrapper.vm.transformManufacturerCriteria(new Criteria(1, 25));

        expect(transformedCriteria).toBeNull();
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
    });

    it('keeps search ranking disabled when the administration Elasticsearch search is enabled', async () => {
        const featureSpy = jest.spyOn(Shopware.Feature, 'isActive').mockReturnValue(true);
        const previousAdminEsEnable = Shopware.Context.app.adminEsEnable;
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        const criteria = new Criteria(1, 25);

        await wrapper.vm.transformManufacturerCriteria(criteria);

        expect(criteria.term).toBe('foo');
        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();

        Shopware.Context.app.adminEsEnable = previousAdminEsEnable;
        featureSpy.mockRestore();
    });

    it('updates only the page search term when the search bar emits a search', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-search-bar-stub').setValue('meteor');

        expect(wrapper.vm.term).toBe('meteor');
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
        expect(wrapper.vm.$router.replace).not.toHaveBeenCalled();
        expect(wrapper.getComponent('.sw-manufacturer-list__grid').props('searchTerm')).toBe('meteor');
    });

    it('hydrates the page-level search term from the table bridge', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent('.sw-manufacturer-list__grid').vm.$emit('search-term-change', 'from route');

        expect(wrapper.vm.term).toBe('from route');
    });

    it('initializes the page-level term from the route query for the search bar', async () => {
        const wrapper = await createWrapper([], {
            query: {
                term: 'route term',
            },
        });

        expect(wrapper.vm.term).toBe('route term');
        expect(wrapper.get('.sw-search-bar-stub').element.value).toBe('route term');
        expect(wrapper.getComponent('.sw-manufacturer-list__grid').props('searchTerm')).toBe('route term');
    });

    it('updates total and loading state from table bridge events', async () => {
        const wrapper = await createWrapper();
        const table = wrapper.getComponent('.sw-manufacturer-list__grid');

        await table.vm.$emit('total-change', 7);
        await table.vm.$emit('loading-change', false);

        expect(wrapper.vm.total).toBe(7);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('renders a search-aware empty state from the table slot', async () => {
        const wrapper = await createWrapper([], {
            query: {
                term: 'foo',
            },
        });

        expect(wrapper.find('.sw-meteor-entity-data-table').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.mt-empty-state__description').text()).toBe('sw-empty-state.messageNoResultSubline');
        expect(wrapper.find('.mt-empty-state__link').text()).toBe('sw-empty-state.messageNoResultLink');
        expect(wrapper.find('.mt-empty-state__link').attributes('href')).toBe('/search-preferences');
    });

    it('renders the module empty-state description when the search term is not valid', async () => {
        const wrapper = await createWrapper([], {
            query: {
                term: '',
            },
        });

        expect(wrapper.find('.mt-empty-state__description').text()).toBe('Manufacturer module description');
        expect(wrapper.find('.mt-empty-state__link').text()).toBe('');
    });
});
