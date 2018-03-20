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
            let valid = true;

            if (typeof this.validation === 'string') {
                valid = this.validateRule(value, this.validation);
            }

            if (types.isArray(this.validation)) {
                valid = this.validation.every((validationRule) => {
                    return this.validateRule(value, validationRule);
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
