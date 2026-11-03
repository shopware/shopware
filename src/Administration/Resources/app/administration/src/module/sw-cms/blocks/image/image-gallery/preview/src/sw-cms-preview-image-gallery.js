import template from './sw-cms-preview-image-gallery.html.twig';
import './sw-cms-preview-image-gallery.scss';
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
