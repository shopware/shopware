import template from './sw-cms-el-preview-image-gallery.html.twig';
import './sw-cms-el-preview-image-gallery.scss';
import { getAssetFilter } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return getAssetFilter();
        },
    },
};
