import template from './sw-form.html.twig';

Shopware.Component.register('sw-form', {
    props: {
        /**
         * "method" attribute for the form component.
         *
         * @type {Object}
         */
        method: {
            type: String,
            default: 'GET'
        },

        action: {
            type: String,
            default: '',
            required: true
        },

        ajax: {
            type: Boolean,
            default: true
        }
    },

    computed: {
        formMethod() {
            return this.method.toUpperCase();
        }
    },

    methods: {

        handleSubmit(e) {
            if (!this.ajax) {
                return;
            }
            e.preventDefault();

            const data = new FormData(this.$refs.form);
            this.$emit('submit-started', data);
        }
    },

    template
});

