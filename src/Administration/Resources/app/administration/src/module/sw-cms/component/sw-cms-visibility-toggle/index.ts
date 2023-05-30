import template from './sw-cms-visibility-toggle.html';
import './sw-cms-visibility-toggle.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    props: {
        text: {
            type: String,
            required: true,
        },
        isCollapsed: {
            type: Boolean,
            required: true,
        },
    },
};
