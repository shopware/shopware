/*
 * @package inventory
 */

import template from './sw-product-layout-assignment.html.twig';
import './sw-product-layout-assignment.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl'],

    emits: ['modal-layout-open', 'button-edit-click', 'button-delete-click'],

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
};
