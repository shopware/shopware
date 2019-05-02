import DomAccess from 'src/script/helper/dom-access.helper';
import OffCanvas from 'src/script/plugin/offcanvas/offcanvas.plugin';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';
import Plugin from 'src/script/helper/plugin/plugin.class';


export default class OffCanvasTabs extends Plugin {

    static options = {

        /**
         * from which direction the
         * offcanvas opens
         */
        offcanvasPostion: 'right',
    };

    init() {
        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the Detail Tab OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {
        this.el.addEventListener('click', this._onClickOffCanvasTab.bind(this));
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
            OffCanvas.open(pane.innerHTML, null, this.options.offcanvasPostion, true, OffCanvas.REMOVE_OFF_CANVAS_DELAY(), true);
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
