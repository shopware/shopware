import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-chart',
    load: () => import('./src/sw-chart'),
    public: {
        props: [
            'type',
            'options',
            'series',
            'height',
            'fillEmptyValues',
            'sort',
        ],
        blocks: [
            'sw_line_chart',
            'sw_line_chart_multiple',
            'sw_line_chart_multiple_chart',
            'sw_line_chart_single',
        ],
        computed: [
            'mergedOptions',
            'mergedLabels',
            'optimizedSeries',
            'convertedSeriesStructure',
            'generatedLabels',
            'needOneDimensionalArray',
            'defaultLocale',
            'defaultOptions',
        ],
        methods: [
            'createdComponent',
            'sortSeries',
            'addZeroValuesToSeries',
            'setDateTime',
            'incrementByTimeUnit',
            'getZeroValues',
            'loadLocaleConfig',
        ],
    },
});
