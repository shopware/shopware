import template from './sw-settings-listing-option-general-info.html.twig';

/**
 * @sw-package inventory
 */

/**
 * @public
 */
export default {
    template,

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
