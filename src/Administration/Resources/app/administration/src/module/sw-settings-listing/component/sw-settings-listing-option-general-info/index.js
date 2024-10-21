import template from './sw-settings-listing-option-general-info.html.twig';

/**
 * @package inventory
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        sortingOption: {
            type: Object,
            required: true,
        },

        isDefaultSorting: {
            type: Boolean,
            required: true,
        },

        technicalNameError: {
            type: Object,
            required: false,
            default: {},
        },

        labelError: {
            type: Object,
            required: false,
            default: {},
        },
    },
};
