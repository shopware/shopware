import template from './sw-cms-el-preview-image-gallery.html.twig';
import './sw-cms-el-preview-image-gallery.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
