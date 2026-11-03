import template from './sw-cms-preview-cross-selling.html.twig';
import './sw-cms-preview-cross-selling.scss';
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
