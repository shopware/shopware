/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - calendar month and week', () => {
    setupDateFilterHooks();

    describe('lastCalendarMonth', () => {
        it('should compute previous calendar month boundaries', async () => {
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

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1337-11-01T00:00:00.000Z',
                                lte: '1337-11-30T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1337-11-01T00:00:00.000Z',
                        timeframe: 'lastCalendarMonth',
                        to: '1337-11-30T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should roll over to previous year when today is in January', async () => {
            jest.setSystemTime(new Date(1338, 0, 15));

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

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1337-12-01T00:00:00.000Z',
                                lte: '1337-12-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1337-12-01T00:00:00.000Z',
                        timeframe: 'lastCalendarMonth',
                        to: '1337-12-31T23:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should snap boundaries to user timezone day edges', async () => {
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

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-03-31T22:00:00.000Z',
                                lte: '2024-04-30T21:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-03-31T22:00:00.000Z',
                        timeframe: 'lastCalendarMonth',
                        to: '2024-04-30T21:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });
    });

    describe('lastCalendarWeek', () => {
        beforeEach(() => {
            jest.setSystemTime(new Date(2024, 4, 15));
        });

        afterEach(() => {
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should compute previous ISO calendar week (Mon-Sun) when today is mid-week', async () => {
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

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-06T00:00:00.000Z',
                                lte: '2024-05-12T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-06T00:00:00.000Z',
                        timeframe: 'lastCalendarWeek',
                        to: '2024-05-12T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should compute previous calendar week when today is Monday', async () => {
            jest.setSystemTime(new Date(2024, 4, 13));

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

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-06T00:00:00.000Z',
                                lte: '2024-05-12T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-06T00:00:00.000Z',
                        timeframe: 'lastCalendarWeek',
                        to: '2024-05-12T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should compute previous calendar week when today is Sunday', async () => {
            jest.setSystemTime(new Date(2024, 4, 19));

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

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-06T00:00:00.000Z',
                                lte: '2024-05-12T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-06T00:00:00.000Z',
                        timeframe: 'lastCalendarWeek',
                        to: '2024-05-12T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should snap boundaries to user timezone day edges', async () => {
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

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-05T22:00:00.000Z',
                                lte: '2024-05-12T21:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-05T22:00:00.000Z',
                        timeframe: 'lastCalendarWeek',
                        to: '2024-05-12T21:59:59.000Z',
                    },
                ],
            ]);
        });
    });
});
