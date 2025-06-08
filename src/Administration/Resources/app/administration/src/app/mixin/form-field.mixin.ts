/**
 * @sw-package framework
 */

import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/**
 * @private
 */
export default Shopware.Mixin.register(
    'sw-form-field',
    defineComponent({
        inject: ['feature'],

        data() {
            return {
                inheritanceAttrs: {},
            };
        },

        provide() {
            return {
                // eslint-disable-next-line @typescript-eslint/unbound-method
                restoreInheritanceHandler: this.handleRestoreInheritance,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                removeInheritanceHandler: this.handleRemoveInheritance,
            };
        },

        props: {
            name: {
                type: String,
                required: false,
                default: null,
            },

            mapInheritance: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                type: Object as PropType<any>,
                required: false,
                default: null,
            },
        },

        computed: {
            formFieldName(): string | null {
                if (this.$attrs.name) {
                    return this.$attrs.name as string;
                }

                if (this.name) {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                    return this.name;
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

                        if (typeof propValue === 'boolean') {
                            this.setAttributesForProps(prop, propValue);
                        }
                    });
                },
                deep: true,
                immediate: true,
            },
        },

        beforeUnmount() {
            this.beforeDestroyComponent();
        },

        methods: {
            handleRestoreInheritance() {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                if (!this.mapInheritance?.restoreInheritance) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                this.mapInheritance.restoreInheritance();
            },

            handleRemoveInheritance() {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                if (!this.mapInheritance?.removeInheritance) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                this.mapInheritance.removeInheritance();
            },

            beforeDestroyComponent() {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            },

            setAttributesForProps(prop: string, propValue: boolean) {
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
            },
        },
    }),
);
