/**
 * @package admin
 */

const { warn } = Shopware.Utils.debug;
const { DeviceHelper } = Shopware.Helper;

let pluginInstalled = false;

/**
 * @private
 */
export default {
    install(app) {
        if (pluginInstalled) {
            warn('DeviceHelper', 'This plugin is already installed');
            return false;
        }

        const deviceHelper = new DeviceHelper();

        Object.defineProperties(app.config.globalProperties, {
            $device: {
                get() {
                    return deviceHelper;
                },
            },
        });

        app.mixin({
            unmounted() {
                this.$device.removeResizeListener(this);
            },
        });

        pluginInstalled = true;

        return true;
    },
};
