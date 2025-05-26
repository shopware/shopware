/**
 * @sw-package inventory
 */

import template from './sw-sales-channel-measurement.html.twig';
import './sw-sales-channel-measurement.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        labelUnitSystem: {
            type: String,
            required: false,
        },

        labelLengthUnit: {
            type: String,
            required: false,
        },

        labelWeightUnit: {
            type: String,
            required: false,
        },
    },

    emits: [
        'measurement-system-change',
    ],

    data() {
        return {
            measurementUnits: null,
            defaultMeasurementSystem: null,
        };
    },

    computed: {
        measurementSystemCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addAssociation('units');

            criteria.getAssociation('units').addFilter(Criteria.equals('default', true));

            return criteria;
        },

        lengthUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'length'));
            criteria.addFilter(Criteria.equals('measurementSystem.technicalName', this.salesChannel.measurementUnits.system));

            return criteria;
        },

        weightUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'weight'));
            criteria.addFilter(Criteria.equals('measurementSystem.technicalName', this.salesChannel.measurementUnits.system));

            return criteria;
        },

        unitSystemLabel() {
            return this.labelUnitSystem || this.$t('sw-sales-channel.detail.measurementSystem.labelUnitSystem');
        },

        dimensionUnitLabel() {
            return this.labelLengthUnit || this.$t('sw-sales-channel.detail.measurementSystem.labelLengthUnit');
        },

        weightUnitLabel() {
            return this.labelWeightUnit || this.$t('sw-sales-channel.detail.measurementSystem.labelWeightUnit');
        },

        measurementSystem: {
            get() {
                return this.salesChannel.measurementUnits.system;
            },
            set(value) {
                this.salesChannel.measurementUnits.system = value;
            }
        },

        lengthUnit: {
            get() {
                return this.salesChannel.measurementUnits?.length;
            },
            set(value) {
                this.salesChannel.measurementUnits.length = value;
            }
        },

        weightUnit: {
            get() {
                return this.salesChannel.measurementUnits.weight;
            },
            set(value) {
                this.salesChannel.measurementUnits.weight = value;
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // Initialize measurement units if needed
            if (!this.salesChannel.measurementUnits) {
                this.salesChannel.measurementUnits = {};
            }

            this.defaultMeasurementSystem = { ...this.salesChannel.measurementUnits };
        },

        onMeasurementSystemChange(measurementSystemId) {
            const measurementSystemSelect = this.$refs.measurementSystemSelect;

            const measurementSystem = measurementSystemSelect?.results?.get(measurementSystemId);

            this.$emit('measurement-system-change', measurementSystemId, measurementSystem);

            // Update the measurementSystem property on the sales channel
            this.salesChannel.measurementSystem = measurementSystem;

            if (measurementSystemId === this.defaultMeasurementSystem.measurementSystemId) {
                this.salesChannel.measurementUnits = { ...this.defaultMeasurementSystem.measurementUnits };
                return;
            }

            // Initialize new measurement units object
            this.salesChannel.measurementUnits = {};

            // Set default units based on the measurement system
            const lengthUnit = measurementSystem?.units?.filter((unit) => unit.type === 'length').first();
            if (lengthUnit) {
                this.salesChannel.measurementUnits.length = lengthUnit.id;
            }

            const weightUnit = measurementSystem?.units?.filter((unit) => unit.type === 'weight').first();
            if (weightUnit) {
                this.salesChannel.measurementUnits.weight = weightUnit.id;
            }
        },

        formatUnitLabel(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },
    },
});
