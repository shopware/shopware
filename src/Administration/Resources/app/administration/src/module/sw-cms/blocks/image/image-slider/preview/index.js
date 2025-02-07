import template from './sw-cms-preview-image-slider.html.twig';
import './sw-cms-preview-image-slider.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
