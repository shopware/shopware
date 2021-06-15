import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import Iterator from 'src/helper/iterator.helper';

export default class CollapseFooterColumnsPlugin extends Plugin {

    static options = {
        collapseShowClass: 'show',
        collapseColumnSelector: '.js-footer-column',
        collapseColumnTriggerSelector: '.js-collapse-footer-column-trigger',
        collapseColumnContentSelector: '.js-footer-column-content',
    };

    init() {
        this._columns = this.el.querySelectorAll(this.options.collapseColumnSelector);

        this._registerEvents();
    }

    /**
     * Register event listeners
     * @private
     */
    _registerEvents() {
        // register event listeners for the first time
        this._onViewportHasChanged();

        document.addEventListener('Viewport/hasChanged', this._onViewportHasChanged.bind(this));
    }

    /**
     * If viewport has changed verify whether to add event listeners to the
     * column headlines for triggering collapse toggling or not
     * @private
     */
    _onViewportHasChanged() {
        const event = 'click';

        Iterator.iterate(this._columns, column => {
            const trigger = DomAccess.querySelector(column, this.options.collapseColumnTriggerSelector);

            // remove possibly existing event listeners
            trigger.removeEventListener(event, this._onClickCollapseTrigger);

            // add event listener if currently in an allowed viewport
            if (this._isInAllowedViewports()) {
                trigger.addEventListener(event, this._onClickCollapseTrigger.bind(this));
            }
        });

        this.$emitter.publish('onViewportHasChanged');
    }

    /**
     * On clicking the collapse trigger (column headline) the columns
     * content area shall be toggled open/close
     * @param {Event} event
     * @private
     */
    _onClickCollapseTrigger(event) {
        const trigger = event.target;
        const collapse = trigger.parentNode.querySelector(this.options.collapseColumnContentSelector);
        const $collapse = $(collapse);
        const collapseShowClass = this.options.collapseShowClass;

        $collapse.collapse('toggle');

        $collapse.on('shown.bs.collapse', () => {
            trigger.classList.add(collapseShowClass);

            this.$emitter.publish('onCollapseShown');
        });

        $collapse.on('hidden.bs.collapse', () => {
            trigger.classList.remove(collapseShowClass);

            this.$emitter.publish('onCollapseHidden');
        });

        this.$emitter.publish('onClickCollapseTrigger');
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
