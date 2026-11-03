import template from './sw-cms-preview-image-highlight-row.html.twig';
import './sw-cms-preview-image-highlight-row.scss';
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
