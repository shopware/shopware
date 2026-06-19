/**
 * @sw-package framework
 */

import { createWrapper, setupDateFilterHooks } from './sw-date-filter.fixtures';

describe('src/app/component/filter/sw-date-filter - lastNMonths month-end overflow', () => {
    setupDateFilterHooks();

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
