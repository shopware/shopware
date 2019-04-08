import DomAccess from 'asset/script/helper/dom-access.helper';
import OffCanvas from 'asset/script/plugin/off-canvas/offcanvas.plugin';
import DeviceDetection from 'asset/script/helper/device-detection.helper';
import ViewportDetection from 'asset/script/helper/viewport-detection.helper';

const OFFCANVAS_TAB_DATA_ATTRIBUTE = 'data-offcanvas-tab';
const OFFCANVAS_TAB_POSITION = 'right';

export default class OffCanvasTabs {

    init() {
        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the Detail Tab OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {

        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        const tabs = document.querySelectorAll(`*[${OFFCANVAS_TAB_DATA_ATTRIBUTE}=true]`);

        tabs.forEach((tab) => {
            tab.addEventListener(event, this._onClickOffCanvasTab.bind(this));
        });
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * tab content may be fetched and shown inside the OffCanvas.
     * This only may happen in the defined valid viewports.
     * @param {Event} e
     * @private
     */
    _onClickOffCanvasTab(e) {

        // if the current viewport is not allowed return
        if (this._isInAllowedViewports() === false) return;

        e.preventDefault();
        const tab = e.target;

        if (DomAccess.hasAttribute(tab, 'href')) {
            const tabTarget = DomAccess.getAttribute(tab, 'href');
            const pane = DomAccess.querySelector(document, tabTarget);
            OffCanvas.open(pane.innerHTML, null, OFFCANVAS_TAB_POSITION, true, OffCanvas.REMOVE_OFF_CANVAS_DELAY(), true);
        }
    }

    /**
     * Returns if the browser is in the allowed viewports
     * @returns {boolean}
     * @private
     */
    _isInAllowedViewports() {
        return (ViewportDetection.isXS());
    }

}
