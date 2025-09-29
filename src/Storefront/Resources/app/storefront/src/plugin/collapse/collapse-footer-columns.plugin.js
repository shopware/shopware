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
                this._initCollapse(trigger, collapseEl);
            } else {
                this._disposeCollapse(trigger, collapseEl);
            }
        });

        this.$emitter.publish('onViewportHasChanged');
    }

    /**
     +  * Initializes new collapse (mobile/tablet). Also ensures trigger has
     +  * proper data-API attributes so Bootstrap will toggle it.
     +  *
     +  * @param {HTMLElement} trigger
     +  * @param {HTMLElement} collapseEl
     +  * @private
     +  */
    _initCollapse(trigger, collapseEl) {
        if (!collapseEl) return;

        // Ensure the collapse element has a stable id the trigger can target
        if (!collapseEl.id) {
            collapseEl.id = `footer-collapse-${Math.random().toString(36).slice(2)}`;
        }

        if (trigger) {
            trigger.setAttribute('data-bs-toggle', 'collapse');
            trigger.setAttribute('data-bs-target', `#${collapseEl.id}`);
            trigger.setAttribute('aria-controls', collapseEl.id);
            trigger.classList.add('collapsed');
            trigger.setAttribute('aria-expanded', 'false');
        }

        new bootstrap.Collapse(collapseEl, { toggle: false });
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

        trigger.removeAttribute('data-bs-toggle');
        trigger.removeAttribute('data-bs-target');
        trigger.removeAttribute('aria-controls');
        trigger.classList.remove('collapsed');
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
