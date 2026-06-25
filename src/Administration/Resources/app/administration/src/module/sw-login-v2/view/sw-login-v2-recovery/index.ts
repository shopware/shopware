import './sw-login-v2-recovery.scss';
import template from './sw-login-v2-recovery.html.twig';

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
        };
    },
});
