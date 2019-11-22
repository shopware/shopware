const { warn } = Shopware.Utils.debug;
const Sanitizer = Shopware.Helper.SanitizerHelper;

let pluginInstalled = false;

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
    }
};
