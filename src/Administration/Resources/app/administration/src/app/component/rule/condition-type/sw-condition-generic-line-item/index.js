import template from './../sw-condition-generic/sw-condition-generic.html.twig';

const { Component, Mixin } = Shopware;

/**
 * @public
 * @package business-ops
 * @description Condition for generic line item rules. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-generic-line-item :condition="condition" :level="0"></sw-condition-generic-line-item>
 */
Component.extend('sw-condition-generic-line-item', 'sw-condition-base-line-item', {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('generic-condition'),
    ],
});
