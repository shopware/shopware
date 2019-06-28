import Plugin from 'src/script/plugin-system/plugin.class';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import Iterator from 'src/script/helper/iterator.helper';

export default class SetBrowserClassPlugin extends Plugin {

    init() {
        this._browserDetection();
    }

    /**
     * Detects the browser type and adds specific css classes to the html element.
     */
    _browserDetection() {
        const detections = DeviceDetection.getList();

        Iterator.iterate(detections, function(value, key) {
            if (value) {
                return document.documentElement.classList.add(key);
            }
        });
    }
}