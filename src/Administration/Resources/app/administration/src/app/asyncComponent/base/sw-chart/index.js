import VueApexCharts from 'vue-apexcharts';
import template from './sw-chart.html.twig';
import './sw-chart.scss';

const { object } = Shopware.Utils;
const { warn } = Shopware.Utils.debug;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @status ready
 * @example-type static
 * @description
 * The sw-chart component is a wrapper component for Apexcharts:
 * <a href="https://apexcharts.com/">https://apexcharts.com/</a>
 * <br>
 * <br>
 * The main difference you need to know in difference to the native component
 * is that you need only one data structure for all types of charts. This main data structure
 * will be converted automatically to the right structure for each chart.
 * <br>
 * You have to use these structure (2.2 Category paired values):
 * <a href="https://apexcharts.com/docs/series/">https://apexcharts.com/docs/series/</a> <br>
 * It is also used here in the example. The "x" values handles also String Values.
 * <br>
 * <br>
 * The wrapper component has a default options which takes care of a consistent
 * look and feel of the charts and an easier usage. You can override all default options
 * manually when you want.
 * <br>
 * <br>
 * Two helper functions can be useful in some use cases.
 * <br>
 * "fillEmptyValues": Fill each day/hour/minute from "options.xaxis.min" to "options.xaxis.max". When no "max"
 * is defined the actual time unit will be used. The values have to be a timestamp in milliseconds.
 * <br>
 * "sort": The values in each series will be sorted in an ascending order.
 * @component-example
 * <sw-chart
 *     :type="'line'"
 *     :series="[
 *         {
 *           name: 'Saleschannel A',
 *           data:[
 *               {x:1559426400000, y:7},
 *               {x:1559512800000, y:6},
 *               {x:1559772000000, y:9},
 *               {x:1559599200000, y:0},
 *               {x:1559685600000, y:2}
 *         ]},
 *         {
 *           name: 'Saleschannel B',
 *           data:[
 *               {x:1559426400000, y:4},
 *               {x:1559512800000, y:2},
 *               {x:1559599200000, y:3},
 *               {x:1559685600000, y:0},
 *               {x:1559772000000, y:1}
 *         ]}
 *     ]"
 *     :options="{
 *         title: {
 *             text: 'Number of orders'
 *         },
 *         xaxis: {
 *             type: 'datetime',
 *             min: 1559260800000,
 *             max: 1559952000000
 *         },
 *         yaxis: {
 *             min:0,
 *             tickAmount:3,
 *             labels:{
 *                 formatter: (value) => { return parseInt(value, 10);}
 *             }
 *         }
 *     }"
 *     :fillEmptyValues="day"
 *     :sort="true">
 * </sw-chart>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    inheritAttrs: false,

    components: {
        apexchart: VueApexCharts,
    },

    props: {
        type: {
            type: String,
            required: true,
            validValues: [
                'line',
                'area',
                'bar',
                'radar',
                'histogram',
                'pie',
                'donut',
                'scatter',
                'bubble',
                'heatmap',
            ],
        },

        options: {
            type: Object,
            required: true,
        },

        series: {
            type: Array,
            required: true,
        },

        height: {
            type: Number,
            required: false,
            default: 400,
        },

        fillEmptyValues: {
            type: String,
            required: false,
            default: null,
            validator(givenValue) {
                return [
                    'minute',
                    'hour',
                    'day',
                ].includes(givenValue);
            },
        },

        sort: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            localeConfig: null,
            isLoading: true,
        };
    },

    computed: {
        mergedOptions() {
            return object.merge(
                {},
                this.defaultOptions,
                this.options,
                { labels: this.mergedLabels },
            );
        },

        mergedLabels() {
            return this.options.labels ? [...this.options.labels, ...this.generatedLabels] : this.generatedLabels;
        },

        optimizedSeries() {
            // when type needs different data structure
            if (this.needOneDimensionalArray) {
                return this.convertedSeriesStructure;
            }

            let optimizedSeries = object.deepCopyObject(this.series);

            if (this.fillEmptyValues) {
                optimizedSeries = this.addZeroValuesToSeries(optimizedSeries);
            }

            if (this.sort) {
                optimizedSeries = this.sortSeries(optimizedSeries);
            }

            return optimizedSeries;
        },

        convertedSeriesStructure() {
            return this.series.map((serie) => {
                const convertedData = serie.data.map((data) => data.y);

                return {
                    name: serie.name,
                    data: convertedData,
                };
            });
        },

        generatedLabels() {
            /**
             * It gets from each serie data all x values.
             *
             * Example: convert from
             * [
             *  {
             *      data: [
             *          {
             *              x: 84561,
             *              y: 9651
             *          },
             *          ...
             *      ],
             *      name: "Total"
             *  }
             *  ...
             * ]
             *
             * to
             *
             * [84561, ...]
             */
            return this.series
                .map(serie => serie.data.map(data => data.x))
                .flat();
        },

        needOneDimensionalArray() {
            return ['pie', 'donut'].indexOf(this.type) >= 0;
        },

        defaultLocale() {
            const adminLocaleLanguage = Shopware.State.getters.adminLocaleLanguage;

            // get all available languages in "apexcharts/dist/locales/**.json"
            const languageFiles = require.context('apexcharts/dist/locales', false, /.json/);

            // change string from "./en.json" to "en"
            const allowedLocales = languageFiles.keys()
                .map(filePath => filePath.replace('./', ''))
                .map(filePath => filePath.replace('.json', ''));

            if (allowedLocales.includes(adminLocaleLanguage)) {
                return adminLocaleLanguage;
            }

            return 'en';
        },

        defaultOptions() {
            return {
                chart: {
                    fontFamily: 'Inter, San Francisco, Segoe UI, Helvetica Neue, Helvetica, Arial, sans-serif',
                    toolbar: {
                        show: false,
                    },

                    defaultLocale: this.defaultLocale,
                    locales: [...(this.localeConfig ? [this.localeConfig] : [])],
                    zoom: false,
                },

                markers: {
                    size: 4,
                    strokeWidth: 0,
                    hover: {
                        size: 8,
                    },
                },

                stroke: {
                    width: 2,
                },

                title: {
                    margin: 0,
                    style: {
                        color: '#52667a',
                        fontSize: '24px',
                    },
                },

                tooltip: {
                    theme: 'dark',
                },

                xaxis: {
                    axisBorder: {
                        show: false,
                    },

                    axisTicks: {
                        show: false,
                    },

                    labels: {
                        style: {
                            colors: '#52667a',
                        },
                    },

                    tooltip: {
                        enabled: true,
                        offsetY: 10,
                    },
                },

                yaxis: {
                    labels: {
                        style: {
                            color: '#52667a',
                        },
                    },
                },
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            return this.loadLocaleConfig().finally(() => {
                this.isLoading = false;
            });
        },

        sortSeries(series) {
            const newSeries = object.deepCopyObject(series);

            newSeries.forEach((serie) => {
                serie.data = serie.data.sort((a, b) => ((a.x && b.x) ? a.x - b.x : a - b));
            });

            return newSeries;
        },

        addZeroValuesToSeries(series) {
            // get zero values
            const zeroValues = this.getZeroValues();

            // copy series
            const newSeries = object.deepCopyObject(series);

            // add zero values for each serie
            newSeries.forEach((serie) => {
                zeroValues.forEach((zeroDate) => {
                    const findDate = serie.data.find(date => date.x === zeroDate.x);
                    if (!findDate) {
                        serie.data.push(zeroDate);
                    }
                });
            });

            return newSeries;
        },

        setDateTime(date) {
            switch (this.fillEmptyValues) {
                case 'minute':
                    date.setSeconds(0, 0);
                    break;
                case 'hour':
                    date.setMinutes(0, 0, 0);
                    break;
                case 'day':
                default:
                    date.setHours(0, 0, 0, 0);
                    break;
            }

            return date;
        },

        incrementByTimeUnit(date) {
            switch (this.fillEmptyValues) {
                case 'hour':
                    date.setHours(date.getHours() + 1);
                    break;
                case 'minute':
                    date.setMinutes(date.getMinutes() + 1);
                    break;
                case 'day':
                default:
                    date.setDate(date.getDate() + 1);
                    break;
            }

            return date;
        },

        getZeroValues() {
            // check if empty dates should filled and xaxis is datetime
            if (!(
                (this.fillEmptyValues) &&
                this.options.xaxis && this.options.xaxis.type === 'datetime'
            )) {
                return [];
            }

            // check if min date is provided
            if (!this.options.xaxis.min) {
                warn('To fill dates without values you have to set a min value timestamp for the xaxis');
                return [];
            }

            // get timestamps for start date
            const fromDate = Shopware.Utils.format.dateWithUserTimezone();
            fromDate.setTime(this.options.xaxis.min);
            this.setDateTime(fromDate);
            const fromDateTimestamp = fromDate.getTime();

            // get timestamps for end date
            let toDateTimestamp;
            if (this.options.xaxis.max) {
                // if user has custom max value
                toDateTimestamp = this.options.xaxis.max;
            } else {
                // get actual day
                const toDate = Shopware.Utils.format.dateWithUserTimezone();
                this.setDateTime(toDate);
                toDate.getTime();
                toDateTimestamp = toDate.getTime();
            }

            // get timestamps between min and now
            const zeroTimestamps = [];

            const indexDate = new Date(fromDateTimestamp);

            // while index date is lower than toDate
            while (indexDate.getTime() < toDateTimestamp) {
                // add index date with zero value to array
                zeroTimestamps.push({
                    x: indexDate.getTime(),
                    y: 0,
                });

                // go to next date unit
                this.incrementByTimeUnit(indexDate);
            }

            return zeroTimestamps;
        },

        async loadLocaleConfig() {
            const defaultLocale = this.defaultLocale;

            // ESLint can´t understand template strings in this import context
            /* eslint-disable-next-line prefer-template */
            const localeConfigModule = await import('apexcharts/dist/locales/' + defaultLocale + '.json');

            this.localeConfig = localeConfigModule?.default;
        },
    },
};
