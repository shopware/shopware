import './sw-login-recovery-info-v2.scss';
import template from './sw-login-recovery-info-v2.html.twig';

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
