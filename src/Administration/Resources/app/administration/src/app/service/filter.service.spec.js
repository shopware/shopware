/**
 * @package admin
 */

import FilterService from 'src/app/service/filter.service';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { createRouter, createWebHashHistory } from 'vue-router';

describe('app/service/filter.service.js', () => {
    let filterService;
    let filterData;

    beforeEach(async () => {
        const router = createRouter({
            history: createWebHashHistory(),
            routes: [
                {
                    name: 'sw.jest.index',
                    path: '/',
                    component: {
                        template: '<div></div>',
                    },
                },
            ],
        });
        const orgPush = router.push;
        router.push = (location) => {
            return orgPush.call(router, location).catch(() => {});
        };

        Shopware.Application.view = {
            router,
        };

        filterData = new EntityCollection(null, null, null, new Criteria(1, 25), [
            {
                key: 'test',
                userId: '123',
                value: {
                    filter3: {
                        value: [
                            {
                                id: '123',
                            },
                        ],
                        criteria: [
                            {
                                type: 'equalsAny',
                                field: 'salutation.id',
                                value: '123',
                            },
                        ],
                    },
                },
            },
        ]);

        Shopware.State.get('session').currentUser = {
            currentUser: {
                id: '123',
            },
        };

        filterService = new FilterService({
            userConfigRepository: {
                create: () =>
                    Promise.resolve({
                        key: 'test',
                        userId: '123',
                    }),
                search: () => Promise.resolve(filterData),
                save: (criteria) => {
                    filterData = criteria;
                    return Promise.resolve();
                },
            },
        });
    });

    it('getStoredFilters when there is no data from url, no data from database', async () => {
        const data = await filterService.getStoredFilters('test');

        expect(data).not.toBeNull();
    });

    it('getStoredFilters when there is no data from url, has data from database', async () => {
        const data = await filterService.getStoredFilters('test');
        await flushPromises();

        const filterResult = {
            filter3: {
                value: [
                    {
                        id: '123',
                    },
                ],
                criteria: [
                    {
                        type: 'equalsAny',
                        field: 'salutation.id',
                        value: '123',
                    },
                ],
            },
        };

        expect(data).toEqual(filterResult);

        const query = JSON.parse(decodeURIComponent(Shopware.Application.view.router.currentRoute.value.query.test));
        expect(query).toEqual(filterResult);
    });

    it('getStoredFilters when there is no data from database, has data from url', async () => {
        filterData = new EntityCollection(null, null, null, new Criteria(1, 25), []);
        const urlEncodedValue = encodeURIComponent(
            JSON.stringify({
                'stock-filter': {
                    value: null,
                    criteria: null,
                },
            }),
        );
        await Shopware.Application.view.router.push({
            query: {
                test: urlEncodedValue,
            },
        });

        const data = await filterService.getStoredFilters('test');
        expect(data).toEqual({
            'stock-filter': {
                value: null,
                criteria: null,
            },
        });
    });

    it('getStoredFilters when there is data from database and data from url', async () => {
        const urlEncodedValue = encodeURIComponent(
            JSON.stringify({
                'stock-filter': {
                    value: null,
                    criteria: null,
                },
            }),
        );
        await Shopware.Application.view.router.push({
            query: {
                test: urlEncodedValue,
            },
        });

        const data = await filterService.getStoredFilters('test');
        expect(data).toEqual({
            'stock-filter': {
                value: null,
                criteria: null,
            },
        });
    });

    it('getStoredCriteria should return correct criteria', async () => {
        const data = await filterService.getStoredCriteria('test');
        expect(data).toEqual([
            { type: 'equalsAny', field: 'salutation.id', value: '123' },
        ]);
    });

    it('saveFilters should cache and save data correctly', async () => {
        await filterService.getStoredFilters('test');

        const filters = {
            filter1: {
                value: 'filter1',
                criteria: [
                    {
                        type: 'equalsAny',
                        field: 'salutation.id',
                        value: 'filter1',
                    },
                ],
            },
            filter2: {
                value: 'filter2',
                criteria: [
                    {
                        type: 'equalsAny',
                        field: 'salutation.id',
                        value: 'filter2',
                    },
                ],
            },
        };

        await filterService.saveFilters('test', filters);
        expect(filterService._storedFilters.test).toEqual([
            { type: 'equalsAny', field: 'salutation.id', value: 'filter1' },
            { type: 'equalsAny', field: 'salutation.id', value: 'filter2' },
        ]);
    });

    it('mergeWithStoredFilters when there is no cache data', async () => {
        filterService.getStoredFilters = jest.fn().mockImplementation(() => Promise.resolve({}));
        const criteria = new Criteria(1, 25);
        criteria.addFilter({
            type: 'equalsAny',
            field: 'salutation.id',
            value: 'filter1',
        });
        criteria.addFilter({
            type: 'equalsAny',
            field: 'salutation.id',
            value: 'filter2',
        });

        await filterService.mergeWithStoredFilters('test', criteria);
        expect(filterService.getStoredFilters).toHaveBeenCalled();
        expect(filterService._storedFilters.test).toEqual([]);
    });
});
