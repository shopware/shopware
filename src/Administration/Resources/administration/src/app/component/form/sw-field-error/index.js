import template from './sw-field-error.html.twig';

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
