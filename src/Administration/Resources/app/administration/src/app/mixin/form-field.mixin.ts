/**
 * @package admin
 */

import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default Shopware.Mixin.register('sw-form-field', defineComponent({
    inject: ['feature'],

    data() {
        return {
            inheritanceAttrs: {},
        };
    },

    props: {
        mapInheritance: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            type: Object as PropType<any>,
            required: false,
            default: null,
        },
    },

    computed: {
        /**
         * @deprecated tag:v6.6.0 - Will be removed
         */
        // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-redundant-type-constituents
        boundExpression(): null|any {
            // @ts-expect-error - we check if model exists on vnode
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (this.$vnode?.data?.model && this.$vnode?.data?.model?.expression) {
                // @ts-expect-error - we check if model exists on vnode
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return
                return this.$vnode.data.model.expression;
            }
            return null;
        },

        formFieldName(): string|null {
            if (this.$attrs.name) {
                return this.$attrs.name as string;
            }

            // @ts-expect-error - name exists on main component
            if (this.name) {
                // @ts-expect-error - name exists on main component
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return this.name;
            }

            /**
             * @deprecated tag:v6.6.0 - If statement will be removed
             */
            if (this.boundExpression) {
                // @ts-expect-error - we check if the value exists in boundExpression
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/restrict-template-expressions
                return `sw-field--${this.$vnode?.data?.model?.expression.replace(/\./g, '-')}`;
            }

            return null;
        },
    },

    watch: {
        mapInheritance: {
            handler(mapInheritance) {
                if (!mapInheritance) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                if (!mapInheritance?.isInheritField) {
                    return;
                }

                // set event listener and attributes for inheritance
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                Object.keys(mapInheritance).forEach((prop) => {
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                    const propValue = mapInheritance[prop];

                    if (typeof propValue === 'function') {
                        this.setFunctionsForEvents(prop, propValue as () => void);
                    } else if (typeof propValue === 'boolean') {
                        this.setAttributesForProps(prop, propValue);
                    }
                });
            },
            deep: true,
            immediate: true,
        },
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        beforeDestroyComponent() {
            // remove event listener
            this.$off('inheritance-restore');
            this.$off('inheritance-remove');
        },

        setFunctionsForEvents(prop: string, propValue: () => void) {
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

        setAttributesForProps(prop: string, propValue: boolean) {
            if (this.feature.isActive('VUE3')) {
                switch (prop) {
                    case 'isInherited': {
                        this.inheritanceAttrs = {
                            ...this.inheritanceAttrs,
                            [prop]: propValue,
                        };
                        break;
                    }

                    case 'isInheritField': {
                        this.inheritanceAttrs = {
                            ...this.inheritanceAttrs,
                            isInheritanceField: propValue,
                        };
                        break;
                    }

                    default: {
                        break;
                    }
                }

                return;
            }

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
        },
    },
}));

