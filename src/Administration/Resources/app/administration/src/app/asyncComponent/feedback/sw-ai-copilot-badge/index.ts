import template from './sw-ai-copilot-badge.html.twig';
import './sw-ai-copilot-badge.scss';

/**
 * @package admin
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        label: {
            type: Boolean,
            required: false,
            default: true,
        },
    },
});
