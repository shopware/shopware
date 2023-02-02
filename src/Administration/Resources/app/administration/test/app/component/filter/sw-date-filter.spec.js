import 'src/app/component/filter/sw-date-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/filter/sw-range-filter';

import { shallowMount, enableAutoDestroy } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-date-filter'), {
        stubs: {
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-range-filter': Shopware.Component.build('sw-range-filter'),
            'sw-single-select': true,
            'sw-datepicker': {
                props: ['value'],
                template: `
                    <div class="sw-field--datepicker">
                        <input type="text" ref="flatpickrInput" :value="value" @input="onChange">
                    </div>`,
                methods: {
                    onChange(e) {
                        this.$emit('input', e.target.value);
                    }
                }
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            }
        },
        propsData: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date'
            },
            active: true
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/app/component/filter/sw-date-filter', () => {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date(1337, 11, 31));
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('should emit `filter-update` event when `From` value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-date-filter__from').find('input');

        await input.setValue('2021-01-22');
        await input.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-22' })],
            { from: '2021-01-22', to: null, timeframe: 'custom' }
        ]);
    });

    it('should emit `filter-update` event when `To` value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-date-filter__to').find('input');

        await input.setValue('2021-01-25');
        await input.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { lte: '2021-01-25' })],
            { from: null, to: '2021-01-25', timeframe: 'custom' }
        ]);
    });

    it('should emit `filter-update` event when `From` value `To` value exists', async () => {
        const wrapper = createWrapper();

        const fromInput = wrapper.find('.sw-date-filter__from').find('input');

        await fromInput.setValue('2021-01-19');
        await fromInput.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19' })],
            { from: '2021-01-19', to: null, timeframe: 'custom' }
        ]);

        const toInput = wrapper.find('.sw-date-filter__to').find('input');

        await toInput.setValue('2021-01-25');
        await toInput.trigger('input');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19', lte: '2021-01-25' })],
            { from: '2021-01-19', to: '2021-01-25', timeframe: 'custom' }
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: '2021-01-22',
                to: null,
                timeframe: null
            }
        });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.from).toBeNull();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: null,
                to: '2021-02-01',
                timeframe: null
            }
        });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.to).toBeNull();
    });

    it('should return default dateType of sw-datepicker', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'anytype'
            }
        });

        expect(wrapper.vm.dateType).toEqual('date');
    });

    it('should render From field and To field on the same line', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseTime',
                name: 'releaseTime',
                label: 'Release Time',
                dateType: 'time'
            }
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeTruthy();
        expect(container.classes()).toContain('sw-container--has-divider');
    });

    it('should render From field and To field in different line', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'datetime-local'
            }
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeFalsy();
        expect(container.classes()).not.toContain('sw-container--has-divider');
    });

    it('should render timeframe field', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];

        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'date',
                showTimeframe: true
            }
        });

        const timeframe = wrapper.find('.sw-date-filter__timeframe');

        expect(timeframe.exists()).toBeTruthy();
    });

    const cases = {
        'a year': {
            timeframe: -365,
            expectedFrom: '1336-12-31T00:00:00.000Z',
            expectedTo: '1337-12-31T00:00:00.000Z'
        },
        'a quarter': {
            timeframe: 'lastQuarter',
            expectedFrom: '1337-07-01T00:00:00.000Z',
            expectedTo: '1337-09-30T23:59:59.000Z'
        },
        'a month': {
            timeframe: -30,
            expectedFrom: '1337-12-01T00:00:00.000Z',
            expectedTo: '1337-12-31T00:00:00.000Z'
        },
        'a week': {
            timeframe: -7,
            expectedFrom: '1337-12-24T00:00:00.000Z',
            expectedTo: '1337-12-31T00:00:00.000Z'
        },
        'a day': {
            timeframe: -1,
            expectedFrom: '1337-12-30T00:00:00.000Z',
            expectedTo: '1337-12-31T00:00:00.000Z'
        }
    };

    Object.entries(cases).forEach(([key, timeCase]) => {
        it(`should filter correctly for timeframe ${key}`, async () => {
            const expected = [
                [
                    'releaseDate',
                    [
                        {
                            field: 'releaseDate',
                            parameters: {
                                gte: timeCase.expectedFrom,
                                lte: timeCase.expectedTo
                            },
                            type: 'range'
                        }
                    ],
                    {
                        from: timeCase.expectedFrom,
                        timeframe: timeCase.timeframe,
                        to: timeCase.expectedTo
                    }
                ]
            ];

            global.activeFeatureFlags = ['FEATURE_NEXT_7530'];

            const wrapper = createWrapper();

            await wrapper.setProps({
                filter: {
                    property: 'releaseDate',
                    name: 'releaseDate',
                    label: 'Release Date',
                    dateType: 'date',
                    showTimeframe: true
                }
            });

            const timeframe = wrapper.find('.sw-date-filter__timeframe');
            expect(timeframe.exists()).toBe(true);

            wrapper.vm.onTimeframeSelect(timeCase.timeframe);

            expect(wrapper.emitted()['filter-update']).toEqual(expected);
        });
    });

    it('should console.error for invalid timeframe', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];

        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'date',
                showTimeframe: true
            }
        });

        const timeframe = wrapper.find('.sw-date-filter__timeframe');
        expect(timeframe.exists()).toBeTruthy();

        global.console.error = jest.fn();

        wrapper.vm.onTimeframeSelect('yeeet');

        expect(global.console.error)
            .toHaveBeenCalledWith('Timeframe yeeet is not allowed for sw-date-filter component');

        global.console.error.mockReset();

        expect(wrapper.emitted()['filter-update']).toBe(undefined);
    });
});
