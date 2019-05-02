import Plugin from 'src/script/helper/plugin/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import OffCanvas from 'src/script/plugin/offcanvas/offcanvas.plugin';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';

export default class OffCanvasAccountMenu extends Plugin{

    static options = {


        /**
         * selector for the dropdown menu content which is inserted into the offcanvas
         */
        dropdownMenuSelector: 'js-account-widget-dropdown',

        /**
         * additional class for the offcanvas
         */
        additionalClass: 'offcanvas-account-menu',

        /**
         * from which direction the
         * offcanvas opens
         */
        offcanvasPostion: 'left',
    };

    init() {
        this._registerEventListeners();
    }

    /**
   * Register events to handle opening the account menu OffCanvas
   * by clicking a defined trigger selector
   * @private
   */
    _registerEventListeners() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        this.el.addEventListener(event, this._onClickAccountMenuTrigger.bind(this, this.el));

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

        const html = DomAccess.querySelector(trigger.parentNode, `.${this.options.dropdownMenuSelector}`);

        OffCanvas.open(html.innerHTML, null, this.options.offcanvasPostion, true, OffCanvas.REMOVE_OFF_CANVAS_DELAY());
        OffCanvas.setAdditionalClassName(this.options.additionalClass);
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
