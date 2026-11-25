import template from './sw-example-list.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    emits: ['item-delete', 'page-change'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            selectedIds: [],
        };
    },

    methods: {
        onDeleteItem(item, itemIndex) {
            this.items.splice(itemIndex, 1);
            this.$emit('item-delete', item);
        },

        onPageChange(page, limit = 25) {
            this.$emit('page-change', { page, limit });
        },

        async validateSelection() {
            await this.$nextTick();

            return this.selectedIds.length > 0;
        },
    },
};
