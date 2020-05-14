import DomAccess from 'src/helper/dom-access.helper';
import DeviceDetection from 'src/helper/device-detection.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import OffcanvasPlugin from '../offcanvas/offcanvas.plugin';

export default class OffCanvasAccountMenu extends OffcanvasPlugin {

    static options = {
        /**
         * selector for the dropdown menu content which is inserted into the offcanvas
         */
        dropdownMenuSelector: 'js-account-menu-dropdown',

        /**
         * additional class for the offcanvas
         */
        additionalClass: 'account-menu-offcanvas',

        /**
         * class is used to hide the dropdown on viewports where the offcanvas is used
         */
        hiddenClass: 'd-none',

        /**
         * from which direction the
         * offcanvas opens
         */
        offcanvasPostion: 'right'
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

        document.addEventListener('Viewport/hasChanged', this._onViewportHasChanged.bind(this));
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

        this._dropdown = DomAccess.querySelector(trigger.parentNode, `.${this.options.dropdownMenuSelector}`);

        this._dropdown.classList.add(this.options.hiddenClass);

        this.open(this._dropdown.innerHTML, null, this.options.offcanvasPostion, true);
        this.setAdditionalClassName(this.options.additionalClass);

        this.$emitter.publish('onClickAccountMenuTrigger');
    }

    /**
     * If viewport has changed verify whether to close
     * an already open account menu offcanvas/dropwdown or not
     * @private
     */
    _onViewportHasChanged() {
        if (this._isInAllowedViewports() === false && this.exists()) {
            this.close();
        }

        if (this._dropdown) {
            if (this._isInAllowedViewports() === false) {
                this._dropdown.classList.remove(this.options.hiddenClass);
            } else {
                this._dropdown.classList.add(this.options.hiddenClass);
            }
        }

        this.$emitter.publish('onViewportHasChanged');
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
