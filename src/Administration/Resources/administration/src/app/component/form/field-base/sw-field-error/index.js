import ShopwareError from 'src/core/data/ShopwareError';
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
            default() {
                return new ShopwareError();
            }
        }
    }
};
