/* @private */
import { defineComponent } from 'vue';
import { types } from 'shopware:utils';

/**
 * @private
 * @sw-package framework
 *
 * @module app/mixin/validation
 *
 * Duplicated in `src/app/composables/use-validation`; change both together.
 */
export default Shopware.Mixin.register(
    'validation',
    defineComponent({
        inject: {
            validationService: {
                type: Object,
                required: false,
                default: null,
            },
        },

        props: {
            validation: {
                type: [
                    String,
                    Array,
                    Object,
                    Boolean,
                ],
                required: false,
                default: null,
            },
        },

        computed: {
            isValid(): boolean {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                const value = this.currentValue || this.value || this.selections;

                return this.validate(value);
            },
        },

        methods: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            validate(value: any) {
                let validation = this.validation;
                let valid = true;

                if (types.isBoolean(validation)) {
                    return validation;
                }

                if (types.isString(validation)) {
                    const validationList = validation.split(',');

                    if (validationList.length > 1) {
                        validation = validationList;
                    } else {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        valid = this.validateRule(value, this.validation as string);
                    }
                }

                if (types.isArray(validation)) {
                    valid = validation.every((validationRule) => {
                        if (types.isBoolean(validationRule)) {
                            return validationRule;
                        }

                        if (types.isString(validationRule)) {
                            return this.validateRule(value, validationRule.trim());
                        }

                        return false;
                    });
                }

                return valid;
            },

            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            validateRule(value: any, rule: string) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                if (typeof this.validationService[rule] === 'undefined') {
                    return false;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return
                return this.validationService[rule](value);
            },
        },
    }),
);
