import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import Feature from 'src/helper/feature.helper';

export default class CrossSellingPlugin extends Plugin {

    static options = {
        /**
         * @deprecated tag:v6.5.0 - Bootstrap v5 renames `data-toggle` attribute to `data-bs-toggle`
         * @see https://getbootstrap.com/docs/5.0/migration/#javascript
         */
        tabSelector: Feature.isActive('V6_5_0_0') ? 'a[data-bs-toggle="tab"]' : 'a[data-toggle="tab"]',
        productSliderSelector: '[data-product-slider="true"]',
    };

    init() {
        this._registerEvents();
    }

    _registerEvents() {
        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses native HTML elements to subscribe to Tab plugin */
        if (Feature.isActive('V6_5_0_0')) {
            const crossSellingTabs = DomAccess.querySelectorAll(this.el, this.options.tabSelector);
            crossSellingTabs.forEach((tab) => {
                tab.addEventListener('shown.bs.tab', this._rebuildCrossSellingSlider.bind(this));
            });
        } else {
            $(this.options.tabSelector).on('shown.bs.tab', this._rebuildCrossSellingSlider.bind(this));
        }
    }

    _rebuildCrossSellingSlider(event) {
        if (!event.target.hasAttribute('id')) {
            return;
        }

        const id = event.target.id;
        const correspondingContent = DomAccess.querySelector(document, `#${id}-pane`);

        const slider = DomAccess.querySelector(correspondingContent, this.options.productSliderSelector, false);

        if (slider === false) {
            return;
        }

        const sliderInstance = window.PluginManager.getPluginInstanceFromElement(slider, 'ProductSlider');

        sliderInstance.rebuild(ViewportDetection.getCurrentViewport(), true);
    }
}
