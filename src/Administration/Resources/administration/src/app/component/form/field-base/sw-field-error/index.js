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
    }
};
