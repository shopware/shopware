import template from './sw-price-preview.html.twig';

const { Component } = Shopware;

/**
 * @package admin
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
Component.extend('sw-price-preview', 'sw-price-field', {
    template,

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },
});
