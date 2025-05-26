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
        measurementUnits: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onChangeMeasurementSystem() {
            this.$emit('measurement-system-change');
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
