/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

const { Criteria } = Shopware.Data;

describe('src/app/component/filter/sw-date-filter - range inputs and rendering', () => {
    setupDateFilterHooks();

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
});
