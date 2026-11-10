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
        onDeleteItem(item) {
            this.items.splice(this.items.indexOf(item), 1);
            this.$emit('item-delete', item);
        },

        onPageChange(page) {
            this.$emit('page-change', { page });
        },

        validateSelection() {
            return this.selectedIds.length > 0;
        },
    },
};
