import template from './sw-cms-preview-image-text.html.twig';
import './sw-cms-preview-image-text.scss';
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
