import template from './sw-cms-preview-image-text-gallery.html.twig';
import './sw-cms-preview-image-text-gallery.scss';
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
