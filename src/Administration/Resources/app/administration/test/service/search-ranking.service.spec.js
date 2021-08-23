import SearchRankingService, { searchRankingPoint } from 'src/app/service/search-ranking.service';

import Criteria from 'src/core/data/criteria.data';
import searchRankingModules from './_mocks/searchRankingModules.json';

describe('app/service/search-ranking.service.js', () => {
    const entity = 'product';
    const defaultModule = {
        name: 'product-module',
        entity: entity,
        routes: {
            index: {
                path: 'index',
                component: 'sw-index'
            }
        }
    };
    const searchFieldsByEntityCases = [
        [
            'two fields with both have searchable is true',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING
                },
                options: {
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING
                    }
                }
            },
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING,
                'product.options.name': searchRankingPoint.LOW_SEARCH_RANKING
            }
        ],
        [
            'two fields with the one have searchable is true and the other is false',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING
                    }
                }
            },
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            }
        ],
        [
            'two fields with both have searchable is false',
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING
                    }
                }
            },
            {}
        ],
        [
            'empty search ranking fields',
            {},
            {}
        ]
    ];

    const buildingCriteriaScoreQueryCase = [
        [
            'term has just one word with word has more than 1 character',
            'order',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria())
                .addQuery(Criteria.equals('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.75)
        ],
        [
            'term has just one word with word has 1 character',
            'o',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria()).setTerm('o')
        ],
        [
            'term has just two words with both have more than 1 character',
            'order category',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria())
                .addQuery(Criteria.equals('product.name', 'order category'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'order category'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.75)
                .addQuery(Criteria.equals('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5)
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5 * 0.75)
                .addQuery(Criteria.equals('product.name', 'category'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5)
                .addQuery(Criteria.contains('product.name', 'category'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5 * 0.75)

        ],
        [
            'term has just two words with one of them have less than 2 characters',
            'order c',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria())
                .addQuery(Criteria.equals('product.name', 'order c'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'order c'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.75)
                .addQuery(Criteria.equals('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5)
                .addQuery(Criteria.contains('product.name', 'order'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5 * 0.75)

        ],
        [
            'term has just two words with both have less than 2 characters',
            'o c',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria())
                .addQuery(Criteria.equals('product.name', 'o c'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'o c'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.75)

        ],
        [
            'term has just two words with the same',
            'same same',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria())
                .addQuery(Criteria.equals('product.name', 'same same'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('product.name', 'same same'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.75)
                .addQuery(Criteria.equals('product.name', 'same'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5)
                .addQuery(Criteria.contains('product.name', 'same'), searchRankingPoint.HIGH_SEARCH_RANKING * 0.5 * 0.75)
        ],
        [
            'term is undefined',
            undefined,
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria()).setTerm(undefined)
        ],
        [
            'term has only spaces',
            '       ',
            {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            (new Criteria()).setTerm('       ')
        ]
    ];

    function clearModules() {
        Shopware.Module.getModuleRegistry().clear();
    }

    function createModules(modules) {
        modules.forEach((module) => {
            Shopware.Module.register(module.name, module);
        });
    }

    beforeEach(() => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];
        clearModules();
    });

    it('Should get default user search preferences', () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const actual = service.getUserSearchPreference();
        const expected = {
            product: {
                'product.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            property: {
                'property_group.name': searchRankingPoint.HIGH_SEARCH_RANKING
            },
            order: {}
        };

        expect(actual).toEqual(expected);
    });

    it.each(searchFieldsByEntityCases)('Should get search ranking fields of the entity with %s', (testName, searchFields, expected) => {
        const module = { ...defaultModule, defaultSearchConfiguration: searchFields };
        createModules([module]);
        const service = new SearchRankingService();

        const actual = service.getSearchFieldsByEntity('product');
        expect(actual).toEqual(expected);
    });

    it('Should return empty query when building global search query score with term less than 2 characters', () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'd');
        const expected = {};

        expect(actual).toEqual(expected);
    });

    it('Should building global search query score with term more than 2 characters', () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'order');
        const expected = {
            product: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'equals',
                            field: 'product.name',
                            value: 'order'
                        }
                    },
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING * 0.75,
                        query: {
                            type: 'contains',
                            field: 'product.name',
                            value: 'order'
                        }
                    }
                ],
                'total-count-mode': 1
            },
            property: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'equals',
                            field: 'property_group.name',
                            value: 'order'
                        }
                    },
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING * 0.75,
                        query: {
                            type: 'contains',
                            field: 'property_group.name',
                            value: 'order'
                        }
                    }
                ],
                'total-count-mode': 1
            }
        };

        expect(actual).toEqual(expected);
    });

    it.each(buildingCriteriaScoreQueryCase)('Should building search query for entity when %', (testName, term, queryScores, newCriteria) => {
        const service = new SearchRankingService();

        const criteria = service.buildSearchQueriesForEntity(queryScores, term, (new Criteria().setTerm(term)));
        expect(criteria.parse()).toEqual(newCriteria.parse());
    });

    it('Should cache the result when get search fields', () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const firstResult = service.getSearchFieldsByEntity('product');
        const secondResult = service.getSearchFieldsByEntity('product');
        expect(firstResult).toEqual(secondResult);
    });
});
