import './sw-login-v2.scss';
import template from './sw-login-v2.html.twig';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data() {
        return {
            isLoading: false,
        };
    },
});
