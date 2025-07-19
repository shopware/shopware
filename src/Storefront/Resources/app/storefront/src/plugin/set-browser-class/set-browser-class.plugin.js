import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * @sw-package framework
 */
export default class SetBrowserClassPlugin extends Plugin {

    init() {
        this._browserDetection();
    }

    /**
     * Detects the browser type and adds specific css classes to the html element.
     */
    _browserDetection() {
        const detections = DeviceDetection.getList();

        for (const [key, value] of Object.entries(detections)) {
            if (value) {
                document.documentElement.classList.add(key);
            }
        }
    }
}
