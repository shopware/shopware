/**
 * @sw-package inventory
 */

import template from './sw-sales-channel-measurement.html.twig';
import './sw-sales-channel-measurement.scss';

const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

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

    inject: [
        'repositoryFactory',
    ],

    emits: [
        'measurement-system-change',
    ],

    data() {
        return {
            defaultMeasurementSystem: null,
            defaultDisplayUnits: [],
        };
    },

    computed: {
        measurementSystemRepository() {
            return this.repositoryFactory.create('measurement_system');
        },

        measurementSystemCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addAssociation('units');

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

        measurementUnits() {
            return this.salesChannel.measurementUnits;
        },

        lengthUnits() {
            return (this.defaultMeasurementSystem?.units || []).filter((unit) => unit.type === 'length');
        },

        weightUnits() {
            return (this.defaultMeasurementSystem?.units || []).filter((unit) => unit.type === 'weight');
        },

        defaultLengthUnit() {
            return this.defaultDisplayUnits.find((u) => u.type === 'length');
        },

        defaultWeightUnit() {
            return this.defaultDisplayUnits.find((u) => u.type === 'weight');
        },

        measurementUnitId: {
            get() {
                if (!this.defaultMeasurementSystem?.id) {
                    return null;
                }

                return this.defaultMeasurementSystem.id;
            },

            set(value) {
                this.defaultMeasurementSystem.id = value;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.defaultMeasurementSystem = await this.getDefaultMeasurementSystem();
            this.defaultDisplayUnits = (this.defaultMeasurementSystem?.units || []).filter((u) =>
                Object.values(this.measurementUnits.units).includes(u.shortName),
            );
        },

        async onMeasurementSystemChange(_, measurementSystem) {
            if (!measurementSystem) {
                return;
            }

            this.measurementUnits.name = measurementSystem.technicalName;
            const units = measurementSystem.units;

            this.defaultMeasurementSystem = measurementSystem;

            const defaultLengthUnit =
                units.find((unit) => unit.shortName === this.defaultLengthUnit.shortName) ||
                units.find((unit) => unit.type === 'length' && unit.default);

            if (defaultLengthUnit) {
                this.measurementUnits.units.length = defaultLengthUnit.shortName;
            }

            const defaultWeightUnit =
                units.find((unit) => unit.shortName === this.defaultWeightUnit.shortName) ||
                units.find((unit) => unit.type === 'weight' && unit.default);

            if (defaultWeightUnit) {
                this.measurementUnits.units.weight = defaultWeightUnit.shortName;
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

        async getDefaultMeasurementSystem() {
            const criteria = cloneDeep(this.measurementSystemCriteria);
            criteria.setLimit(1);

            if (this.measurementUnits.name) {
                criteria.addFilter(Criteria.equals('technicalName', this.measurementUnits.name));
            }

            const measurement = await this.measurementSystemRepository.search(criteria);

            return measurement?.first();
        },
    },
});
