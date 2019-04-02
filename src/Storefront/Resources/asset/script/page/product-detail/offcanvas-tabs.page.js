import DomAccess from "../../helper/dom-access.helper";
import OffCanvas from "../../plugin/off-canvas/offcanvas.plugin";
import DeviceDetection from "../../helper/device-detection.helper";
import Plugin from "../../helper/plugin/plugin.class";

const OFFCANVAS_TAB_DATA_ATTRIBUTE = 'data-offcanvas-tab';
const OFFCANVAS_TAB_POSITION = 'right';

export default class OffcanvasTabs extends Plugin {

    init() {
        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the Detail Tab OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        document.addEventListener(event, (e) => {
            e.path.forEach(item => {
                if (DomAccess.hasAttribute(item, OFFCANVAS_TAB_DATA_ATTRIBUTE)) {
                    e.preventDefault();
                    this._onOpenOffcanvasTab(e);
                }
            });
        });
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * tab content may be fetched and shown inside the OffCanvas
     * @param {Event} e
     * @private
     */
    _onOpenOffcanvasTab(e) {
        let targetElement = e.target;

        if (DomAccess.hasAttribute(targetElement, 'href')) {
            let contentID = targetElement.getAttribute('href').replace(/^#/, '');
            let contentHTML = document.getElementById(contentID).innerHTML;

            e.preventDefault();
            OffCanvas.open(contentHTML, null, OFFCANVAS_TAB_POSITION, true, 0, true);
        }
    }

}
