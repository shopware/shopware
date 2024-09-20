import template from './sw-cms-product-box-preview.html.twig';
import './sw-cms-product-box-preview.scss';

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        hasText: {
            type: Boolean,
            required: false,
            default() {
                return true;
            },
        },
    },

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
});
