const { Mixin } = Shopware;
const types = Shopware.Utils.types;

/**
 * @module app/mixin/validation
 */
Mixin.register('validation', {
    inject: ['validationService'],

    props: {
        validation: {
            type: [String, Array, Object, Boolean],
            required: false,
            default: null,
        },
    },

    computed: {
        isValid() {
            const value = this.currentValue || this.value || this.selections;

            return this.validate(value);
        },
    },

    methods: {
        validate(value) {
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
                    valid = this.validateRule(value, this.validation);
                }
            }

            if (types.isArray(validation)) {
                valid = validation.every((validationRule) => {
                    if (types.isBoolean(validationRule)) {
                        return validationRule;
                    }

                    return this.validateRule(value, validationRule.trim());
                });
            }

            return valid;
        },

        validateRule(value, rule) {
            if (typeof this.validationService[rule] === 'undefined') {
                return false;
            }

            return this.validationService[rule](value);
        },
    },
});
