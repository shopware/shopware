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
        snippetKey() {
            if (!this.error) {
                return null;
            }
            return `global.error-codes.${this.error.code}`;
        },

        errorMessage() {
            if (this.$te(this.$i18n.fallbackLocale, this.snippetKey)) {
                return this.$t(this.snippetKey, this.error.parameters);
            }
            return this.error.detail;
        }
    }
};
