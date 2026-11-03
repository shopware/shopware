import template from './sw-cms-preview-image-three-column.html.twig';
import './sw-cms-preview-image-three-column.scss';
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
