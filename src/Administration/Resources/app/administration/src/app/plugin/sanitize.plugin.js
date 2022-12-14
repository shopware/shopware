/**
 * @package admin
 */

const { warn } = Shopware.Utils.debug;
const Sanitizer = Shopware.Helper.SanitizerHelper;

let pluginInstalled = false;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    install(Vue) {
        if (pluginInstalled) {
            warn('Sanitize Plugin', 'This plugin is already installed');
            return false;
        }

        Vue.prototype.$sanitizer = Sanitizer;
        Vue.prototype.$sanitize = Sanitizer.sanitize;

        pluginInstalled = true;

        return true;
    },
};
