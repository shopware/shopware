import template from './sw-field-error.html.twig';
import './sw-field-error.scss';
import messages from './error-codes.json';

/**
 * @private
 */
export default {
    name: 'sw-field-error',
    template,

    i18n: {
        messages
    },

    props: {
        error: {
            type: Object,
            required: false,
            default: null
        }
    }
};
