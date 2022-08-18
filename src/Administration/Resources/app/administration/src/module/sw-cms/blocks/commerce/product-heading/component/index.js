import template from './sw-cms-block-product-heading.html.twig';
import './sw-cms-block-product-heading.scss';

const { Component, State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
