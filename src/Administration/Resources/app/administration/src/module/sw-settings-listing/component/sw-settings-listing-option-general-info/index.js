import template from './sw-settings-listing-option-general-info.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    model: {
        prop: 'sortingOption',
        event: 'input',
    },

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
