import template from './sw-cms-block-gallery-buybox.html.twig';
import './sw-cms-block-gallery-buybox.scss';

const { Store } = Shopware;

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        currentDeviceView() {
            return Store.get('cmsPage').currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            return `is--${this.currentDeviceView}`;
        },
    },
};
