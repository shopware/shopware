import template from './sw-cms-preview-image-simple-grid.html.twig';
import './sw-cms-preview-image-simple-grid.scss';
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
