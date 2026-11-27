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
        /**
         * @bc-change ParameterRemoval version:v6.8.0 parameter:itemIndex
         *   The template stops passing the index. Use this.items.indexOf(item).
         */
        onDeleteItem(item, itemIndex) {
            this.items.splice(itemIndex ?? this.items.indexOf(item), 1);
            this.$emit('item-delete', item);
        },

        /**
         * @bc-change ParameterRemoval version:v6.8.0 parameter:limit
         *   The limit is read from the store instead.
         */
        onPageChange(page) {
            this.$emit('page-change', { page });
        },

        /** @deprecated tag:v6.8.0 - Use {@link hasSelection} instead. */
        validateSelection() {
            return this.selectedIds.length > 0;
        },
    },
};
