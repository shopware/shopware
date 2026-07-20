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
                label: system.translated?.name || system.name,
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

        labelUnitCallback(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },

        getUnitOptionsByType(type) {
            return (this.measurementSystem?.units || [])
                .filter((unit) => unit.type === type)
                .map((unit) => ({
                    ...unit,
                    label: this.labelUnitCallback(unit),
                    value: unit.shortName,
                }));
        },
    },
};
