import './sw-login-recovery-v2.scss';
import template from './sw-login-recovery-v2.html.twig';

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
