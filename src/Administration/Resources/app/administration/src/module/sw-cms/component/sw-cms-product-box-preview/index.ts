import template from './sw-cms-product-box-preview.html.twig';
import './sw-cms-product-box-preview.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
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
            return Shopware.Filter.getByName('asset');
        },
    },
});
