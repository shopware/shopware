import template from './sw-cms-preview-gallery-buybox.html.twig';
import './sw-cms-preview-gallery-buybox.scss';
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
