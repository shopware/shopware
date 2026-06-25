import './sw-login-access-denied-v2.scss';
import template from './sw-login-access-denied-v2.html.twig';

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
