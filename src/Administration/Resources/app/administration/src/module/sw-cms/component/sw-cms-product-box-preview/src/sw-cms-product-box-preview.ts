import template from './sw-cms-product-box-preview.html.twig';
import './sw-cms-product-box-preview.scss';
import { getAssetFilter, wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    props: {
        hasText: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },
    },

    computed: {
        assetFilter() {
            return getAssetFilter();
        },
    },
});
