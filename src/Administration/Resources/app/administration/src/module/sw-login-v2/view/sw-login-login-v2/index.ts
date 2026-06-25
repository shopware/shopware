import './sw-login-login-v2.scss';
import template from './sw-login-login-v2.html.twig';

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
