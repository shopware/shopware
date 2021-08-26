import template from './sw-import-export-progress.html.twig';
import './sw-import-export-progress.scss';

/**
 * @private
 */
Shopware.Component.register('sw-import-export-progress', {
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
            default: true,
        },
    },
});
