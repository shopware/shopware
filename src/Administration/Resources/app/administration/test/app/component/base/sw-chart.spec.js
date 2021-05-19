import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-chart';
import en from 'apexcharts/dist/locales/en.json';
import nl from 'apexcharts/dist/locales/nl.json';

// mock data
const chartOptions = {
    title: { text: 'Phenomenal chart' },
    chart: {
        toolbar: {
            show: true
        }
    }
};

const chartSeries = [
    { name: 'Demo Serie', data: [10, 41, 35, 51, 49, 62, 69, 91, 148] },
    { name: 'Another demo Serie', data: [12, 24, 35, 58, 88, 95, 125, 145, 148] }
];

// generate function
const generateRandomDateSeries = (numberOfSeries = 1, numberOfDates = 30) => {
    const demoDateSeries = [];

    for (let i = 0; i < numberOfSeries; i += 1) {
        demoDateSeries.push({
            name: `Demo dates ${i}`,
            data: []
        });
    }

    let today;
    let seriesNumber;
    let randomDate;
    for (let i = 0; i < numberOfDates; i += 1) {
        today = new Date();
        today.setHours(0, 0, 0, 0);
        seriesNumber = Math.floor(Math.random() * numberOfSeries);
        randomDate = new Date();
        randomDate.setHours(0, 0, 0, 0);

        // get random date in the double amount of numberOfDates
        randomDate.setDate(randomDate.getDate() - Math.floor(Math.random() * (numberOfDates * 2)));

        // push random number on random date
        demoDateSeries[seriesNumber].data.push({
            x: randomDate.getTime(),
            y: Math.random() * 100
        });
    }

    return demoDateSeries;
};

// initial component setup
const setup = ({ type, series, options, fillEmptyDates, sort } = {}) => {
    const propsData = {
        type: type || 'line',
        series: series || chartSeries,
        options: options || chartOptions,
        fillEmptyDates: fillEmptyDates || false,
        sort: sort || false
    };

    return shallowMount(Shopware.Component.build('sw-chart'), {
        stubs: ['apexchart'],
        propsData
    });
};

describe('components/base/sw-chart', () => {
    beforeEach(() => {
        Shopware.State.commit('setAdminLocale', {
            locale: 'en-GB',
            locales: ['en-GB', 'nl-NL']
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = setup();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should override some default options', async () => {
        const referenceFont = '"Comic Sans MS", cursive, sans-serif';
        const referenceText = 'Check against Comic Sans MS';

        const wrapper = setup({
            options: {
                title: { text: referenceText },
                chart: {
                    fontFamily: referenceFont
                }
            }
        });

        expect(wrapper.vm.mergedOptions.chart.fontFamily).toMatch(referenceFont);
        expect(wrapper.vm.mergedOptions.title.text).toMatch(referenceText);
    });

    it('should have custom options by default', async () => {
        const wrapper = setup();

        expect(wrapper.vm.mergedOptions.tooltip.theme).toMatch('dark');
    });

    it('should fill empty dates', async () => {
        const numberOfSeries = 2;
        const numberOfDates = 30;
        const demoDateSeries = generateRandomDateSeries(numberOfSeries, numberOfDates);

        const dateAgo = new Date();
        dateAgo.setHours(0, 0, 0, 0);
        dateAgo.setDate(dateAgo.getDate() - (numberOfDates * 2));

        const wrapper = setup({
            series: demoDateSeries,
            options: {
                xaxis: { type: 'datetime', min: dateAgo }
            },
            fillEmptyDates: true
        });

        // Expect that the number of dates are greater or equal the double amount of number of dates
        expect(wrapper.vm.optimizedSeries[0].data.length).toBeGreaterThanOrEqual(numberOfDates * 2);

        // check if the number of generated items matches the optimizes series
        const seriesOneLength = demoDateSeries[0].data.length;
        expect(wrapper.vm.optimizedSeries[0].data).not.toHaveLength(seriesOneLength);

        const seriesTwoLength = demoDateSeries[1].data.length;
        expect(wrapper.vm.optimizedSeries[1].data).not.toHaveLength(seriesTwoLength);
    });

    it('should not fill empty dates', async () => {
        const numberOfSeries = 2;
        const numberOfDates = 30;
        const demoDateSeries = generateRandomDateSeries(numberOfSeries, numberOfDates);

        const dateAgo = new Date();
        dateAgo.setHours(0, 0, 0, 0);
        dateAgo.setDate(dateAgo.getDate() - (numberOfDates * 2));

        const wrapper = setup({
            series: demoDateSeries,
            options: {
                xaxis: { type: 'datetime', min: dateAgo }
            },
            fillEmptyDates: false
        });

        // check if the number of generated items matches the optimizes series
        const seriesOneLength = demoDateSeries[0].data.length;
        expect(wrapper.vm.optimizedSeries[0].data).toHaveLength(seriesOneLength);

        const seriesTwoLength = demoDateSeries[1].data.length;
        expect(wrapper.vm.optimizedSeries[1].data).toHaveLength(seriesTwoLength);
    });

    it('should sort the series', async () => {
        const seriesToSort = [
            {
                name: 'First series',
                data: [4, 2, 1, 3, 6, 7, 9, 5, 8]
            },
            {
                name: 'Second series',
                data: [
                    { x: 1559772000000, y: 12 },
                    { x: 1561413600000, y: 9 },
                    { x: 1560722400000, y: 7 },
                    { x: 1559944800000, y: 9 },
                    { x: 1560290400000, y: 7 }
                ]
            }
        ];

        const wrapper = setup({
            series: seriesToSort,
            sort: true
        });

        const isFirstSeriesSorted = wrapper.vm.optimizedSeries[0].data.reduce((acc, value) => {
            return (acc !== false) && (acc <= value) ? value : false;
        });

        const isSecondSeriesSorted = wrapper.vm.optimizedSeries[1].data.reduce((acc, value) => {
            return (acc !== false) && (acc.x <= value.x) ? value : false;
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
                data: [4, 2, 1, 3, 6, 7, 9, 5, 8]
            },
            {
                name: 'Second series',
                data: [
                    { x: 1559772000000, y: 12 },
                    { x: 1561413600000, y: 9 },
                    { x: 1560722400000, y: 7 },
                    { x: 1559944800000, y: 9 },
                    { x: 1560290400000, y: 7 }
                ]
            }
        ];

        const wrapper = setup({
            series: seriesToSort,
            sort: false
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
                    { x: 'Saleschannel C', y: 2 }
                ]
            }
        ];

        const wrapper = await setup({
            series: seriesToConvert,
            type: 'pie'
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
                data: serie.data.map(value => value.y)
            };
        });

        expect(wrapper.vm.optimizedSeries).toEqual(convertedSeriesStructure);
    });

    it('should load the correct default locale', async () => {
        Shopware.State.commit('setAdminLocale', {
            locale: 'nl-NL',
            locales: ['en-GB', 'nl-NL']
        });

        const wrapper = await setup();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.defaultLocale).toEqual('nl');
        expect(wrapper.vm.localeConfig).toEqual(nl);
    });

    it('should load the fallback locale when default locale does not exists', async () => {
        Shopware.State.commit('setAdminLocale', {
            locale: 'foo-BAR',
            locales: ['en-GB', 'nl-NL', 'foo-BAR']
        });

        const wrapper = await setup();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.defaultLocale).toEqual('en');
        expect(wrapper.vm.localeConfig).toEqual(en);
    });
});
