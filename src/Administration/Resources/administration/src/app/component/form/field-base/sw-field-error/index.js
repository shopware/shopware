import template from './sw-field-error.html.twig';
import './sw-field-error.scss';

/**
 * @private
 */
export default {
    name: 'sw-field-error',
    template,

    props: {
        error: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        errorMessage() {
            if (!this.error) {
                return '';
            }
            const translationKey = `global.error-codes.${this.error.code}`;
            const translation = this.$tc(translationKey);

            if (translation === translationKey) {
                return this.error.detail;
            }
            return translation;
        }
    }
};
