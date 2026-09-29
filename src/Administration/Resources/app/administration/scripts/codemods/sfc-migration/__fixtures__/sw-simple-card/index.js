import template from './sw-simple-card.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    emits: ['save'],

    props: {
        title: {
            type: String,
            required: false,
            default: 'Default Title',
        },
    },

    data() {
        return {
            isLoading: false,
            items: [],
        };
    },

    computed: {
        headline() {
            return `${this.title} (${this.items.length})`;
        },

        repository() {
            return this.repositoryFactory.create('product');
        },
    },

    methods: {
        async loadItems() {
            this.isLoading = true;
            this.items = await this.repository.search(new Shopware.Data.Criteria(1, 25));
            this.isLoading = false;
        },

        onSave() {
            this.$emit('save', this.items);
        },
    },
};
