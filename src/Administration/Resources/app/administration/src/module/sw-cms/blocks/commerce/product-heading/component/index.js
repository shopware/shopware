import template from './sw-cms-block-product-heading.html.twig';
import './sw-cms-block-product-heading.scss';

const { Store } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        currentDeviceView() {
            return Store.get('cmsPage').currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            return `is--${this.currentDeviceView}`;
        },
    },
};
