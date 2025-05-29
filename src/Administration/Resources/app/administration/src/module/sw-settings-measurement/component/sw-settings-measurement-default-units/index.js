/**
 * @sw-package inventory
 */
import template from './sw-settings-measurement-default-units.html.twig';
import './sw-settings-measurement-default-units.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['measurement-system-change'],

    props: {
        measurementSystem: {
            type: Object,
            required: true,
        },

        measurementUnits: {
            type: Object,
            required: true,
        },

        measurementSystemCriteria: {
            type: Object,
            required: true,
        },
    },

    computed: {
        lengthUnits() {
            return (this.measurementSystem?.units || []).filter((unit) => unit.type === 'length');
        },

        weightUnits() {
            return (this.measurementSystem?.units || []).filter((unit) => unit.type === 'weight');
        },

        measurementUnitId: {
            get() {
                if (!this.measurementSystem?.id) {
                    return null;
                }

                return this.measurementSystem.id;
            },

            set(value) {
                this.measurementSystem.id = value;
            },
        },
    },

    methods: {
        onChangeMeasurementSystem(_, measurement) {
            this.$emit('measurement-system-change', measurement);
        },

        labelUnitCallback(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const shortName = item.shortName || item.name;

            return `${name} (${shortName})`.trim();
        },
    },
};
