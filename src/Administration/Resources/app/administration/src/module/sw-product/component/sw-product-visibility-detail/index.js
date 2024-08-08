/*
 * @package inventory
 */

import template from './sw-product-visibility-detail.html.twig';
import './sw-product-visibility-detail.scss';

const { mapState } = Shopware.Component.getComponentHelper();
const { Filter } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

        truncateFilter() {
            return Filter.getByName('truncate');
        },

        filteredItems() {
            return this.product.visibilities.filter((item) => {
                return !item.isDeleted;
            });
        },

        names() {
            const names = {};

            this.filteredItems.forEach((item) => {
                names[item.id] = item.salesChannelInternal ?
                    item.salesChannelInternal.translated.name : item.salesChannel.translated.name;
            });

            return names;
        },
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

            this.total = this.filteredItems.length;
            this.items = this.filteredItems.slice(offset, offset + params.limit);
        },

        changeVisibilityValue(event, item) {
            item.visibility = Number(event);
        },
    },
};
