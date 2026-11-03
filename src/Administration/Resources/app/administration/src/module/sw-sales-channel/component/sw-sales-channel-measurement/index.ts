import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-sales-channel-measurement',
    load: () => import('./src/sw-sales-channel-measurement'),
    public: {
        props: [
            'salesChannel',
            'labelUnitSystem',
            'labelLengthUnit',
            'labelWeightUnit',
        ],
        emits: [
            'measurement-system-change',
        ],
        computed: [
            'measurementSystemRepository',
            'measurementSystemCriteria',
            'unitSystemLabel',
            'dimensionUnitLabel',
            'weightUnitLabel',
            'measurementUnits',
            'measurementSystemOptions',
            'lengthUnitOptions',
            'weightUnitOptions',
            'defaultLengthUnit',
            'defaultWeightUnit',
            'measurementUnitSystemError',
            'measurementLengthUnitError',
            'measurementWeightUnitError',
        ],
        methods: [
            'createdComponent',
            'onMeasurementSystemChange',
            'formatUnitLabel',
            'getDefaultMeasurementSystems',
            'getUnitOptionsByType',
        ],
    },
});
