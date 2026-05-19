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

    inject: [
        'repositoryFactory',
        'acl',
    ],

    emits: [
        'measurement-system-change',
    ],

    data() {
        return {
            defaultMeasurementSystem: null,
            defaultDisplayUnits: [],
            measurementSystems: [],
            measurementSystem: null,
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

        measurementSystemOptions() {
            return this.measurementSystems.map((system) => ({
                ...system,
                label: system.translated?.name || system.name,
                value: system.technicalName,
            }));
        },

        lengthUnitOptions() {
            return this.getUnitOptionsByType('length');
        },

        weightUnitOptions() {
            return this.getUnitOptionsByType('weight');
        },

        defaultLengthUnit() {
            const units = this.measurementSystem?.units || [];

            const lengthUnit = this.defaultDisplayUnits.find((u) => u.type === 'length');
            const defaultLengthUnit = units.find((unit) => unit.type === 'length' && unit.default);

            return units.find((unit) => unit.shortName === lengthUnit.shortName) || defaultLengthUnit;
        },

        defaultWeightUnit() {
            const units = this.measurementSystem?.units || [];

            const weightUnit = this.defaultDisplayUnits.find((u) => u.type === 'weight');
            const defaultWeightUnit = units.find((unit) => unit.type === 'weight' && unit.default);

            return units.find((unit) => unit.shortName === weightUnit.shortName) || defaultWeightUnit;
        },

        measurementUnitSystemError() {
            if (!this.salesChannel?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.salesChannel, 'measurementUnits.system');
        },

        measurementLengthUnitError() {
            if (!this.salesChannel?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.salesChannel, 'measurementUnits.units.length');
        },

        measurementWeightUnitError() {
            if (!this.salesChannel?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.salesChannel, 'measurementUnits.units.weight');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.measurementSystems = await this.getDefaultMeasurementSystems();
            this.measurementSystem = this.measurementSystems.find(
                (system) => system.technicalName === this.measurementUnits.system,
            );

            this.defaultDisplayUnits = (this.measurementSystem?.units || []).filter((u) =>
                Object.values(this.measurementUnits.units).includes(u.shortName),
            );
        },

        async onMeasurementSystemChange(technicalName) {
            if ((Array.isArray(technicalName) && technicalName.length === 0) || !technicalName) {
                return;
            }

            this.measurementSystem = this.measurementSystems.find((system) => system.technicalName === technicalName);

            this.measurementUnits.units.length = this.defaultLengthUnit.shortName;

            this.measurementUnits.units.weight = this.defaultWeightUnit.shortName;
        },

        formatUnitLabel(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },

        getDefaultMeasurementSystems() {
            return this.measurementSystemRepository.search(this.measurementSystemCriteria);
        },

        getUnitOptionsByType(type) {
            return (this.measurementSystem?.units || [])
                .filter((unit) => unit.type === type)
                .map((unit) => ({
                    ...unit,
                    label: this.formatUnitLabel(unit),
                    value: unit.shortName,
                }));
        },
    },
});
