import Plugin from 'src/plugin-system/plugin.class';
import ViewportDetection from 'src/helper/viewport-detection.helper';

export default class CrossSellingPlugin extends Plugin {

    static options = {
        tabSelector: 'a[data-bs-toggle="tab"]',
        productSliderSelector: '[data-product-slider="true"]',
    };

    init() {
        this._registerEvents();
    }

    _registerEvents() {
        const crossSellingTabs = this.el.querySelectorAll(this.options.tabSelector);
        crossSellingTabs.forEach((tab) => {
            tab.addEventListener('shown.bs.tab', this._rebuildCrossSellingSlider.bind(this));
        });
    }

    _rebuildCrossSellingSlider(event) {
        if (!event.target.hasAttribute('id')) {
            return;
        }

        const id = event.target.id;
        const correspondingContent = document.querySelector(`#${id}-pane`);

        const slider = correspondingContent.querySelector(this.options.productSliderSelector);

        if (slider === false) {
            return;
        }

        const sliderInstance = window.PluginManager.getPluginInstanceFromElement(slider, 'ProductSlider');

        sliderInstance.rebuild(ViewportDetection.getCurrentViewport(), true);
    }
}
