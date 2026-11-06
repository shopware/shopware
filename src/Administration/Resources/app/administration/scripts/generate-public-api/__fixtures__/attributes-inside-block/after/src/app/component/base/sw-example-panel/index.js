import template from './sw-example-panel.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    emits: ['item-select'],

    props: {
        title: {
            type: String,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            selectedId: null,
        };
    },

    computed: {
        hasItems() {
            return this.items.length > 0;
        },
    },

    methods: {
        onSelectItem(item) {
            this.selectedId = item.id;
            this.$emit('item-select', item);
        },
    },
};
