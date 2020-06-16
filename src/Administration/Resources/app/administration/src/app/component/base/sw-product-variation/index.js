import template from './sw-product-variation.html.twig';

const { Component } = Shopware;

/**
 * @private
 * @description Component which renders the variations of variant products.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-product-variation :variations="variations"></sw-product-variation>
 */
Component.register('sw-product-variation', {
    template,

    props: {
        variations: {
            type: Array,
            required: true
        }
    }
});
