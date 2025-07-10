/**
 * @sw-package inventory
 */
import template from './sw-settings-measurement-default-units.html.twig';
import './sw-settings-measurement-default-units.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    emits: ['measurement-system-change'],

    props: {
        measurementSystems: {
            type: Array,
            required: true,
        },

        measurementSystem: {
            type: Object,
            required: true,
        },

        measurementUnits: {
            type: Object,
            required: true,
        },
    },

    computed: {
        lengthUnitOptions() {
            return this.getUnitOptionsByType('length');
        },

        weightUnitOptions() {
            return this.getUnitOptionsByType('weight');
        },

        measurementSystemOptions() {
            return this.measurementSystems.map((system) => ({
                ...system,
                label: this.$t(`sw-settings-measurement.defaultUnits.technicalName.${system.technicalName}`),
                value: system.technicalName,
            }));
        },

        measurementUnitSystemError() {
            if (!this.measurementSystem?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.measurementSystem, 'system');
        },

        measurementLengthUnitError() {
            if (!this.measurementSystem?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.measurementSystem, 'length');
        },

        measurementWeightUnitError() {
            if (!this.measurementSystem?.id) {
                return null;
            }

            return Shopware.Store.get('error').getApiError(this.measurementSystem, 'weight');
        },
    },

    methods: {
        onChangeMeasurementSystem(technicalName) {
            this.$emit('measurement-system-change', technicalName);
        },

        getUnitLabel(item) {
            return `${this.$t(`sw-settings-measurement.defaultUnits.shortName.${item.shortName}`)} (${item.shortName})`;
        },

        getUnitOptionsByType(type) {
            return (this.measurementSystem?.units || [])
                .filter((unit) => unit.type === type)
                .map((unit) => ({
                    ...unit,
                    label: this.getUnitLabel(unit),
                    value: unit.shortName,
                }));
        },
    },
};
