import Plugin from 'src/script/helper/plugin/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';
import Iterator from 'src/script/helper/iterator.helper';

const COLLAPSE_SHOW_CLASS = 'show';

const COLLAPSE_COLUMN_SELECTOR = '.js-footer-column';
const COLLAPSE_COLUMN_TRIGGER_SELECTOR = '.js-collapse-footer-column-trigger';
const COLLAPSE_COLUMN_CONTENT_SELECTOR = '.js-footer-column-content';

export default class CollapseFooterColumnsPlugin extends Plugin {

    init() {
        this._columns = this.el.querySelectorAll(COLLAPSE_COLUMN_SELECTOR);

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
        const event = 'click';

        Iterator.iterate(this._columns, column => {
            const trigger = DomAccess.querySelector(column, COLLAPSE_COLUMN_TRIGGER_SELECTOR);

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
     * @param {Event} event
     * @private
     */
    _onClickCollapseTrigger(event) {
        const trigger = event.target;
        const collapse = trigger.parentNode.querySelector(COLLAPSE_COLUMN_CONTENT_SELECTOR);
        const $collapse = $(collapse);

        $collapse.collapse('toggle');

        $collapse.on('shown.bs.collapse', function () {
            trigger.classList.add(COLLAPSE_SHOW_CLASS);
        });

        $collapse.on('hidden.bs.collapse', function () {
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
