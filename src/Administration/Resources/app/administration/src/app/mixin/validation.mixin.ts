/* @private */
export {};

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @module app/mixin/validation
 */
Shopware.Mixin.register('validation', {
    inject: ['validationService'],

    props: {
        validation: {
            type: [String, Array, Object, Boolean],
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
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            let validation = this.validation;
            let valid = true;

            if (Shopware.Utils.types.isBoolean(validation)) {
                return validation;
            }

            if (Shopware.Utils.types.isString(validation)) {
                const validationList = validation.split(',');

                if (validationList.length > 1) {
                    validation = validationList;
                } else {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-argument
                    valid = this.validateRule(value, this.validation);
                }
            }

            if (Shopware.Utils.types.isArray(validation)) {
                valid = validation.every((validationRule) => {
                    if (Shopware.Utils.types.isBoolean(validationRule)) {
                        return validationRule;
                    }

                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-argument
                    return this.validateRule(value, validationRule.trim());
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

            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return
            return this.validationService[rule](value);
        },
    },
});
