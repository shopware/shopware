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
            measurementSystem: null,
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
            if (this.salesChannel?.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.salesChannel.measurementSystemId));
            }

            return criteria;
        },

        weightUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'weight'));
            if (this.salesChannel.measurementSystemId) {
                criteria.addFilter(Criteria.equals('measurementSystem.id', this.salesChannel.measurementSystemId));
            }

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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.defaultMeasurementSystem = {
                measurementSystemId: this.salesChannel.measurementSystemId,
                lengthUnitId: this.salesChannel.lengthUnitId,
                weightUnitId: this.salesChannel.weightUnitId,
            };
        },

        onMeasurementSystemChange(measurementSystemId) {
            const measurementSystemSelect = this.$refs.measurementSystemSelect;

            const measurementSystem = measurementSystemSelect?.results?.get(measurementSystemId);

            this.$emit('measurement-system-change', measurementSystemId, measurementSystem);

            // Update the measurementSystem property on the sales channel
            this.salesChannel.measurementSystem = measurementSystem;

            if (measurementSystemId === this.defaultMeasurementSystem.measurementSystemId) {
                this.salesChannel.lengthUnitId = this.defaultMeasurementSystem.lengthUnitId;
                this.salesChannel.weightUnitId = this.defaultMeasurementSystem.weightUnitId;

                return;
            }

            this.salesChannel.lengthUnitId = measurementSystem?.units?.filter((unit) => unit.type === 'length').first()?.id;

            this.salesChannel.weightUnitId = measurementSystem?.units?.filter((unit) => unit.type === 'weight').first()?.id;
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
