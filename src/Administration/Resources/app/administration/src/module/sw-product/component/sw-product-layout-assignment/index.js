/*
 * @package inventory
 */

import template from './sw-product-layout-assignment.html.twig';
import './sw-product-layout-assignment.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-product-layout-assignment', {
    template,

    inject: ['acl'],

    props: {
        cmsPage: {
            type: Object,
            required: false,
            default: null,
        },
    },

    methods: {
        openLayoutModal() {
            this.$emit('modal-layout-open');
        },

        openInPageBuilder() {
            this.$emit('button-edit-click');
        },

        onLayoutReset() {
            this.$emit('button-delete-click');
        },
    },
});
