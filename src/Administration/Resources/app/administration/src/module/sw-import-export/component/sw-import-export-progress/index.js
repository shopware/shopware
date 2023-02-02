/**
 * @package system-settings
 */
import template from './sw-import-export-progress.html.twig';
import './sw-import-export-progress.scss';

/**
 * @private
 */
export default {
    template,

    inject: ['feature'],

    props: {
        activityType: {
            type: String,
            required: false,
            default: 'import',
            validValues: [
                'import',
                'export',
            ],
            validator(value) {
                return [
                    'import',
                    'export',
                ].includes(value);
            },
        },

        disableButton: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },
};
