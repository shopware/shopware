import $ from 'jquery';
import DomAccess from "../../helper/dom-access.helper";
import DeviceDetection from "../../helper/device-detection.helper";
import ViewportDetection from "../../helper/viewport-detection.helper";

const COLLAPSE_SHOW_CLASS = "show";

export default class CollapseFooterColumns {

    /**
     * Constructor.
     */
    constructor() {
        this._footer = DomAccess.querySelector(document, '#footerColumns');
        this._columns = this._footer.querySelectorAll('.footer-column');
        this._registerEvents();
    }

    /**
     * Register event listeners
     * @private
     */
    _registerEvents() {
        document.addEventListener(ViewportDetection.EVENT_VIEWPORT_HAS_CHANGED(), this._onViewportHasChanged.bind(this));
    }

    /**
     * If viewport has changed verify whether to add event listeners to the
     * column headlines for triggering collapse toggling or not
     * @private
     */
    _onViewportHasChanged() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this._columns.forEach((column) => {
            let trigger = DomAccess.querySelector(column, '.column-headline');

            // remove possibly existing event listeners
            trigger.removeEventListener(event, this._onClickCollapseTrigger);

            // add event listener if currently in an allowed viewport
            if (this._isInAllowedViewports()) {
                trigger.addEventListener(event, this._onClickCollapseTrigger);
            }
        });
    }

    /**
     * On clicking the collapse trigger (column headline) the columns
     * content area shall be toggled open/close
     * @param e
     * @private
     */
    _onClickCollapseTrigger(e) {
        let trigger = e.srcElement;
        let collapse = trigger.parentNode.querySelector('.column-content');

        $(collapse).collapse('toggle');

        $(collapse).on('shown.bs.collapse', function() {
            trigger.classList.add(COLLAPSE_SHOW_CLASS);
        });

        $(collapse).on('hidden.bs.collapse', function() {
            trigger.classList.remove(COLLAPSE_SHOW_CLASS);
        });
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