import template from './sw-description-list.html.twig';
import './sw-description-list.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description A definition list which uses CSS grid for a column layout.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-description-list>
 *     <dt>Product name</dt>
 *     <dd>Example product</dd>
 *     <dt>Price</dt>
 *     <dd>$4.99</dd>
 *     <dt>Description</dt>
 *     <dd>Lorem ipsum dolor sit amet, consetetur sadipscing elitr</dd>
 * </sw-description-list>
 */
Component.register('sw-description-list', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        grid: {
            type: String,
            required: false,
            default: '1fr',
        },
    },

    computed: {
        descriptionListStyles() {
            return {
                'grid-template-columns': this.grid,
            };
        },
    },
});
