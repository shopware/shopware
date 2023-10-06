import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class CollapseCheckoutConfirmMethodsPlugin extends Plugin {

    static options = {
        collapseShowClass: 'show',
        collapseContainerSelector: '.collapse',
        collapseTriggerLabelSelector: '.confirm-checkout-collapse-trigger-label',
        collapseTriggerChevronSelector: '.icon-confirm-checkout-chevron',
        collapseTriggerMoreLabel: 'Show more',
        collapseTriggerLessLabel: 'Show less',
    };

    init() {
        this._registerEvents();
    }

    /**
     * Register event listeners
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('click', this._onClickCollapseTrigger.bind(this));

        const collapse = DomAccess.querySelector(this.el.parentNode, this.options.collapseContainerSelector);

        collapse.addEventListener('show.bs.collapse', this._onCollapseShow.bind(this));
        collapse.addEventListener('hide.bs.collapse', this._onCollapseHide.bind(this));
    }

    /**
     * On clicking the collapse trigger
     * content area shall be toggled open/close
     * @private
     */
    _onClickCollapseTrigger(event) {
        event.preventDefault();

        const collapse = DomAccess.querySelector(this.el.parentNode, this.options.collapseContainerSelector);

        new bootstrap.Collapse(collapse, {
            toggle: true,
        });

        this.$emitter.publish('onClickCollapseTrigger');
    }

    _onCollapseShow() {
        const collapseTriggerLabel = DomAccess.querySelector(this.el, this.options.collapseTriggerLabelSelector);
        const collapseTriggerChevron = DomAccess.querySelector(this.el, this.options.collapseTriggerChevronSelector);

        collapseTriggerLabel.textContent = this.options.collapseTriggerLessLabel;
        collapseTriggerChevron.classList.add('icon-rotate-180');
    }

    _onCollapseHide() {
        const collapseTriggerLabel = DomAccess.querySelector(this.el, this.options.collapseTriggerLabelSelector);
        const collapseTriggerChevron = DomAccess.querySelector(this.el, this.options.collapseTriggerChevronSelector);

        collapseTriggerLabel.textContent = this.options.collapseTriggerMoreLabel;
        collapseTriggerChevron.classList.remove('icon-rotate-180');
    }
}
