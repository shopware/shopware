import template from './sw-cms-preview-image-text-gallery.html.twig';
import './sw-cms-preview-image-text-gallery.scss';

/**
 * @private
 * @package discovery
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
