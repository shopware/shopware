import template from './sw-settings-measurement-default-units.html.twig';
import './sw-settings-measurement-default-units.scss';

/**
 * @sw-package inventory
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['measurement-system-change'],

    props: {
        measurementSystem: {
            type: Object,
            required: true,
        },
        lengthUnitCriteria: {
            type: Object,
            required: true,
        },
        massUnitCriteria: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onChangeMeasurementSystem() {
            this.$emit('measurement-system-change');
        },

        labelSystemCallback(item) {
            if (!item) {
                return '';
            }

            const name = item.translated?.name || item.name;
            const systemLabel = this.$t('sw-settings-measurement.defaultUnits.system');

            return `${name} ${systemLabel}`.trim();
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
