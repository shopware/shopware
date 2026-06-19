/**
 * @sw-package framework
 */

import { ref } from 'vue';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import { useMeteorTableCriteria } from './use-meteor-table-criteria';
import type { SwMeteorEntityDataTableRecord } from '../sw-meteor-entity-data-table.internal-types';

const { Criteria } = Shopware.Data;

type TestRepository = Repository<keyof EntitySchema.Entities>;
type SearchMock = jest.Mock<Promise<EntityCollection<keyof EntitySchema.Entities>>, [CriteriaType, ApiContext]>;

function createSearchResult(
    records: SwMeteorEntityDataTableRecord[],
    total = records.length,
): EntityCollection<keyof EntitySchema.Entities> {
    return Object.assign([...records], { total }) as unknown as EntityCollection<keyof EntitySchema.Entities>;
}

function createRepository(search: SearchMock): TestRepository {
    return {
        search,
    } as unknown as TestRepository;
}

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-criteria', () => {
    it('builds Criteria from state and column sorting metadata', () => {
        const search = jest.fn<ReturnType<SearchMock>, Parameters<SearchMock>>().mockResolvedValue(createSearchResult([]));
        const criteria = useMeteorTableCriteria({
            repository: () => createRepository(search),
            criteria: () => null,
            criteriaResolver: () => null,
            context: () => null,
            state: ref({
                page: 2,
                limit: 10,
                searchTerm: 'shirt',
                sort: {
                    property: 'customerName',
                    direction: 'DESC',
                },
            }),
            cloneState: () => ({
                page: 2,
                limit: 10,
                searchTerm: 'shirt',
            }),
            columns: () => [
                {
                    property: 'customerName',
                    label: 'Customer',
                    sortField: [
                        'firstName',
                        'lastName',
                    ],
                    naturalSorting: true,
                },
            ],
            resetInlineEdit: jest.fn(),
            syncSelectedRecordsWithLoadedRecords: jest.fn(),
            emitLoadSuccess: jest.fn(),
            emitLoadError: jest.fn(),
        });

        expect(criteria.buildCriteria().parse()).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
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

    it('ignores stale load responses from earlier searches', async () => {
        let resolveStaleSearch: (value: EntityCollection<keyof EntitySchema.Entities>) => void = () => {};
        const staleSearchPromise = new Promise<EntityCollection<keyof EntitySchema.Entities>>((resolve) => {
            resolveStaleSearch = resolve;
        });
        const latestResult = createSearchResult([
            {
                id: 'latest-record',
            },
        ]);
        const staleResult = createSearchResult([
            {
                id: 'stale-record',
            },
        ]);
        const search = jest
            .fn<ReturnType<SearchMock>, Parameters<SearchMock>>()
            .mockReturnValueOnce(staleSearchPromise)
            .mockResolvedValueOnce(latestResult);
        const emitLoadSuccess = jest.fn();
        const criteria = useMeteorTableCriteria({
            repository: () => createRepository(search),
            criteria: () => new Criteria(1, 25),
            criteriaResolver: () => null,
            context: () => null,
            state: ref({
                page: 1,
                limit: 25,
                searchTerm: '',
            }),
            cloneState: () => ({
                page: 1,
                limit: 25,
                searchTerm: '',
            }),
            columns: () => [],
            resetInlineEdit: jest.fn(),
            syncSelectedRecordsWithLoadedRecords: jest.fn(),
            emitLoadSuccess,
            emitLoadError: jest.fn(),
        });

        const staleLoad = criteria.load();
        await criteria.load();
        resolveStaleSearch(staleResult);
        await staleLoad;

        expect(criteria.records.value).toEqual(latestResult);
        expect(emitLoadSuccess).toHaveBeenCalledTimes(1);
    });
});
