import template from './sw-field-error.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-field-error',
    template,

    props: {
        formError: {
            type: Object,
            required: false
        },
        errorMessage: {
            type: String,
            required: false
        }
    }
};
