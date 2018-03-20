import { Mixin } from 'src/core/shopware';
import { types } from 'src/core/service/util.service';

/**
 * @module app/mixin/validation
 */
Mixin.register('validation', {
    inject: ['validationService'],

    props: {
        validation: {
            type: [String, Array, Object],
            required: false,
            default: null
        }
    },

    computed: {
        isValid() {
            return this.validate(this.value);
        }
    },

    methods: {
        validate(value) {
            let validation = this.validation;
            let valid = true;

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
        }
    }
});
