/*
 * @package inventory
 */

import template from './sw-product-visibility-detail.html.twig';
import './sw-product-visibility-detail.scss';

const { mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onPageChange({ page: this.page, limit: this.limit });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;
            const all = this.product.visibilities.filter((item) => {
                return !item.isDeleted;
            });
            this.total = all.length;

            this.items = all.slice(offset, offset + params.limit);
        },

        changeVisibilityValue(event, item) {
            item.visibility = Number(event);
        },
    },
};
