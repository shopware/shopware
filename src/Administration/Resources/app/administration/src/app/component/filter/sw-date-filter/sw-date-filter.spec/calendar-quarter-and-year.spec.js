/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - calendar quarter and year', () => {
    setupDateFilterHooks();

    describe('currentQuarter', () => {
        afterEach(() => {
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should compute current quarter as Jan-today when today is in Q1', async () => {
            jest.setSystemTime(new Date(1337, 1, 15));

            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                },
            });

            wrapper.vm.onTimeframeSelect('currentQuarter');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1337-01-01T00:00:00.000Z',
                                lte: '1337-02-15T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1337-01-01T00:00:00.000Z',
                        timeframe: 'currentQuarter',
                        to: '1337-02-15T23:59:59.000Z',
                    },
                ],
            ]);
        });
    });

    describe('previousYear and currentYear', () => {
        it('should compute previous year as Jan 1 -> Dec 31 of last year', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                },
            });

            wrapper.vm.onTimeframeSelect('previousYear');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2023-01-01T00:00:00.000Z',
                                lte: '2023-12-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2023-01-01T00:00:00.000Z',
                        timeframe: 'previousYear',
                        to: '2023-12-31T23:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should snap currentYear boundaries to user timezone day edges', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));
            Shopware.Store.get('session').setCurrentUser({ timeZone: 'Europe/Berlin' });

            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                },
            });

            wrapper.vm.onTimeframeSelect('currentYear');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2023-12-31T23:00:00.000Z',
                                lte: '2024-05-15T21:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2023-12-31T23:00:00.000Z',
                        timeframe: 'currentYear',
                        to: '2024-05-15T21:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });
    });

    describe('lastQuarter boundary when today is in Q1', () => {
        beforeEach(() => {
            jest.setSystemTime(new Date(1337, 1, 15));
        });

        afterEach(() => {
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should compute last quarter as Oct-Dec of the previous year', async () => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                },
            });

            wrapper.vm.onTimeframeSelect('lastQuarter');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1336-10-01T00:00:00.000Z',
                                lte: '1336-12-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1336-10-01T00:00:00.000Z',
                        timeframe: 'lastQuarter',
                        to: '1336-12-31T23:59:59.000Z',
                    },
                ],
            ]);
        });
    });
});
