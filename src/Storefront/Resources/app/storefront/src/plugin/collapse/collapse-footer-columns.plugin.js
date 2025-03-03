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
        this._clickCallbackRegistry = new Map();
        this._showCallbackRegistry = new Map();
        this._hideCallbackRegistry = new Map();

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

            // add event listener if currently in an allowed viewport
            if (this._isInAllowedViewports()) {
                this._initCollapse(trigger, collapseEl);
            } else {
                this._disposeCollapse(trigger, collapseEl);
            }
        });

        this.$emitter.publish('onViewportHasChanged');
    }

    /**
     * Initializes new collapse.
     *
     * @param {HTMLElement} trigger
     * @param {HTMLElement} collapseEl
     * @private
     */
    _initCollapse(trigger, collapseEl) {
        if (!trigger || !collapseEl) {
            return;
        }

        const collapse = new bootstrap.Collapse(collapseEl, {
            toggle: false,
        });

        const clickCallback = this._onClickCollapseTrigger.bind(this, collapse);
        const showCallback = this._onShowCollapse.bind(this, trigger, collapseEl);
        const hideCallback = this._onHideCollapse.bind(this, trigger, collapseEl);

        trigger.addEventListener('click', clickCallback);
        collapseEl.addEventListener('shown.bs.collapse', showCallback);
        collapseEl.addEventListener('hidden.bs.collapse', hideCallback);

        this._clickCallbackRegistry.set(trigger, clickCallback);
        this._showCallbackRegistry.set(collapseEl, showCallback);
        this._hideCallbackRegistry.set(collapseEl, hideCallback);
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

        if (this._clickCallbackRegistry.get(trigger)) trigger.removeEventListener('click', this._clickCallbackRegistry.get(trigger));
        if (this._showCallbackRegistry.get(collapseEl)) collapseEl.removeEventListener('shown.bs.collapse', this._showCallbackRegistry.get(collapseEl));
        if (this._hideCallbackRegistry.get(collapseEl)) collapseEl.removeEventListener('hidden.bs.collapse', this._hideCallbackRegistry.get(collapseEl));

        trigger.classList.remove(this.options.collapseShowClass);
        collapseEl.classList.remove(this.options.collapseShowClass);

        trigger.setAttribute('aria-expanded', 'true');
        collapseEl.setAttribute('aria-expanded', 'true');
    }

    /**
     * On clicking the collapse trigger (column headline) the columns
     * content area shall be toggled open/close
     * @private
     * @param collapse
     */
    _onClickCollapseTrigger(collapse) {
        collapse.toggle();

        this.$emitter.publish('onClickCollapseTrigger');
    }

    /**
     * Triggered when the collapse is opened to apply additional attributes.
     *
     * @param {HTMLElement} trigger
     * @param {HTMLElement} collapseEl
     * @private
     */
    _onShowCollapse(trigger, collapseEl) {
        trigger.classList.add(this.options.collapseShowClass);
        trigger.setAttribute('aria-expanded', 'true');
        collapseEl.setAttribute('aria-expanded', 'true');

        this.$emitter.publish('onCollapseShown');
    }

    /**
     * Triggered when the collapse is closed to remove additional attributes.
     *
     * @param {HTMLElement} trigger
     * @param {HTMLElement} collapseEl
     * @private
     */
    _onHideCollapse(trigger, collapseEl) {
        trigger.classList.remove(this.options.collapseShowClass);
        trigger.setAttribute('aria-expanded', 'false');
        collapseEl.setAttribute('aria-expanded', 'false');

        this.$emitter.publish('onCollapseHidden');
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
