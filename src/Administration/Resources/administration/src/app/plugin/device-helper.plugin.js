import DeviceHelper from 'src/core/helper/device.helper';
import { warn } from 'src/core/service/utils/debug.utils';

let pluginInstalled = false;

export default {
    install(Vue) {
        if (pluginInstalled) {
            warn('DeviceHelper', 'This plugin is already installed');
            return false;
        }

        const deviceHelper = new DeviceHelper();

        Object.defineProperties(Vue.prototype, {
            $device: {
                get() {
                    return deviceHelper;
                }
            }
        });

        Vue.mixin({
            destroyed() {
                this.$device.removeResizeListener(this);
            }
        });

        pluginInstalled = true;

        return true;
    }
};
