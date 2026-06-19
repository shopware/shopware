/**
 * @sw-package framework
 */

import {
    Criteria,
    createRepositoryMock,
    createWrapper,
    flushPromises,
    getLastSearchCriteria,
    getSearchMock,
    getTable,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type {
    ApiContext,
    SwMeteorEntityDataTableCriteriaResolver,
    SwMeteorEntityDataTableCriteriaResolverPayload,
} from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/criteria', () => {
    registerSwMeteorEntityDataTableHooks();

    it('clones provided criteria before applying state and uses an explicit context', async () => {
        const repository = createRepositoryMock();
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        } as ApiContext;
        const criteria = new Criteria(9, 99);
        criteria.addFilter(Criteria.equals('active', true));
        criteria.addPostFilter(Criteria.equals('visible', true));
        criteria.addAssociation('manufacturer');
        criteria.getAssociation('manufacturer').addFilter(Criteria.equals('name', 'ACME'));
        criteria.addAggregation(Criteria.count('count-id', 'id'));
        criteria.addIncludes({
            product: [
                'id',
                'name',
            ],
        });
        criteria.addFields('id', 'name');
        criteria.addGrouping('manufacturerId');
        criteria.addGroupField('manufacturerId');
        criteria.setTotalCountMode(2);
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        const originalCriteriaPayload = criteria.parse();
        createWrapper({
            props: {
                repository,
                criteria,
                context,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
            },
        });

        await flushPromises();

        const usedCriteria = getLastSearchCriteria(repository);
        const usedCriteriaPayload = usedCriteria.parse();

        expect(usedCriteria).not.toBe(criteria);
        expect(criteria.parse()).toEqual(originalCriteriaPayload);
        expect(usedCriteriaPayload).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
                filter: originalCriteriaPayload.filter,
                'post-filter': originalCriteriaPayload['post-filter'],
                aggregations: originalCriteriaPayload.aggregations,
                includes: originalCriteriaPayload.includes,
                fields: originalCriteriaPayload.fields,
                grouping: originalCriteriaPayload.grouping,
                groupFields: originalCriteriaPayload.groupFields,
                associations: originalCriteriaPayload.associations,
                'total-count-mode': 2,
            }),
        );
        expect(usedCriteriaPayload.sort).toBeUndefined();
        expect(getSearchMock(repository)).toHaveBeenCalledWith(usedCriteria, context);
    });

    it('resolves the prepared criteria before searching', async () => {
        const repository = createRepositoryMock();
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        } as ApiContext;
        const resolvedCriteria = new Criteria(3, 5);
        resolvedCriteria.addFilter(Criteria.equals('active', true));
        let resolverPayload: SwMeteorEntityDataTableCriteriaResolverPayload | undefined;
        const criteriaResolver: SwMeteorEntityDataTableCriteriaResolver = jest.fn((payload) => {
            resolverPayload = payload;
            return resolvedCriteria;
        });

        createWrapper({
            props: {
                repository,
                context,
                criteriaResolver,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
                initialSort: {
                    property: 'name',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(criteriaResolver).toHaveBeenCalledTimes(1);
        expect(resolverPayload).toBeDefined();

        const payload = resolverPayload as SwMeteorEntityDataTableCriteriaResolverPayload;

        expect(payload.criteria.parse()).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
                sort: [
                    {
                        field: 'name',
                        order: 'DESC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
        expect(payload.state).toEqual({
            page: 2,
            limit: 10,
            searchTerm: 'shirt',
            sort: {
                property: 'name',
                direction: 'DESC',
            },
        });
        expect(payload.context).toEqual(context);
        expect(getSearchMock(repository)).toHaveBeenCalledWith(resolvedCriteria, context);
    });

    it('emits an empty successful load when the criteria resolver returns null', async () => {
        const repository = createRepositoryMock();
        const criteriaResolver: SwMeteorEntityDataTableCriteriaResolver = jest.fn(() => null);
        const wrapper = createWrapper({
            props: {
                repository,
                criteriaResolver,
            },
        });

        await flushPromises();

        expect(criteriaResolver).toHaveBeenCalledTimes(1);
        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('dataSource')).toEqual([]);
        expect(getTable(wrapper).props('paginationTotalItems')).toBe(0);
        expect(wrapper.emitted('load-success')).toEqual([
            [
                {
                    records: [],
                    total: 0,
                    state: {
                        page: 1,
                        limit: 25,
                        searchTerm: '',
                    },
                },
            ],
        ]);
    });

    it('applies initial sorting by property', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                initialSort: {
                    property: 'name',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'name',
                        order: 'DESC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
    });

    it('applies sorting by sortField', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        sortField: 'customer.lastName',
                    },
                ],
                initialSort: {
                    property: 'customerName',
                    direction: 'ASC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'customer.lastName',
                        order: 'ASC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
    });

    it('applies multiple sortField values with natural sorting', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        sortField: [
                            'firstName',
                            'lastName',
                        ],
                        naturalSorting: true,
                    },
                ],
                initialSort: {
                    property: 'customerName',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'firstName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                    {
                        field: 'lastName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                ],
            }),
        );
    });
});
