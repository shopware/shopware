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
            if (this.$attrs.name) {
                return this.$attrs.name;
            }

            if (this.name) {
                return this.name;
            }

            if (this.boundExpression) {
                return `sw-field--${this.$vnode.data.model.expression.replace(/\./g, '-')}`;
            }

            return null;
        },

        pointer() {
            return this.errorPointer || this.boundExpression;
        },

        actualError() {
            return this.$store.getters.boundError(this.pointer);
        },

        isInheritanceField() {
            return this.inheritedValue !== null;
        },

        isInherited() {
            return this.isInheritanceField && this.currentValue === null;
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
    },

    methods: {
        resetFormError() {
            if (this.actualError && this.actualError.code !== 0 && !!this.pointer) {
                this.$store.dispatch('resetFormError', this.pointer);
            }
        }
    }
});
