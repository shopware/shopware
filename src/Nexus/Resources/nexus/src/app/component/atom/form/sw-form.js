import ComponentFactory from 'src/core/factory/component.factory';
import template from 'src/app/component/atom/form/sw-form/sw-form.html.twig';

export default ComponentFactory.register('sw-form', {
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

