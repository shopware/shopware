import template from './sw-cms-preview-image-cover.html.twig';
import './sw-cms-preview-image-cover.scss';
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
