/**
 * @package services-settings
 */
import template from './sw-import-export-progress.html.twig';
import './sw-import-export-progress.scss';

/**
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    emits: ['process-start', 'process-start-dryrun'],

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
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },
};
