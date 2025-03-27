import template from './sw-settings-measurement-default-units.html.twig';
import './sw-settings-measurement-default-units.scss';

/**
 * @sw-package inventory
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    emits: [
        'measurement-system-config-type-change',
    ],

    props: {
        measurementSystemConfig: {
            type: Object,
            required: true,
        },

        measurementSystemOptions: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onMeasurementSystemConfigTypeChange() {
            this.$emit('measurement-system-config-type-change');
        },
    },
};
