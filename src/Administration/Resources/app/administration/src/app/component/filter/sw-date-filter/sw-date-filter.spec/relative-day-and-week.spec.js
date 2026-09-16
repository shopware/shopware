/**
 * @sw-package framework
 */

import mockTimezone from 'test/_helper_/mock-timezone';

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - relative day and week', () => {
    setupDateFilterHooks();

    describe('today and yesterday', () => {
        it('should snap today boundaries to user timezone day edges', async () => {
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

            wrapper.vm.onTimeframeSelect('today');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-14T22:00:00.000Z',
                                lte: '2024-05-15T21:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-14T22:00:00.000Z',
                        timeframe: 'today',
                        to: '2024-05-15T21:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should compute today from the user timezone when the browser timezone is ahead of UTC', async () => {
            jest.useFakeTimers().setSystemTime(new Date('2020-01-01'));

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

            const resetTimezone = mockTimezone('Europe/Berlin');

            try {
                wrapper.vm.onTimeframeSelect('today');

                expect(wrapper.emitted()['filter-update']).toEqual([
                    [
                        'releaseDate',
                        [
                            {
                                field: 'releaseDate',
                                parameters: {
                                    gte: '2020-01-01T00:00:00.000Z',
                                    lte: '2020-01-01T23:59:59.000Z',
                                },
                                type: 'range',
                            },
                        ],
                        {
                            from: '2020-01-01T00:00:00.000Z',
                            timeframe: 'today',
                            to: '2020-01-01T23:59:59.000Z',
                        },
                    ],
                ]);
            } finally {
                resetTimezone();
            }
        });

        it('should roll yesterday into the previous month at month boundaries', async () => {
            jest.setSystemTime(new Date(1338, 0, 1));

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

            wrapper.vm.onTimeframeSelect('yesterday');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1337-12-31T00:00:00.000Z',
                                lte: '1337-12-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1337-12-31T00:00:00.000Z',
                        timeframe: 'yesterday',
                        to: '1337-12-31T23:59:59.000Z',
                    },
                ],
            ]);

            jest.setSystemTime(new Date(1337, 11, 31));
        });
    });

    describe('currentWeek', () => {
        afterEach(() => {
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should compute current ISO week (Mon-today) when today is mid-week', async () => {
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

            wrapper.vm.onTimeframeSelect('currentWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-13T00:00:00.000Z',
                                lte: '2024-05-15T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-13T00:00:00.000Z',
                        timeframe: 'currentWeek',
                        to: '2024-05-15T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should yield a single-day window when today is Monday', async () => {
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

            wrapper.vm.onTimeframeSelect('currentWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-13T00:00:00.000Z',
                                lte: '2024-05-13T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-13T00:00:00.000Z',
                        timeframe: 'currentWeek',
                        to: '2024-05-13T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should extend to a full Mon-Sun window when today is Sunday', async () => {
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

            wrapper.vm.onTimeframeSelect('currentWeek');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-05-13T00:00:00.000Z',
                                lte: '2024-05-19T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-05-13T00:00:00.000Z',
                        timeframe: 'currentWeek',
                        to: '2024-05-19T23:59:59.000Z',
                    },
                ],
            ]);
        });
    });
});
