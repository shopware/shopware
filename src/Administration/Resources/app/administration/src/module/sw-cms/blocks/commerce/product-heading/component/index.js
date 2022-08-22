import template from './sw-cms-block-product-heading.html.twig';
import './sw-cms-block-product-heading.scss';

const { Component, State } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-product-heading', {
    template,

    computed: {
        currentDeviceView() {
            return State.get('cmsPageState').currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            if (this.currentDeviceView) {
                return `is--${this.currentDeviceView}`;
            }

            return null;
        },
    },
});
