import './sw-login-v2-access-denied.scss';
import template from './sw-login-v2-access-denied.html.twig';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data() {
        return {
            email: 'j.johnson@company.com',
        };
    },
});
