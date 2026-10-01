import template from './sw-price-preview.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @example-type static
 * @component-example
 * <sw-price-preview
 *     :taxRate="{ taxRate: 19 }"
 *     :price="[{ net: 10, gross: 11.90, currencyId: '...' }, ...]"
 *     :defaultPrice="{...}"
 *     :currency="{...}">
 * </sw-price-preview>
 */
export default {
    template,

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },
};
