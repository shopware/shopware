import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-variant-info',
    load: () => import('./src/sw-product-variant-info'),
    public: {
        props: [
            'variations',
            'highlighted',
            'searchTerm',
            'titleTerm',
            'showTooltip',
            'ommitParenthesis',
            'seperator',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_product_variant_info',
            'sw_product_variant_info_product_name',
            'sw_product_variant_info_options',
            'sw_product_variant_info_options_text',
        ],
        computed: [
            'productName',
        ],
        methods: [
            'mountedComponent',
            'getFirstSlot',
            'setHelpText',
        ],
    },
});
