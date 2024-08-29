/**
 * @package checkout
 * @group disabledCompat
 */
import SearchRankingService, { searchRankingPoint, KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import searchRankingModules from './_mocks/searchRankingModules.json';

Shopware.Service().register('userConfigService', () => {
    return {
        search: () => Promise.resolve({ data: {} }),
    };
});


Shopware.Service().register('loginService', () => {
    return {
        addOnLoginListener: () => {},
    };
});

describe('app/service/search-ranking.service.js', () => {
    const entity = 'product';
    const defaultModule = {
        name: 'product-module',
        entity: entity,
        routes: {
            index: {
                path: 'index',
                component: 'sw-index',
            },
        },
    };
    const searchFieldsByEntityCases = [
        [
            'two fields with both have searchable is true',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
                'product.options.name': searchRankingPoint.LOW_SEARCH_RANKING,
            },
        ],
        [
            'two fields with the one have searchable is true and the other is false',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        ],
        [
            'two fields with both have searchable is false',
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {},
        ],
        [
            'empty search ranking fields',
            {},
            {},
        ],
        [
            'entity is unsearchable',
            {
                _searchable: false,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {},
        ],
    ];

    const buildingCriteriaScoreQueryCase = [
        [
            'term has just one word with word has more than 1 character',
            'order',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25))
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term has just one word with word has 1 character',
            'o',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25)).setTerm('o'),
        ],
        [
            'term has just two words with both have more than 1 character',
            'order category',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25))
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'category'), searchRankingPoint.HIGH_SEARCH_RANKING),

        ],
        [
            'term has just two words with one of them have less than 2 characters',
            'order c',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25))
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING),

        ],
        [
            'term has just two words with both have less than 2 characters',
            'o c',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25))
                .setTerm('o c'),
        ],
        [
            'term has just two words with the same',
            'same same',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25))
                .addQuery(Criteria.contains('product.name', 'same'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term is undefined',
            undefined,
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25)).setTerm(undefined),
        ],
        [
            'term has only spaces',
            '       ',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            (new Criteria(1, 25)).setTerm('       '),
        ],
    ];

    const userConfigSearchPreferenceCase = [
        [
            'Overwrite the default fields from searchable to unsearchable',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {},
        ],
        [
            'Overwrite the default fields from unsearchable to searchable',
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        ],
        [
            'Overwrite the default score',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
            {
                'product.name': searchRankingPoint.LOW_SEARCH_RANKING,
            },
        ],
        [
            'Return empty when the module has default search configuration is empty',
            {},
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {},
        ],
    ];

    function clearModules() {
        Shopware.Module.getModuleRegistry().clear();
    }

    function createModules(modules) {
        modules.forEach((module) => {
            Shopware.Module.register(module.name, module);
        });
    }

    function addDataToRegisterUserConfigService(userConfigSearchs) {
        const data = {
            [KEY_USER_SEARCH_PREFERENCE]: userConfigSearchs,
        };

        Shopware.Service('userConfigService').search = () => Promise.resolve({ data });
    }

    beforeEach(async () => {
        clearModules();
    });

    it('Should get default user search preferences', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const actual = await service.getUserSearchPreference();
        const expected = {
            product: {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            property_group: {
                'property_group.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            order: {},
            cms_page: {},
        };

        expect(actual).toEqual(expected);
    });

    it.each(searchFieldsByEntityCases)('Should get search ranking fields of the entity with %s', async (testName, searchFields, expected) => {
        const module = { ...defaultModule, defaultSearchConfiguration: { _searchable: true, ...searchFields } };
        createModules([module]);
        const service = new SearchRankingService();

        const actual = await service.getSearchFieldsByEntity('product');
        expect(actual).toEqual(expected);
    });

    it('Should return empty query when building global search query score with term less than 2 characters', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = await service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'd');
        const expected = {};

        expect(actual).toEqual(expected);
    });

    it('Should building global search query score with term more than 2 characters', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = await service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'order');
        const expected = {
            product: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'contains',
                            field: 'product.name',
                            value: 'order',
                        },
                    },
                ],
                'total-count-mode': 1,
            },
            property_group: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'contains',
                            field: 'property_group.name',
                            value: 'order',
                        },
                    },
                ],
                'total-count-mode': 1,
            },
        };

        expect(actual).toEqual(expected);
    });

    it.each(buildingCriteriaScoreQueryCase)('Should building search query for entity when %', (testName, term, queryScores, newCriteria) => {
        const service = new SearchRankingService();

        const criteria = service.buildSearchQueriesForEntity(queryScores, term, (new Criteria(1, 25).setTerm(term)));
        expect(criteria.parse()).toEqual(newCriteria.parse());
    });

    it('Should cache the result when get search fields by entity', async () => {
        const service = new SearchRankingService();

        // Create module with name._searchable = true
        let module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        const firstResult = await service.getSearchFieldsByEntity('product');

        // Create again module with name._searchable = false
        clearModules();
        module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);

        const secondResult = await service.getSearchFieldsByEntity('product');

        expect(firstResult).toEqual(secondResult);
    });

    it('Should cache the result when get global search fields', async () => {
        const service = new SearchRankingService();

        // Create module with name._searchable = true
        let module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        const firstResult = await service.getUserSearchPreference();

        // Create again module with name._searchable = false
        clearModules();
        module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);

        const secondResult = await service.getUserSearchPreference();

        expect(firstResult).toEqual(secondResult);
    });

    it.each(userConfigSearchPreferenceCase)(
        'Should %s when search ranking fields of the entity along with getting user config',
        async (testName, defaultSearchFields, userConfigSearchFields, expected) => {
            const module = { ...defaultModule, defaultSearchConfiguration: { _searchable: true, ...defaultSearchFields } };

            createModules([module]);
            addDataToRegisterUserConfigService([{
                product: {
                    _searchable: true,
                    ...userConfigSearchFields,
                },
            }]);
            const newService = new SearchRankingService();
            const actual = await newService.getSearchFieldsByEntity('product');

            expect(actual).toEqual(expected);
        },
    );

    it('Should add default search configuration of a new module to current user search preferences', async () => {
        const commonSearchConfigurations = {
            _searchable: true,
            name: {
                _searchable: true,
                _score: searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        };

        createModules(searchRankingModules);
        addDataToRegisterUserConfigService([
            {
                order: { ...commonSearchConfigurations },
            },
            {
                property_group: { ...commonSearchConfigurations },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            product: { 'product.name': searchRankingPoint.HIGH_SEARCH_RANKING },
            order: { 'order.name': searchRankingPoint.HIGH_SEARCH_RANKING },
            property_group: { 'property_group.name': searchRankingPoint.HIGH_SEARCH_RANKING },
        });
    });

    it('Should remove an entity\'s search configurations from current user search preferences when entity\'s module does not have default search configurations', async () => {
        const commonSearchConfigurations = {
            _searchable: true,
            name: {
                _searchable: true,
                _score: searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        };

        const module = { ...defaultModule, defaultSearchConfiguration: { ...commonSearchConfigurations } };
        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                order: { ...commonSearchConfigurations },
            },
            {
                property_group: { ...commonSearchConfigurations },
            },
            {
                product: { ...commonSearchConfigurations },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            product: { 'product.name': searchRankingPoint.HIGH_SEARCH_RANKING },
        });
    });

    it('Should cache the result when getting user search configuration through the API', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([{
            product: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        }]);
        const newService = new SearchRankingService();
        let actual = await newService.getSearchFieldsByEntity('product');
        const expected = { 'product.name': searchRankingPoint.LOW_SEARCH_RANKING };

        expect(actual).toEqual(expected);

        // Set the response to return different response
        addDataToRegisterUserConfigService([{
            product: {
                _searchable: false,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        }]);

        actual = await newService.getSearchFieldsByEntity('product');
        // expect to still be equal the old one
        expect(actual).toEqual(expected);
    });

    it('Should recall the API get user config after clear the cache', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([{
            product: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        }]);
        const newService = new SearchRankingService();
        let actual = await newService.getSearchFieldsByEntity('product');

        expect(actual).toEqual({ 'product.name': searchRankingPoint.LOW_SEARCH_RANKING });

        // Set the response to return different response
        addDataToRegisterUserConfigService([{
            product: {
                _searchable: false,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        }]);
        newService.clearCacheUserSearchConfiguration();

        actual = await newService.getSearchFieldsByEntity('product');
        // expect to get different result
        expect(actual).toEqual({});
    });
});
