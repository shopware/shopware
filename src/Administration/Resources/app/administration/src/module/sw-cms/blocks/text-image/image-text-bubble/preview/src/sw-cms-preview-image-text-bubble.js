import template from './sw-cms-preview-image-text-bubble.html.twig';
import './sw-cms-preview-image-text-bubble.scss';
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
