/**
 * @sw-package framework
 */

/* eslint jest/expect-expect: ["error", { "assertFunctionNames": ["expect", "expectWrapperToEmitTimeframeRange"] }] */

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

function createDateTimeframeFilter(overrides = {}) {
    return {
        property: 'releaseDate',
        name: 'releaseDate',
        label: 'Release Date',
        dateType: 'date',
        showTimeframe: true,
        ...overrides,
    };
}

async function createDateFilterWithTimeframe({ timezone = 'UTC' } = {}) {
    Shopware.Store.get('session').setCurrentUser({ timeZone: timezone });

    const wrapper = await createWrapper();

    await wrapper.setProps({
        filter: createDateTimeframeFilter(),
    });

    return wrapper;
}

function expectWrapperToEmitTimeframeRange(wrapper, { timeframe, from, to }) {
    expect(wrapper.emitted()['filter-update']).toEqual([
        [
            'releaseDate',
            [
                Criteria.range('releaseDate', {
                    gte: from,
                    lte: to,
                }),
            ],
            {
                from,
                timeframe,
                to,
            },
        ],
    ]);
}

describe('src/app/component/filter/sw-date-filter', () => {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date(1337, 11, 31));
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ timeZone: 'UTC' });
    });

    afterEach(() => {
        jest.setSystemTime(new Date(1337, 11, 31));
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

    it.each([
        [
            'from',
            {
                from: '2021-01-22',
                to: null,
                timeframe: null,
            },
        ],
        [
            'to',
            {
                from: null,
                to: '2021-02-01',
                timeframe: null,
            },
        ],
    ])('should emit `filter-reset` event when user clicks Reset button when %s value exists', async (property, dateValue) => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            dateValue,
        });

        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue[property]).toBeNull();
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
            filter: createDateTimeframeFilter(),
        });

        const timeframe = wrapper.find('.sw-date-filter__timeframe');

        expect(timeframe.exists()).toBeTruthy();
    });

    it.each([
        [
            'today',
            {
                timeframe: 'today',
                expectedFrom: '1337-12-31T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'yesterday',
            {
                timeframe: 'yesterday',
                expectedFrom: '1337-12-30T00:00:00.000Z',
                expectedTo: '1337-12-30T23:59:59.000Z',
            },
        ],
        [
            '7 days (rolling)',
            {
                timeframe: -7,
                expectedFrom: '1337-12-24T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            '30 days (rolling)',
            {
                timeframe: -30,
                expectedFrom: '1337-12-01T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'previous quarter (lastQuarter)',
            {
                timeframe: 'lastQuarter',
                expectedFrom: '1337-07-01T00:00:00.000Z',
                expectedTo: '1337-09-30T23:59:59.000Z',
            },
        ],
        [
            'current month',
            {
                timeframe: 'currentMonth',
                expectedFrom: '1337-12-01T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'current quarter (Q4)',
            {
                timeframe: 'currentQuarter',
                expectedFrom: '1337-10-01T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'current year',
            {
                timeframe: 'currentYear',
                expectedFrom: '1337-01-01T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'previous year',
            {
                timeframe: 'previousYear',
                expectedFrom: '1336-01-01T00:00:00.000Z',
                expectedTo: '1336-12-31T23:59:59.000Z',
            },
        ],
        [
            'last 3 months (clamps month-end overflow)',
            {
                timeframe: 'last3Months',
                expectedFrom: '1337-09-30T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'last 6 months (clamps month-end overflow)',
            {
                timeframe: 'last6Months',
                expectedFrom: '1337-06-30T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
        [
            'last 12 months',
            {
                timeframe: 'last12Months',
                expectedFrom: '1336-12-31T00:00:00.000Z',
                expectedTo: '1337-12-31T23:59:59.000Z',
            },
        ],
    ])('should filter correctly for timeframe %s', async (key, timeCase) => {
        const wrapper = await createDateFilterWithTimeframe();

        const timeframe = wrapper.find('.sw-date-filter__timeframe');
        expect(timeframe.exists()).toBe(true);

        wrapper.vm.onTimeframeSelect(timeCase.timeframe);

        expectWrapperToEmitTimeframeRange(wrapper, {
            timeframe: timeCase.timeframe,
            from: timeCase.expectedFrom,
            to: timeCase.expectedTo,
        });
    });

    describe('today and yesterday', () => {
        it('should snap today boundaries to user timezone day edges', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createDateFilterWithTimeframe({
                timezone: 'Europe/Berlin',
            });

            wrapper.vm.onTimeframeSelect('today');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'today',
                from: '2024-05-14T22:00:00.000Z',
                to: '2024-05-15T21:59:59.000Z',
            });

        });

        it('should compute today from the user timezone when the browser timezone is ahead of UTC', async () => {
            jest.useFakeTimers().setSystemTime(new Date('2020-01-01'));

            const wrapper = await createDateFilterWithTimeframe();

            const resetTimezone = mockTimezone('Europe/Berlin');

            try {
                wrapper.vm.onTimeframeSelect('today');

                expectWrapperToEmitTimeframeRange(wrapper, {
                    timeframe: 'today',
                    from: '2020-01-01T00:00:00.000Z',
                    to: '2020-01-01T23:59:59.000Z',
                });
            } finally {
                resetTimezone();
            }
        });

        it('should roll yesterday into the previous month at month boundaries', async () => {
            jest.setSystemTime(new Date(1338, 0, 1));

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('yesterday');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'yesterday',
                from: '1337-12-31T00:00:00.000Z',
                to: '1337-12-31T23:59:59.000Z',
            });

        });
    });

    describe('currentWeek', () => {
        it.each([
            [
                'when today is mid-week',
                new Date(2024, 4, 15),
                '2024-05-13T00:00:00.000Z',
                '2024-05-15T23:59:59.000Z',
            ],
            [
                'when today is Monday',
                new Date(2024, 4, 13),
                '2024-05-13T00:00:00.000Z',
                '2024-05-13T23:59:59.000Z',
            ],
            [
                'when today is Sunday',
                new Date(2024, 4, 19),
                '2024-05-13T00:00:00.000Z',
                '2024-05-19T23:59:59.000Z',
            ],
        ])('should compute current ISO week %s', async (label, systemTime, from, to) => {
            jest.setSystemTime(systemTime);

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('currentWeek');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'currentWeek',
                from,
                to,
            });
        });
    });

    describe('currentQuarter', () => {
        it('should compute current quarter as Jan-today when today is in Q1', async () => {
            jest.setSystemTime(new Date(1337, 1, 15));

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('currentQuarter');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'currentQuarter',
                from: '1337-01-01T00:00:00.000Z',
                to: '1337-02-15T23:59:59.000Z',
            });
        });
    });

    describe('previousYear and currentYear', () => {
        it('should compute previous year as Jan 1 -> Dec 31 of last year', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('previousYear');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'previousYear',
                from: '2023-01-01T00:00:00.000Z',
                to: '2023-12-31T23:59:59.000Z',
            });

        });

        it('should snap currentYear boundaries to user timezone day edges', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createDateFilterWithTimeframe({
                timezone: 'Europe/Berlin',
            });

            wrapper.vm.onTimeframeSelect('currentYear');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'currentYear',
                from: '2023-12-31T23:00:00.000Z',
                to: '2024-05-15T21:59:59.000Z',
            });

        });
    });

    describe('lastNMonths month-end overflow', () => {
        it.each([
            [
                'should clamp last 3 months from May 31 to Feb 29 (not Mar 3) in a leap year',
                new Date(2024, 4, 31),
                'last3Months',
                '2024-02-29T00:00:00.000Z',
                '2024-05-31T23:59:59.000Z',
            ],
            [
                'should clamp last 3 months from May 31 to Feb 28 in a non-leap year',
                new Date(2023, 4, 31),
                'last3Months',
                '2023-02-28T00:00:00.000Z',
                '2023-05-31T23:59:59.000Z',
            ],
            [
                'should compute last 12 months as the same day one year back when no overflow',
                new Date(2024, 4, 15),
                'last12Months',
                '2023-05-15T00:00:00.000Z',
                '2024-05-15T23:59:59.000Z',
            ],
        ])('%s', async (label, systemTime, timeframe, from, to) => {
            jest.setSystemTime(systemTime);

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect(timeframe);

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe,
                from,
                to,
            });
        });
    });

    describe('legacy timeframe compatibility', () => {
        it('should alias the legacy lastDay (-1) value to "yesterday" when called programmatically', async () => {
            const wrapper = await createDateFilterWithTimeframe();

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-1);

            expect(global.console.error).not.toHaveBeenCalled();
            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'yesterday',
                from: '1337-12-30T00:00:00.000Z',
                to: '1337-12-30T23:59:59.000Z',
            });

            global.console.error.mockReset();
        });

        it('should alias the legacy lastYear (-365) value to "last12Months" when called programmatically', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createDateFilterWithTimeframe();

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-365);

            expect(global.console.error).not.toHaveBeenCalled();
            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'last12Months',
                from: '2023-05-15T00:00:00.000Z',
                to: '2024-05-15T23:59:59.000Z',
            });

            global.console.error.mockReset();
        });

        it.each([
            [
                'lastDay (-1)',
                {
                    from: '1337-12-30T00:00:00.000Z',
                    to: '1337-12-31T23:59:59.000Z',
                    timeframe: -1,
                },
                {
                    from: '1337-12-30T00:00:00.000Z',
                    to: '1337-12-31T23:59:59.000Z',
                    timeframe: 'yesterday',
                },
            ],
            [
                'lastYear (-365)',
                {
                    from: '1336-12-31T00:00:00.000Z',
                    to: '1337-12-31T23:59:59.000Z',
                    timeframe: -365,
                },
                {
                    from: '1336-12-31T00:00:00.000Z',
                    to: '1337-12-31T23:59:59.000Z',
                    timeframe: 'last12Months',
                },
            ],
        ])('should rewrite a persisted %s timeframe without touching from/to', async (label, value, expectedDateValue) => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                filter: createDateTimeframeFilter({ value }),
            });

            expect(wrapper.vm.dateValue).toEqual(expectedDateValue);
        });

        it('should leave unknown legacy timeframe values untouched so they still trigger the console-error guard', async () => {
            const wrapper = await createDateFilterWithTimeframe();

            global.console.error = jest.fn();

            wrapper.vm.onTimeframeSelect(-42);

            expect(global.console.error).toHaveBeenCalledWith('Timeframe -42 is not allowed for sw-date-filter component');
            expect(wrapper.emitted()['filter-update']).toBeUndefined();

            global.console.error.mockReset();
        });
    });

    describe('lastCalendarMonth', () => {
        it('should compute previous calendar month boundaries', async () => {
            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastCalendarMonth',
                from: '1337-11-01T00:00:00.000Z',
                to: '1337-11-30T23:59:59.000Z',
            });
        });

        it('should roll over to previous year when today is in January', async () => {
            jest.setSystemTime(new Date(1338, 0, 15));

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastCalendarMonth',
                from: '1337-12-01T00:00:00.000Z',
                to: '1337-12-31T23:59:59.000Z',
            });

        });

        it('should snap boundaries to user timezone day edges', async () => {
            jest.setSystemTime(new Date(2024, 4, 15));

            const wrapper = await createDateFilterWithTimeframe({
                timezone: 'Europe/Berlin',
            });

            wrapper.vm.onTimeframeSelect('lastCalendarMonth');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastCalendarMonth',
                from: '2024-03-31T22:00:00.000Z',
                to: '2024-04-30T21:59:59.000Z',
            });

        });
    });

    describe('lastCalendarWeek', () => {
        beforeEach(() => {
            jest.setSystemTime(new Date(2024, 4, 15));
        });

        it.each([
            ['when today is mid-week', new Date(2024, 4, 15)],
            ['when today is Monday', new Date(2024, 4, 13)],
            ['when today is Sunday', new Date(2024, 4, 19)],
        ])('should compute previous calendar week %s', async (label, systemTime) => {
            jest.setSystemTime(systemTime);

            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastCalendarWeek',
                from: '2024-05-06T00:00:00.000Z',
                to: '2024-05-12T23:59:59.000Z',
            });
        });

        it('should snap boundaries to user timezone day edges', async () => {
            const wrapper = await createDateFilterWithTimeframe({
                timezone: 'Europe/Berlin',
            });

            wrapper.vm.onTimeframeSelect('lastCalendarWeek');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastCalendarWeek',
                from: '2024-05-05T22:00:00.000Z',
                to: '2024-05-12T21:59:59.000Z',
            });
        });
    });

    describe('lastQuarter boundary when today is in Q1', () => {
        beforeEach(() => {
            jest.setSystemTime(new Date(1337, 1, 15));
        });

        it('should compute last quarter as Oct-Dec of the previous year', async () => {
            const wrapper = await createDateFilterWithTimeframe();

            wrapper.vm.onTimeframeSelect('lastQuarter');

            expectWrapperToEmitTimeframeRange(wrapper, {
                timeframe: 'lastQuarter',
                from: '1336-10-01T00:00:00.000Z',
                to: '1336-12-31T23:59:59.000Z',
            });
        });
    });

    it('should console.error for invalid timeframe', async () => {
        const wrapper = await createDateFilterWithTimeframe();

        const timeframe = wrapper.find('.sw-date-filter__timeframe');
        expect(timeframe.exists()).toBeTruthy();

        global.console.error = jest.fn();

        wrapper.vm.onTimeframeSelect('yeeet');

        expect(global.console.error).toHaveBeenCalledWith('Timeframe yeeet is not allowed for sw-date-filter component');

        global.console.error.mockReset();

        expect(wrapper.emitted()['filter-update']).toBeUndefined();
    });
});
