import DomAccess from "../../helper/dom-access.helper";
import OffCanvas from "../../plugin/off-canvas/offcanvas.plugin";
import DeviceDetection from "../../helper/device-detection.helper";
import ViewportDetection from "../../helper/viewport-detection.helper";

const OFFCANVAS_ACCOUNT_MENU_CLASS = 'account-widget-dropdown';
const OFFCANVAS_ACCOUNT_MENU_DATA_ATTRIBUTE = 'data-offcanvas-account-menu';
const OFFCANVAS_ACCOUNT_MENU_DATA_POSITION = 'left';


export default class OffCanvasAccountMenu {

    init() {
        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the account menu OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        let trigger = DomAccess.querySelector(document, `*[${OFFCANVAS_ACCOUNT_MENU_DATA_ATTRIBUTE}=true]`);
        trigger.addEventListener(event, this._onClickAccountMenuTrigger.bind(this, trigger));

        document.addEventListener(ViewportDetection.EVENT_VIEWPORT_HAS_CHANGED(), this._onViewportHasChanged.bind(this));
    }


    /**
     * On clicking the trigger item the account menu OffCanvas shall open
     * and the dropdown content may be fetched and shown inside the OffCanvas.
     * @param trigger
     * @private
     */
    _onClickAccountMenuTrigger(trigger) {

        // if the current viewport is not allowed return
        if (this._isInAllowedViewports() === false) return;

        let html = DomAccess.querySelector(trigger.parentNode, `.${OFFCANVAS_ACCOUNT_MENU_CLASS}`);

        OffCanvas.open(html.innerHTML, null, OFFCANVAS_ACCOUNT_MENU_DATA_POSITION, true, OffCanvas.REMOVE_OFF_CANVAS_DELAY());
    }

    /**
     * If viewport has changed verify whether to close
     * an already open account menu offcanvas or not
     * @private
     */
    _onViewportHasChanged() {
        if (this._isInAllowedViewports() === false && OffCanvas.exists()) {
            OffCanvas.close();
        }
    }

    /**
     * Returns if the browser is in the allowed viewports
     * @returns {boolean}
     * @private
     */
    _isInAllowedViewports() {
        return (ViewportDetection.isXS() || ViewportDetection.isSM());
    }

}
