/**
 * @sw-package after-sales
 */

import template from './sw-sso-error-index.html.twig';
import './sw-sso-error-index.scss';

/**
 * @private
 */
export default {
    template,

    inject: [
        'loginService',
    ],

    data() {
        return {
            loginConfig: {},
            loginConfigLoaded: false,
        };
    },

    created() {
        this.loginService.getLoginTemplateConfig().then((loginConfig) => {
            this.loginConfig = loginConfig;
            this.loginConfigLoaded = true;
        });
    },

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        isLoading() {
            return !this.loginConfigLoaded;
        },

        url() {
            if (!this.loginConfigLoaded) {
                return '';
            }

            return this.loginConfig.url;
        },

        email() {
            return this.loginService.getStorage().getItem('user');
        },
    },
};
