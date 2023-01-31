import template from './sw-cms-visibility-config.html';
import './sw-cms-visibility-config.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    props: {
        visibility: {
            type: Object,
            required: true,
        },
    },
};
