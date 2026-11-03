/*
 * @sw-package inventory
 */

import template from './sw-product-layout-assignment.html.twig';
import './sw-product-layout-assignment.scss';
import { aclService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService],

    emits: ['modal-layout-open', 'button-edit-click', 'button-delete-click'],

    props: {
        cmsPage: {
            type: Object,
            required: false,
            default: null,
        },

        product: {
            type: Object,
            required: false,
            default: null,
        },
    },

    computed: {
        pageName() {
            if (!this.cmsPage) {
                return this.$t('sw-product.layoutAssignment.title');
            }

            return this.cmsPage.translated?.name ?? this.cmsPage.name;
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
