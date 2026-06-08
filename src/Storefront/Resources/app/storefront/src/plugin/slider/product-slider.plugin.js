import deepmerge from 'deepmerge';
import BaseSliderPlugin from  'src/plugin/slider/base-slider.plugin';

export default class ProductSliderPlugin extends BaseSliderPlugin {

    /**
     * default slider options
     *
     * @type {*}
     */
    static options = deepmerge(BaseSliderPlugin.options, {
        containerSelector: '[data-product-slider-container=true]',
        controlsSelector: '[data-product-slider-controls=true]',
        productboxMinWidth: '300px',
    });

    /**
     * returns the slider settings for the current viewport
     *
     * @param viewport
     * @private
     */
    _getSettings(viewport) {
        super._getSettings(viewport);

        this._addItemLimit();
    }

    /**
     * extends the slider settings with the slider item limit depending on the product-box and the container width
     *
     * @private
     */
    _addItemLimit() {
        const containerWidth = this._getInnerWidth();
        const gutter = this._sliderSettings.gutter;
        const itemWidth = parseInt(this.options.productboxMinWidth.replace('px', ''), 0);

        const itemLimit = Math.floor(containerWidth / (itemWidth + gutter));

        this._sliderSettings.items = Math.max(1, itemLimit);
    }

    /**
     * returns the inner width of the container without padding
     *
     * @returns {number}
     * @private
     */
    _getInnerWidth() {
        const computedStyle = getComputedStyle(this.el);

        if (!computedStyle) return;

        /**
         * The slider element sits in a content-sized flex item, so its width depends on the
         * product-box content (e.g. the minimal layout without a description) and is not settled
         * during initialization. Measure the surrounding full-width CMS element instead.
         */
        const widthSource = this.el.closest('.cms-element-product-slider') ?? this.el;

        // width with padding
        let width = widthSource.clientWidth;

        width -= parseFloat(computedStyle.paddingLeft) + parseFloat(computedStyle.paddingRight);

        return width;
    }
}
