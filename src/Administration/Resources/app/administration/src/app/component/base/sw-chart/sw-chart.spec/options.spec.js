/**
 * @sw-package framework
 */

import { setup } from './sw-chart.fixtures';

describe('asyncComponent/base/sw-chart', () => {
    beforeEach(async () => {
        Shopware.Store.get('session').setAdminLocaleState({
            locale: 'en-GB',
            locales: ['en-GB', 'nl-NL'],
        });
    });

    it('should override some default options', async () => {
        const referenceFont = '"Comic Sans MS", cursive, sans-serif';
        const referenceText = 'Check against Comic Sans MS';

        const wrapper = await setup({
            options: {
                title: { text: referenceText },
                chart: {
                    fontFamily: referenceFont,
                },
            },
        });

        expect(wrapper.vm.mergedOptions.chart.fontFamily).toMatch(referenceFont);
        expect(wrapper.vm.mergedOptions.title.text).toMatch(referenceText);
    });

    it('should have custom options by default', async () => {
        const wrapper = await setup();

        expect(wrapper.vm.mergedOptions.tooltip.theme).toMatch('dark');
    });

    it('should fill empty day values', async () => {
        const numberOfDates = 30;

        const dateAgo = new Date();
        dateAgo.setHours(0, 0, 0, 0);
        dateAgo.setDate(dateAgo.getDate() - numberOfDates);

        const wrapper = await setup({
            series: [
                {
                    name: 'Demo dates',
                    data: [
                        {
                            x: new Date().setDate(new Date().getDate() - 20),
                            y: Math.random() * 100,
                        },
                    ],
                },
            ],
            options: {
                xaxis: { type: 'datetime', min: dateAgo },
            },
            fillEmptyValues: 'day',
        });

        // Expect that the number of values are greater or equal the double amount of number of dates
        expect(wrapper.vm.optimizedSeries[0].data).toHaveLength(numberOfDates + 1);
    });

    it('should fill empty hour values', async () => {
        const numberOfHours = 24;

        const dateAgo = new Date();
        dateAgo.setHours(dateAgo.getHours() - numberOfHours);

        const wrapper = await setup({
            series: [
                {
                    name: 'Demo dates',
                    data: [
                        {
                            x: new Date().setHours(new Date().getHours() - 20),
                            y: Math.random() * 100,
                        },
                    ],
                },
            ],
            options: {
                xaxis: { type: 'datetime', min: dateAgo },
            },
            fillEmptyValues: 'hour',
        });

        // Expect that the number of values are greater or equal the double amount of number of dates
        expect(wrapper.vm.optimizedSeries[0].data).toHaveLength(numberOfHours + 1);
    });

    it('should fill empty minute values', async () => {
        const numberOfMinutes = 60;

        const dateAgo = new Date();
        dateAgo.setMinutes(dateAgo.getMinutes() - numberOfMinutes);

        const wrapper = await setup({
            series: [
                {
                    name: 'Demo dates',
                    data: [
                        {
                            x: new Date().setMinutes(new Date().getMinutes() - 20),
                            y: Math.random() * 100,
                        },
                    ],
                },
            ],
            options: {
                xaxis: { type: 'datetime', min: dateAgo },
            },
            fillEmptyValues: 'minute',
        });

        // Expect that the number of values are greater or equal the double amount of number of dates
        expect(wrapper.vm.optimizedSeries[0].data).toHaveLength(numberOfMinutes + 1);
    });

    it('should not fill empty values', async () => {
        const numberOfDates = 30;

        const dateAgo = new Date();
        dateAgo.setHours(0, 0, 0, 0);
        dateAgo.setDate(dateAgo.getDate() - numberOfDates);

        // if fillEmptyValues is not present, there should be no additional values
        const wrapper = await setup({
            series: [
                {
                    name: 'Demo dates',
                    data: [
                        {
                            x: new Date().setDate(new Date().getDate() - 20),
                            y: Math.random() * 100,
                        },
                    ],
                },
            ],
            options: {
                xaxis: { type: 'datetime', min: dateAgo },
            },
        });

        // Expect that the number of values are greater or equal the double amount of number of dates
        expect(wrapper.vm.optimizedSeries[0].data).toHaveLength(1);
    });

    it('should sort the series', async () => {
        const seriesToSort = [
            {
                name: 'First series',
                data: [
                    4,
                    2,
                    1,
                    3,
                    6,
                    7,
                    9,
                    5,
                    8,
                ],
            },
            {
                name: 'Second series',
                data: [
                    { x: 1559772000000, y: 12 },
                    { x: 1561413600000, y: 9 },
                    { x: 1560722400000, y: 7 },
                    { x: 1559944800000, y: 9 },
                    { x: 1560290400000, y: 7 },
                ],
            },
        ];

        const wrapper = await setup({
            series: seriesToSort,
            sort: true,
        });

        const isFirstSeriesSorted = wrapper.vm.optimizedSeries[0].data.reduce((acc, value) => {
            return acc !== false && acc <= value ? value : false;
        });

        const isSecondSeriesSorted = wrapper.vm.optimizedSeries[1].data.reduce((acc, value) => {
            return acc !== false && acc.x <= value.x ? value : false;
        });

        // check if sorted
        expect(isFirstSeriesSorted).toBeTruthy();
        expect(isSecondSeriesSorted).toBeTruthy();

        // check if the series does not matches the orginal
        expect(wrapper.vm.optimizedSeries[0].data).not.toEqual(seriesToSort[0].data);
        expect(wrapper.vm.optimizedSeries[1].data).not.toEqual(seriesToSort[1].data);
    });

    it('should not sort the series', async () => {
        const seriesToSort = [
            {
                name: 'First series',
                data: [
                    4,
                    2,
                    1,
                    3,
                    6,
                    7,
                    9,
                    5,
                    8,
                ],
            },
            {
                name: 'Second series',
                data: [
                    { x: 1559772000000, y: 12 },
                    { x: 1561413600000, y: 9 },
                    { x: 1560722400000, y: 7 },
                    { x: 1559944800000, y: 9 },
                    { x: 1560290400000, y: 7 },
                ],
            },
        ];

        const wrapper = await setup({
            series: seriesToSort,
            sort: false,
        });

        // check if the series matches the orginal
        expect(wrapper.vm.optimizedSeries[0].data).toEqual(seriesToSort[0].data);
        expect(wrapper.vm.optimizedSeries[1].data).toEqual(seriesToSort[1].data);
    });

    it('should convert the data structure', async () => {
        const seriesToConvert = [
            {
                name: 'Sales Channel Orders',
                data: [
                    { x: 'Saleschannel A', y: 23 },
                    { x: 'Saleschannel B', y: 17 },
                    { x: 'Saleschannel C', y: 2 },
                ],
            },
        ];

        const wrapper = await setup({
            series: seriesToConvert,
            type: 'pie',
        });

        // check if conversion to label works
        const convertedLabelStructure = seriesToConvert.reduce((acc, serie) => {
            acc = [...acc, ...serie.data.map((data) => data.x)];
            return acc;
        }, []);

        expect(wrapper.vm.mergedOptions.labels).toEqual(convertedLabelStructure);

        // check if conversion of series works
        const convertedSeriesStructure = seriesToConvert.map((serie) => {
            return {
                name: serie.name,
                data: serie.data.map((value) => value.y),
            };
        });

        expect(wrapper.vm.optimizedSeries).toEqual(convertedSeriesStructure);
    });
});
