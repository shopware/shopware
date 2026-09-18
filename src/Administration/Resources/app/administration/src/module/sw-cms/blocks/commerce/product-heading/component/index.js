import useCmsPageStore from 'shopware:stores/cmsPage';
import template from './sw-cms-block-product-heading.html.twig';
import './sw-cms-block-product-heading.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        currentDeviceView() {
            return useCmsPageStore().currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            return `is--${this.currentDeviceView}`;
        },
    },
};
