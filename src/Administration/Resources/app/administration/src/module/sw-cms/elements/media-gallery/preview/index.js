import template from './sw-cms-el-preview-media-gallery.html.twig';
import './sw-cms-el-preview-media-gallery.scss';

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
