import Plugin from 'src/plugin-system/plugin.class';
import ViewportDetection from 'src/helper/viewport-detection.helper';

/**
 * @sw-package framework
 */
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
        this._columns.forEach(column => {
            const trigger = column.querySelector(this.options.collapseColumnTriggerSelector);
            const collapseEl = column.querySelector(this.options.collapseColumnContentSelector);

            if (this._isInAllowedViewports()) {
                this._initCollapse(collapseEl);
            } else {
                this._disposeCollapse(trigger, collapseEl);
            }
        });

        this.$emitter.publish('onViewportHasChanged');
    }

    /**
     * Initializes new collapse.
     *
     * @param {HTMLElement} collapseEl
     * @private
     */
    _initCollapse(collapseEl) {
        if (!collapseEl) {
            return;
        }

        new bootstrap.Collapse(collapseEl, {
            toggle: false,
        });
    }

    /**
     * Removes the collapse and corresponding attributes.
     *
     * @param {HTMLElement} trigger
     * @param {HTMLElement} collapseEl
     * @private
     */
    _disposeCollapse(trigger, collapseEl) {
        if (!trigger || !collapseEl) {
            return;
        }

        const collapse = bootstrap.Collapse.getInstance(collapseEl);

        if (collapse) {
            collapse.dispose();
        }

        trigger.setAttribute('aria-expanded', 'true');
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
