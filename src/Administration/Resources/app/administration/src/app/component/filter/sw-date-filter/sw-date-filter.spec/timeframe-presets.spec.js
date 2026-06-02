/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - timeframe presets', () => {
    setupDateFilterHooks();

    const cases = {
        today: {
            timeframe: 'today',
            expectedFrom: '1337-12-31T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        yesterday: {
            timeframe: 'yesterday',
            expectedFrom: '1337-12-30T00:00:00.000Z',
            expectedTo: '1337-12-30T23:59:59.000Z',
        },
        '7 days (rolling)': {
            timeframe: -7,
            expectedFrom: '1337-12-24T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        '30 days (rolling)': {
            timeframe: -30,
            expectedFrom: '1337-12-01T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'previous quarter (lastQuarter)': {
            timeframe: 'lastQuarter',
            expectedFrom: '1337-07-01T00:00:00.000Z',
            expectedTo: '1337-09-30T23:59:59.000Z',
        },
        'current month': {
            timeframe: 'currentMonth',
            expectedFrom: '1337-12-01T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'current quarter (Q4)': {
            timeframe: 'currentQuarter',
            expectedFrom: '1337-10-01T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'current year': {
            timeframe: 'currentYear',
            expectedFrom: '1337-01-01T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'previous year': {
            timeframe: 'previousYear',
            expectedFrom: '1336-01-01T00:00:00.000Z',
            expectedTo: '1336-12-31T23:59:59.000Z',
        },
        'last 3 months (clamps month-end overflow)': {
            timeframe: 'last3Months',
            expectedFrom: '1337-09-30T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'last 6 months (clamps month-end overflow)': {
            timeframe: 'last6Months',
            expectedFrom: '1337-06-30T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
        'last 12 months': {
            timeframe: 'last12Months',
            expectedFrom: '1336-12-31T00:00:00.000Z',
            expectedTo: '1337-12-31T23:59:59.000Z',
        },
    };

    Object.entries(cases).forEach(
        ([
            key,
            timeCase,
        ]) => {
            it(`should filter correctly for timeframe ${key}`, async () => {
                const expected = [
                    [
                        'releaseDate',
                        [
                            {
                                field: 'releaseDate',
                                parameters: {
                                    gte: timeCase.expectedFrom,
                                    lte: timeCase.expectedTo,
                                },
                                type: 'range',
                            },
                        ],
                        {
                            from: timeCase.expectedFrom,
                            timeframe: timeCase.timeframe,
                            to: timeCase.expectedTo,
                        },
                    ],
                ];

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

                const timeframe = wrapper.find('.sw-date-filter__timeframe');
                expect(timeframe.exists()).toBe(true);

                wrapper.vm.onTimeframeSelect(timeCase.timeframe);

                expect(wrapper.emitted()['filter-update']).toEqual(expected);
            });
        },
    );
});
