import Plugin from 'src/plugin-system/plugin.class';

/**
 * @sw-package inventory
 */
export default class GaranLabelTogglePlugin extends Plugin {

    static options = {
        previewSelector: '.product-detail-garan-label',
        fullSelector: '.product-detail-garan-label-full',
        triggerSelector: '[data-garan-label-toggle-trigger]',
        textSelector: '.product-detail-garan-label-show-link-text',
        iconShowSelector: '.product-detail-garan-label-show-link-icon-show',
        iconHideSelector: '.product-detail-garan-label-show-link-icon-hide',
        showTextAttribute: 'data-garan-label-show-text',
        hideTextAttribute: 'data-garan-label-hide-text',
        hiddenCls: 'd-none',
    };

    init() {
        this._trigger = this.el.querySelector(this.options.triggerSelector);
        this._preview = this.el.querySelector(this.options.previewSelector);
        this._full = this.el.querySelector(this.options.fullSelector);
        this._text = this._trigger ? this._trigger.querySelector(this.options.textSelector) : null;
        this._iconShow = this._trigger ? this._trigger.querySelector(this.options.iconShowSelector) : null;
        this._iconHide = this._trigger ? this._trigger.querySelector(this.options.iconHideSelector) : null;

        if (!this._trigger || !this._preview || !this._full || !this._text || !this._iconShow || !this._iconHide) {
            return;
        }

        this._showText = this._trigger.getAttribute(this.options.showTextAttribute);
        this._hideText = this._trigger.getAttribute(this.options.hideTextAttribute);

        this._registerEvents();
    }

    _registerEvents() {
        this._trigger.addEventListener('click', this._onClickTrigger.bind(this));
    }

    _onClickTrigger() {
        const isFullVisible = !this._full.classList.contains(this.options.hiddenCls);

        this._preview.classList.toggle(this.options.hiddenCls, !isFullVisible);
        this._full.classList.toggle(this.options.hiddenCls, isFullVisible);
        this._text.textContent = isFullVisible ? this._showText : this._hideText;
        this._iconShow.classList.toggle(this.options.hiddenCls, !isFullVisible);
        this._iconHide.classList.toggle(this.options.hiddenCls, isFullVisible);
    }
}
