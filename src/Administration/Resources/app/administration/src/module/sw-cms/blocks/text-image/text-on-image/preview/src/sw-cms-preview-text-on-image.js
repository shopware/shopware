import template from './sw-cms-preview-text-on-image.html.twig';
import './sw-cms-preview-text-on-image.scss';
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
