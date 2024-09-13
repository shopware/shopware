/**
 * @package admin
 */

const { warn } = Shopware.Utils.debug;
const Sanitizer = Shopware.Helper.SanitizerHelper;

let pluginInstalled = false;

/**
 * @private
 */
export default {
    install(app) {
        if (pluginInstalled) {
            warn('Sanitize Plugin', 'This plugin is already installed');
            return false;
        }

        app.config.globalProperties.$sanitizer = Sanitizer;
        app.config.globalProperties.$sanitize = Sanitizer.sanitize;

        pluginInstalled = true;

        return true;
    },
};
