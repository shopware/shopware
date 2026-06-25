import './sw-login-v2-credentials.scss';
import template from './sw-login-v2-credentials.html.twig';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data() {
        return {
            error: false,
            warning: false,
            showSso: true,
        };
    },
});
