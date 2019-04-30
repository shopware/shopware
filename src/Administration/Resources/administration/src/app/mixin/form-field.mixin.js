import { Mixin } from 'src/core/shopware';

Mixin.register('sw-form-field', {
    props: {
        errorPointer: {
            type: String,
            required: false,
            default: null
        }
    },

    computed: {
        boundExpression() {
            if (this.$vnode.data.model && this.$vnode.data.model.expression) {
                return this.$vnode.data.model.expression;
            }
            return null;
        },

        formFieldName() {
            if (this.boundExpression) {
                return `sw-field--${this.$vnode.data.model.expression.replace(/\./g, '-')}`;
            }

            return this.$attrs.name || this.name;
        },

        pointer() {
            return this.errorPointer || this.boundExpression;
        },

        actualError() {
            return this.$store.getters.boundError(this.pointer);
        }
    },

    created() {
        if (this.pointer) {
            this.$store.dispatch('registerFormField', this.pointer);
        }
    },

    beforeDestroy() {
        if (this.pointer) {
            this.$store.dispatch('deleteFieldError', this.pointer);
        }
    }
});
