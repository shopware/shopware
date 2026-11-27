/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import utils from 'src/core/service/util.service';

const fixture = [{ id: utils.createId(), name: 'first entry' }];

function getCollection() {
    return new EntityCollection('/test-entity', 'testEntity', null, new Criteria(1, 25), fixture, fixture.length, null);
}

const createSelect = async (
    customOptions = {
        props: {},
        global: {},
    },
) => {
    return mount(
        await wrapTestComponent('sw-entity-many-to-many-select', {
            sync: true,
        }),
        {
            props: {
                entityCollection: getCollection(),
                ...customOptions.props,
            },
            global: {
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-label': true,
                    'sw-loader': await wrapTestComponent('sw-loader'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'mt-loader': true,
                    'sw-loader-deprecated': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: (value) => Promise.resolve({ id: value, name: value }),
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
                ...customOptions.global,
            },
        },
    );
};

describe('components/sw-entity-many-to-many-select', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with sw-entity-many-to-many-select.
    it.deprecated('v6.8.0.0')('should use the provided associations in the criteria', async () => {
        const criteria = new Criteria(1, 25);
        criteria.addAssociation('testAssociation');
        const entityCollection = getCollection();
        entityCollection.context = 'test';

        const checkAssociation = jest.fn((searchCriteria) => {
            expect(searchCriteria.associations).toHaveLength(1);
            expect(searchCriteria.associations[0].association).toBe('testAssociation');
        });

        const wrapper = await createSelect({
            props: {
                entityCollection: entityCollection,
                criteria: criteria,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: (value) => Promise.resolve({ id: value, name: value }),
                                search: (searchCriteria, context) => {
                                    // The sendSearchRequest function does not use the entity context.
                                    // This check filters the fetchDisplayItems function search request
                                    if (context !== 'test') {
                                        checkAssociation(searchCriteria);
                                    }
                                    return Promise.resolve(
                                        new EntityCollection('', '', Shopware.Context.api, new Criteria(1, 1), [], 0),
                                    );
                                },
                            };
                        },
                    },
                },
            },
        });

        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        expect(checkAssociation).toHaveBeenCalled();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with sw-entity-many-to-many-select.
    it.deprecated('v6.8.0.0')(
        'should only display the result of the latest search when an older search responds later',
        async () => {
            const pendingSearches = [];
            const wrapper = await createSelect({
                props: {},
                global: {
                    provide: {
                        repositoryFactory: {
                            create: () => {
                                return {
                                    search: (criteria, context) => {
                                        if (context !== Shopware.Context.api) {
                                            return Promise.resolve(getCollection());
                                        }

                                        return new Promise((resolve) => {
                                            pendingSearches.push(resolve);
                                        });
                                    },
                                    searchIds: () => Promise.resolve({ data: [] }),
                                };
                            },
                        },
                    },
                },
            });
            await flushPromises();
            jest.useFakeTimers();

            await wrapper.find('.sw-select__selection').trigger('click');
            pendingSearches[0](getCollection());
            await flushPromises();

            await wrapper.find('input').setValue('older');
            await wrapper.find('input').trigger('change');
            jest.advanceTimersByTime(400);

            await wrapper.find('input').setValue('newer');
            await wrapper.find('input').trigger('change');
            jest.advanceTimersByTime(400);

            const olderEntity = { id: utils.createId(), name: 'older entry' };
            const newerEntity = { id: utils.createId(), name: 'newer entry' };

            pendingSearches[2](
                new EntityCollection('/test-entity', 'testEntity', null, new Criteria(1, 25), [newerEntity], 1, null),
            );
            await flushPromises();
            pendingSearches[1](
                new EntityCollection('/test-entity', 'testEntity', null, new Criteria(1, 25), [olderEntity], 1, null),
            );
            await flushPromises();

            const results = wrapper.findAll('.sw-select-result-list__item-list li');
            expect(results).toHaveLength(1);
            expect(results.at(0).find('.sw-select-result__result-item-text').text()).toBe('newer entry');
            expect(wrapper.find('sw-loader-deprecated-stub').exists()).toBe(false);
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with sw-entity-many-to-many-select.
    it.deprecated('v6.8.0.0')(
        'should only display the result of the latest search when an older assignment lookup responds later',
        async () => {
            const olderEntity = { id: utils.createId(), name: 'older entry' };
            const newerEntity = { id: utils.createId(), name: 'newer entry' };
            const searchResults = {
                older: [olderEntity],
                newer: [newerEntity],
            };
            const pendingAssignmentLookups = [];
            const wrapper = await createSelect({
                props: {},
                global: {
                    provide: {
                        repositoryFactory: {
                            create: () => {
                                return {
                                    search: (criteria, context) => {
                                        if (context !== Shopware.Context.api) {
                                            return Promise.resolve(getCollection());
                                        }

                                        const entities = searchResults[criteria.term] ?? fixture;
                                        return Promise.resolve(
                                            new EntityCollection(
                                                '/test-entity',
                                                'testEntity',
                                                null,
                                                new Criteria(1, 25),
                                                entities,
                                                entities.length,
                                                null,
                                            ),
                                        );
                                    },
                                    searchIds: () =>
                                        new Promise((resolve) => {
                                            pendingAssignmentLookups.push(resolve);
                                        }),
                                };
                            },
                        },
                    },
                },
            });
            await flushPromises();
            jest.useFakeTimers();

            await wrapper.find('.sw-select__selection').trigger('click');
            await flushPromises();
            pendingAssignmentLookups[0]({ data: [] });
            await flushPromises();

            await wrapper.find('input').setValue('older');
            await wrapper.find('input').trigger('change');
            jest.advanceTimersByTime(400);
            await flushPromises();

            await wrapper.find('input').setValue('newer');
            await wrapper.find('input').trigger('change');
            jest.advanceTimersByTime(400);
            await flushPromises();

            pendingAssignmentLookups[2]({ data: [] });
            await flushPromises();
            pendingAssignmentLookups[1]({ data: [] });
            await flushPromises();

            const results = wrapper.findAll('.sw-select-result-list__item-list li');
            expect(results).toHaveLength(1);
            expect(results.at(0).find('.sw-select-result__result-item-text').text()).toBe('newer entry');
            expect(wrapper.find('sw-loader-deprecated-stub').exists()).toBe(false);
        },
    );

    // @deprecated tag:v6.8.0 - The test will be removed with sw-entity-many-to-many-select.
    it.deprecated('v6.8.0.0')(
        'should display all pages when pages are loaded while an earlier page is pending',
        async () => {
            const pendingSearches = [];
            const wrapper = await createSelect({
                props: {},
                global: {
                    provide: {
                        repositoryFactory: {
                            create: () => {
                                return {
                                    search: (criteria, context) => {
                                        if (context !== Shopware.Context.api) {
                                            return Promise.resolve(getCollection());
                                        }

                                        return new Promise((resolve) => {
                                            pendingSearches.push(resolve);
                                        });
                                    },
                                    searchIds: () => Promise.resolve({ data: [] }),
                                };
                            },
                        },
                    },
                },
            });
            const getPage = (name) => {
                return new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    new Criteria(1, 25),
                    [{ id: utils.createId(), name }],
                    100,
                    null,
                );
            };
            await flushPromises();

            await wrapper.find('.sw-select__selection').trigger('click');
            pendingSearches[0](getPage('page one'));
            await flushPromises();

            const listContent = wrapper.find('.sw-select-result-list__content');
            await listContent.trigger('scroll');
            await listContent.trigger('scroll');

            pendingSearches[1](getPage('page two'));
            await flushPromises();
            pendingSearches[2](getPage('page three'));
            await flushPromises();

            const results = wrapper.findAll('.sw-select-result__result-item-text');
            expect(results.map((result) => result.text())).toEqual(['page one', 'page two', 'page three']);
        },
    );
});
