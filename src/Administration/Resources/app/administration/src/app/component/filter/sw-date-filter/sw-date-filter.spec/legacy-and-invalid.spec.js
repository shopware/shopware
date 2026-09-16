/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - legacy compatibility and invalid input', () => {
    setupDateFilterHooks();

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
