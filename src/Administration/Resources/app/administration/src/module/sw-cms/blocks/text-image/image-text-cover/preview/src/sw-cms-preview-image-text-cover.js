import template from './sw-cms-preview-image-text-cover.html.twig';
import './sw-cms-preview-image-text-cover.scss';
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
