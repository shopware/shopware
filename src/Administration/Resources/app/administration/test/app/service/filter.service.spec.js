import FilterService from 'src/app/service/filter.service';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

describe('app/service/filter.service.js', () => {
    let filterService;
    let filterData;

    beforeEach(() => {
        filterData = new EntityCollection(null, null, null, new Criteria(), [{
            key: 'test',
            userId: '123',
            value: {
                filter3: {
                    value: [
                        {
                            id: '123'
                        }
                    ],
                    criteria: [{
                        type: 'equalsAny',
                        field: 'salutation.id',
                        value: '123'
                    }]
                }
            }
        }]);

        Shopware.State.get('session').currentUser = {
            currentUser: {
                id: '123'
            }
        };

        filterService = new FilterService({
            userConfigRepository: {
                create: () => Promise.resolve({
                    key: 'test',
                    userId: '123'
                }),
                search: () => Promise.resolve(filterData),
                save: criteria => {
                    filterData = criteria;
                    return Promise.resolve();
                }
            }
        });
    });

    it('getStoredFilters should return correct data', async () => {
        const data = await filterService.getStoredFilters('test');

        expect(data).not.toBeNull();
    });

    it('getStoredFilters should return empty object when data is not available', async () => {
        filterData = new EntityCollection(null, null, null, new Criteria(), []);

        const data = await filterService.getStoredFilters('test');
        expect(data).toEqual({});
    });

    it('getStoredCriteria should return correct criteria', async () => {
        const data = await filterService.getStoredCriteria('test');
        expect(data).toEqual([{ type: 'equalsAny', field: 'salutation.id', value: '123' }]);
    });

    it('saveFilters should cache and save data correctly', async () => {
        await filterService.getStoredFilters('test');

        const filters = {
            filter1: {
                value: 'filter1',
                criteria: [{
                    type: 'equalsAny',
                    field: 'salutation.id',
                    value: 'filter1'
                }]
            },
            filter2: {
                value: 'filter2',
                criteria: [{
                    type: 'equalsAny',
                    field: 'salutation.id',
                    value: 'filter2'
                }]
            }
        };

        await filterService.saveFilters('test', filters);
        expect(filterService._storedFilters.test).toEqual([
            { type: 'equalsAny', field: 'salutation.id', value: 'filter1' },
            { type: 'equalsAny', field: 'salutation.id', value: 'filter2' }
        ]);
    });

    it('mergeWithStoredFilters when there is no cache data', async () => {
        filterService.getStoredFilters = jest.fn().mockImplementation(() => Promise.resolve({}));
        const criteria = new Criteria();
        criteria.addFilter({
            type: 'equalsAny',
            field: 'salutation.id',
            value: 'filter1'
        });
        criteria.addFilter({
            type: 'equalsAny',
            field: 'salutation.id',
            value: 'filter2'
        });

        await filterService.mergeWithStoredFilters('test', criteria);
        expect(filterService.getStoredFilters).toHaveBeenCalled();
        expect(filterService._storedFilters.test).toEqual([]);
    });

    it('mergeWithStoredFilters should merge filters when there is cache data', async () => {
        let criteria = new Criteria();
        criteria.addFilter({
            type: 'equalsAny',
            field: 'filter1',
            value: 'filter1'
        });
        criteria.addFilter({
            type: 'equalsAny',
            field: 'filter2',
            value: 'filter2'
        });

        await filterService.mergeWithStoredFilters('test', criteria);

        criteria = new Criteria();
        criteria.addFilter({
            type: 'equalsAny',
            field: 'filter1',
            value: 'newValue'
        });
        criteria.addFilter({
            type: 'equalsAny',
            field: 'filter2',
            value: 'newValue'
        });
        criteria.addFilter({
            type: 'equalsAny',
            field: 'filter3',
            value: 'newValue'
        });

        filterService.getStoredFilters = jest.fn();
        const mergedFilters = await filterService.mergeWithStoredFilters('test', criteria);

        expect(filterService.getStoredFilters).not.toHaveBeenCalled();
        expect(mergedFilters.filters).toEqual([
            { type: 'equalsAny', field: 'filter1', value: 'newValue' },
            { type: 'equalsAny', field: 'filter2', value: 'newValue' },
            { type: 'equalsAny', field: 'filter3', value: 'newValue' },
            { field: 'salutation.id', type: 'equalsAny', value: '123' }
        ]);
    });
});
