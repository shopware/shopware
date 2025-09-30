import Plugin from 'src/plugin-system/plugin.class';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import ViewportDetection from 'src/helper/viewport-detection.helper';

export default class OffCanvasAccountMenu extends Plugin {

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
         * from which direction the offcanvas opens
         */
        offcanvasPostion: 'left',
    };

    init() {
        this._dropdown = this.el.parentNode.querySelector(`.${this.options.dropdownMenuSelector}`);
        this._dropdownWrapper = this.el.parentNode;

        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the account menu OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {
        this.el.addEventListener('click', this._onClickAccountMenuTrigger.bind(this, this.el));
        this._dropdownWrapper.addEventListener('show.bs.dropdown', this._onClickPreventDropdown.bind(this));

        document.addEventListener('Viewport/hasChanged', this._onViewportHasChanged.bind(this));
    }

    /**
     * On clicking the trigger item the account menu OffCanvas shall open
     * and the dropdown content may be fetched and shown inside the OffCanvas.
     * @private
     */
    _onClickAccountMenuTrigger() {
        // if the current viewport is not allowed return
        if (this._isInAllowedViewports() === false) {
            return;
        }

        OffCanvas.open(this._dropdown.innerHTML, null, this.options.offcanvasPostion, true, OffCanvas.REMOVE_OFF_CANVAS_DELAY());
        OffCanvas.setAdditionalClassName(this.options.additionalClass);

        this.$emitter.publish('onClickAccountMenuTrigger');
    }

    /**
     * Prevent opening the Bootstrap dropdown in allowed viewports
     *
     * @param event
     * @private
     */
    _onClickPreventDropdown(event) {
        if (this._isInAllowedViewports() === true) {
            event.preventDefault();
        }
    }

    /**
     * If viewport has changed verify whether to close
     * an already open account menu offcanvas/dropwdown or not
     * @private
     */
    _onViewportHasChanged() {
        if (
            this._isInAllowedViewports() === false
            && OffCanvas.exists()
            && OffCanvas.getOffCanvas()[0].classList.contains(this.options.additionalClass)
        ) {
            OffCanvas.close();
        }

        if (this._dropdown) {
            const bsDropdownInstance = bootstrap.Dropdown.getInstance(this.el);

            if (this._isInAllowedViewports() === true) {
                if (bsDropdownInstance) {
                    bsDropdownInstance.hide();
                }
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
