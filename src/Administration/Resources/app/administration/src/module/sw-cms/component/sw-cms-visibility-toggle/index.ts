import template from './sw-cms-visibility-toggle.html.twig';
import './sw-cms-visibility-toggle.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
