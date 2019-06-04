import { Mixin } from 'src/core/shopware';

Mixin.register('sw-form-field', {
    props: {
        errorPointer: {
            type: String,
            required: false,
            default: null
        },

        mapInheritance: {
            type: Object,
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
        }
    },

    watch: {
        mapInheritance: {
            handler(mapInheritance) {
                if (!mapInheritance) {
                    return;
                }

                if (!mapInheritance.isInheritField) {
                    return;
                }

                // set event listener and attributes for inheritance
                Object.keys(mapInheritance).forEach((prop) => {
                    const propValue = mapInheritance[prop];

                    if (typeof propValue === 'function') {
                        this.setFunctionsForEvents(prop, propValue);
                    } else if (typeof propValue === 'boolean') {
                        this.setAttributesForProps(prop, propValue);
                    }
                });
            },
            deep: true,
            immediate: true
        }
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        beforeDestroyComponent() {
            if (this.pointer) {
                this.$store.dispatch('deleteFieldError', this.pointer);
            }

            // remove event listener
            this.$off('inheritance-restore');
            this.$off('inheritance-remove');
        },

        resetFormError() {
            if (this.actualError && this.actualError.code !== 0 && !!this.pointer) {
                this.$store.dispatch('resetFormError', this.pointer);
            }
        },

        setFunctionsForEvents(prop, propValue) {
            switch (prop) {
                case 'restoreInheritance': {
                    this.$off('inheritance-restore');
                    this.$on('inheritance-restore', propValue);
                    break;
                }

                case 'removeInheritance': {
                    this.$off('inheritance-remove');
                    this.$on('inheritance-remove', propValue);
                    break;
                }

                default: {
                    break;
                }
            }
        },

        setAttributesForProps(prop, propValue) {
            switch (prop) {
                case 'isInherited': {
                    this.$set(this.$attrs, prop, propValue);
                    break;
                }

                case 'isInheritField': {
                    this.$set(this.$attrs, 'isInheritanceField', propValue);
                    break;
                }

                default: {
                    break;
                }
            }
        }
    }
});
