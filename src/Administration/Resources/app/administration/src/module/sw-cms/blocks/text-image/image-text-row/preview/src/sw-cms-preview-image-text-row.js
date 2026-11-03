import template from './sw-cms-preview-image-text-row.html.twig';
import './sw-cms-preview-image-text-row.scss';
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
