/**
 * @package services-settings
 */
import Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import createCriteriaFromArray from './criteria-helper.service';

describe('src/app/service/criteria-helper.service.ts', () => {
    it('should return a criteria object', () => {
        const result = createCriteriaFromArray({
            associations: [],
            filters: [],
            sortings: [],
        });

        expect(result instanceof Criteria).toBe(true);
    });

    it('should add associations', () => {
        const result = createCriteriaFromArray({
            associations: [
                'test1',
                'test2',
            ],
            filters: [],
            sortings: [],
        });

        expect(new Criteria().addAssociation('test1').addAssociation('test2')).toEqual(result);
    });

    it('should not add filter when type is not existing', () => {
        const result = createCriteriaFromArray({
            associations: [],
            filters: [
                {
                    type: 'unknown',
                    field: 'test',
                    value: 'test',
                },
            ],
            sortings: [],
        });

        expect(new Criteria()).toEqual(result);
    });

    it('should add filters', () => {
        const filters = [
            {
                type: 'contains',
                field: 'name',
                value: 'test1',
            },
            {
                type: 'prefix',
                field: 'test2',
                value: 'test2',
            },
            {
                type: 'suffix',
                field: 'test3',
                value: 'test3',
            },
            {
                type: 'equals',
                field: 'test4',
                value: 'test4',
            },
            // same filter type
            {
                type: 'equals',
                field: 'test5',
                value: 'test5',
            },
            {
                type: 'equalsAny',
                field: 'test6',
                value: [
                    'test6',
                    'test6',
                ],
            },
            {
                type: 'range',
                field: 'test7',
                parameters: {
                    lte: 'test7',
                    lt: 'test7',
                },
            },
            {
                type: 'multi',
                operator: 'and',
                queries: [
                    {
                        type: 'not',
                        operator: 'or',
                        queries: [
                            {
                                type: 'equals',
                                field: 'test8',
                                value: 'test8',
                            },
                        ],
                    },
                    {
                        type: 'equals',
                        field: 'test9',
                        value: 'test9',
                    },
                ],
            },
            {
                type: 'not',
                operator: 'and',
                queries: [
                    {
                        type: 'equals',
                        field: 'test10',
                        value: 'test10',
                    },
                ],
            },
        ];

        const result = createCriteriaFromArray({
            associations: [],
            filters,
            sortings: [],
        });

        const criteria = new Criteria()
            .addFilter(Criteria.contains('name', 'test1'))
            .addFilter(Criteria.prefix('test2', 'test2'))
            .addFilter(Criteria.suffix('test3', 'test3'))
            .addFilter(Criteria.equals('test4', 'test4'))
            .addFilter(Criteria.equals('test5', 'test5'))
            .addFilter(
                Criteria.equalsAny('test6', [
                    'test6',
                    'test6',
                ]),
            )
            .addFilter(Criteria.range('test7', { lte: 'test7', lt: 'test7' }))
            .addFilter(
                Criteria.multi('and', [
                    Criteria.not('or', [
                        Criteria.equals('test8', 'test8'),
                    ]),
                    Criteria.equals('test9', 'test9'),
                ]),
            )
            .addFilter(
                Criteria.not('and', [
                    Criteria.equals('test10', 'test10'),
                ]),
            );

        expect(criteria).toEqual(result);
    });

    it('should not add filters when properties are missing', () => {
        const filters = [
            {
                type: 'contains',
                field: null,
                value: null,
            },
            {
                type: 'prefix',
                field: null,
                value: null,
            },
            {
                type: 'suffix',
                field: null,
                value: null,
            },
            {
                type: 'equals',
                field: null,
                value: null,
            },
            {
                type: 'range',
                field: null,
                parameters: null,
            },
            {
                type: 'equalsAny',
                field: null,
                value: null,
            },
            {
                type: 'not',
                operator: null,
                queries: null,
            },
            {
                type: 'multi',
                operator: null,
                queries: null,
            },
        ];

        const result = createCriteriaFromArray({
            associations: [],
            filters,
            sortings: [],
        });

        expect(new Criteria()).toEqual(result);
    });

    it('should add sorting', () => {
        const result = createCriteriaFromArray({
            associations: [],
            filters: [],
            sortings: [
                {
                    field: 'test',
                    order: 'ASC',
                    naturalSorting: true,
                },
                {
                    field: 'test2',
                    order: 'DESC',
                },
                {
                    field: 'test3',
                },
            ],
        });

        expect(
            new Criteria()
                .addSorting(Criteria.sort('test', 'ASC', true))
                .addSorting(Criteria.sort('test2', 'DESC'))
                .addSorting(Criteria.sort('test3')),
        ).toEqual(result);
    });

    it('should not add sorting when properties are missing', () => {
        const result = createCriteriaFromArray({
            associations: [],
            filters: [],
            sortings: [
                {
                    field: null,
                    order: null,
                    naturalSorting: null,
                },
            ],
        });

        expect(new Criteria()).toEqual(result);
    });
});
