import template from './sw-field-addition.html.twig';

export default {
    name: 'sw-field-addition',
    template,

    props: {
        text: {
            type: String,
            required: false,
            default: ''
        },
        isPrefix: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        fieldClasses() {
            return {
                'is--prefix': !!this.isPrefix
            };
        }
    }
};
