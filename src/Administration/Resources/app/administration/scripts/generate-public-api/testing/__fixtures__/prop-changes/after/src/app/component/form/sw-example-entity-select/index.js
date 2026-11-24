/**
 * @sw-package framework
 */

import template from './sw-example-entity-select.html.twig';

const { Criteria } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['update:value'],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        entity: {
            type: String,
            required: true,
        },

        placeholderText: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            results: [],
            isLoading: false,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create(this.entity);
        },
    },

    created() {
        this.loadResults();
    },

    methods: {
        async loadResults() {
            this.isLoading = true;

            try {
                this.results = await this.repository.search(new Criteria(1, 25));
            } finally {
                this.isLoading = false;
            }
        },

        onSelect(item) {
            this.$emit('update:value', item.id);
        },

        onClear() {
            this.$emit('update:value', null);
        },
    },
};
