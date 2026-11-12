/**
 * @sw-package framework
 */
import template from './sw-example-list.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            examples: null,
            exampleToDelete: null,
            isLoading: false,
            selection: {},
        };
    },

    computed: {
        exampleRepository() {
            return this.repositoryFactory.create('example');
        },

        selectionCount() {
            return Object.keys(this.selection).length;
        },
    },

    created() {
        this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                this.examples = await this.exampleRepository.search(new Criteria(1, 25));
            } finally {
                this.isLoading = false;
            }
        },

        onSelectionChange(selection) {
            this.selection = selection;
        },

        onDelete(example) {
            this.exampleToDelete = example;
        },

        onCloseDeleteModal() {
            this.exampleToDelete = null;
        },

        async onConfirmDelete() {
            await this.exampleRepository.delete(this.exampleToDelete.id);

            this.onCloseDeleteModal();
            await this.getList();
        },
    },
};
