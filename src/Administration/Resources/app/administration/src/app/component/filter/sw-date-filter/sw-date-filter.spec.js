/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

import mockTimezone from 'test/_helper_/mock-timezone';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-date-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': await wrapTestComponent('sw-base-filter', {
                    sync: true,
                }),
                'sw-range-filter': await wrapTestComponent('sw-range-filter', {
                    sync: true,
                }),
                'sw-single-select': true,
                'mt-datepicker': {
                    props: ['modelValue'],
                    template: `
                    <div class="sw-field--datepicker">
                        <input type="text" ref="flatpickrInput" :value="modelValue" @input="onChange">
                    </div>`,
                    methods: {
                        onChange(e) {
                            this.$emit('update:modelValue', e.target.value);
                        },
                    },
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
            },
        },
        props: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
            },
            active: true,
        },
    });
}

describe('src/app/component/filter/sw-date-filter', () => {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date(1337, 11, 31));
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ timeZone: 'UTC' });
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('should emit `filter-update` event when `From` value exists', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-date-filter__from').find('input');

        await input.setValue('2021-01-22');
        await input.trigger('input');
        await flushPromises();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-22T00:00:00.000Z' })],
            { from: '2021-01-22T00:00:00.000Z', to: null, timeframe: 'custom' },
        ]);
    });

    it('should emit `filter-update` event when `To` value exists', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-date-filter__to').find('input');

        await input.setValue('2021-01-25');
        await input.trigger('input');
        await flushPromises();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { lte: '2021-01-25T23:59:59.000Z' })],
            { from: null, to: '2021-01-25T23:59:59.000Z', timeframe: 'custom' },
        ]);
    });

    it('should emit `filter-update` event when `From` value `To` value exists', async () => {
        const wrapper = await createWrapper();

        const fromInput = wrapper.find('.sw-date-filter__from').find('input');

        await fromInput.setValue('2021-01-19');
        await fromInput.trigger('input');
        await flushPromises();

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19T00:00:00.000Z' })],
            { from: '2021-01-19T00:00:00.000Z', to: null, timeframe: 'custom' },
        ]);

        const toInput = wrapper.find('.sw-date-filter__to').find('input');

        await toInput.setValue('2021-01-25');
        await toInput.trigger('input');
        await flushPromises();

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'releaseDate',
            [
                Criteria.range('releaseDate', {
                    gte: '2021-01-19T00:00:00.000Z',
                    lte: '2021-01-25T23:59:59.000Z',
                }),
            ],
            {
                from: '2021-01-19T00:00:00.000Z',
                to: '2021-01-25T23:59:59.000Z',
                timeframe: 'custom',
            },
        ]);
    });

    it('should emit user timezone aware criteria for date ranges', async () => {
        Shopware.Store.get('session').setCurrentUser({ timeZone: 'Europe/Berlin' });

        const wrapper = await createWrapper();

        const fromInput = wrapper.find('.sw-date-filter__from').find('input');
        const toInput = wrapper.find('.sw-date-filter__to').find('input');

        await fromInput.setValue('2024-04-29');
        await fromInput.trigger('input');
        await flushPromises();

        await toInput.setValue('2024-04-29');
        await toInput.trigger('input');
        await flushPromises();

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'releaseDate',
            [
                Criteria.range('releaseDate', {
                    gte: '2024-04-28T22:00:00.000Z',
                    lte: '2024-04-29T21:59:59.000Z',
                }),
            ],
            {
                from: '2024-04-28T22:00:00.000Z',
                to: '2024-04-29T21:59:59.000Z',
                timeframe: 'custom',
            },
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            dateValue: {
                from: '2021-01-22',
                to: null,
                timeframe: null,
            },
        });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.from).toBeNull();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            dateValue: {
                from: null,
                to: '2021-02-01',
                timeframe: null,
            },
        });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.to).toBeNull();
    });

    it('should return default dateType of sw-datepicker', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'anytype',
            },
        });

        expect(wrapper.vm.dateType).toBe('date');
    });

    it('should render From field and To field on the same line', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseTime',
                name: 'releaseTime',
                label: 'Release Time',
                dateType: 'time',
            },
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeTruthy();
        expect(container.classes()).toContain('sw-container--has-divider');
    });

    it('should render From field and To field in different line', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'datetime-local',
            },
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeFalsy();
        expect(container.classes()).not.toContain('sw-container--has-divider');
    });

    it('should render timeframe field', async () => {
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

        expect(timeframe.exists()).toBeTruthy();
    });

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
            Shopware.Store.get('session').setCurrentUser({ timeZone: 'UTC' });

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
                jest.setSystemTime(new Date(1337, 11, 31));
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

    describe('lastNMonths month-end overflow', () => {
        afterEach(() => {
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should clamp last 3 months from May 31 to Feb 29 (not Mar 3) in a leap year', async () => {
            jest.setSystemTime(new Date(2024, 4, 31));

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

            wrapper.vm.onTimeframeSelect('last3Months');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2024-02-29T00:00:00.000Z',
                                lte: '2024-05-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2024-02-29T00:00:00.000Z',
                        timeframe: 'last3Months',
                        to: '2024-05-31T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should clamp last 3 months from May 31 to Feb 28 in a non-leap year', async () => {
            jest.setSystemTime(new Date(2023, 4, 31));

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

            wrapper.vm.onTimeframeSelect('last3Months');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2023-02-28T00:00:00.000Z',
                                lte: '2023-05-31T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2023-02-28T00:00:00.000Z',
                        timeframe: 'last3Months',
                        to: '2023-05-31T23:59:59.000Z',
                    },
                ],
            ]);
        });

        it('should compute last 12 months as the same day one year back when no overflow', async () => {
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

            wrapper.vm.onTimeframeSelect('last12Months');

            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2023-05-15T00:00:00.000Z',
                                lte: '2024-05-15T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2023-05-15T00:00:00.000Z',
                        timeframe: 'last12Months',
                        to: '2024-05-15T23:59:59.000Z',
                    },
                ],
            ]);
        });
    });

    describe('legacy timeframe compatibility', () => {
        it('should alias the legacy lastDay (-1) value to "yesterday" when called programmatically', async () => {
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

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-1);

            expect(global.console.error).not.toHaveBeenCalled();
            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '1337-12-30T00:00:00.000Z',
                                lte: '1337-12-30T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '1337-12-30T00:00:00.000Z',
                        timeframe: 'yesterday',
                        to: '1337-12-30T23:59:59.000Z',
                    },
                ],
            ]);

            global.console.error.mockReset();
        });

        it('should alias the legacy lastYear (-365) value to "last12Months" when called programmatically', async () => {
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

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-365);

            expect(global.console.error).not.toHaveBeenCalled();
            expect(wrapper.emitted()['filter-update']).toEqual([
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: '2023-05-15T00:00:00.000Z',
                                lte: '2024-05-15T23:59:59.000Z',
                            },
                            type: 'range',
                        },
                    ],
                    {
                        from: '2023-05-15T00:00:00.000Z',
                        timeframe: 'last12Months',
                        to: '2024-05-15T23:59:59.000Z',
                    },
                ],
            ]);

            global.console.error.mockReset();
            jest.setSystemTime(new Date(1337, 11, 31));
        });

        it('should rewrite a persisted lastDay (-1) timeframe to "yesterday" without touching from/to', async () => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                    value: {
                        from: '1337-12-30T00:00:00.000Z',
                        to: '1337-12-31T23:59:59.000Z',
                        timeframe: -1,
                    },
                },
            });

            expect(wrapper.vm.dateValue).toEqual({
                from: '1337-12-30T00:00:00.000Z',
                to: '1337-12-31T23:59:59.000Z',
                timeframe: 'yesterday',
            });
        });

        it('should rewrite a persisted lastYear (-365) timeframe to "last12Months" without touching from/to', async () => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true,
                    value: {
                        from: '1336-12-31T00:00:00.000Z',
                        to: '1337-12-31T23:59:59.000Z',
                        timeframe: -365,
                    },
                },
            });

            expect(wrapper.vm.dateValue).toEqual({
                from: '1336-12-31T00:00:00.000Z',
                to: '1337-12-31T23:59:59.000Z',
                timeframe: 'last12Months',
            });
        });

        it('should leave unknown legacy timeframe values untouched so they still trigger the console-error guard', async () => {
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

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-42);

            expect(global.console.error).toHaveBeenCalledWith('Timeframe -42 is not allowed for sw-date-filter component');
            expect(wrapper.emitted()['filter-update']).toBeUndefined();

            global.console.error.mockReset();
        });
    });

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

    it('should console.error for invalid timeframe', async () => {
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
        expect(timeframe.exists()).toBeTruthy();

        global.console.error = jest.fn();

        wrapper.vm.onTimeframeSelect('yeeet');

        expect(global.console.error).toHaveBeenCalledWith('Timeframe yeeet is not allowed for sw-date-filter component');

        global.console.error.mockReset();

        expect(wrapper.emitted()['filter-update']).toBeUndefined();
    });
});
