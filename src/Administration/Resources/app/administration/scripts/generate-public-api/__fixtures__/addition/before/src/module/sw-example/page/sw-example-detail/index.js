/**
 * @sw-package framework
 */

import template from './sw-example-detail.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    props: {
        exampleId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            example: null,
            isLoading: false,
        };
    },

    computed: {
        exampleRepository() {
            return this.repositoryFactory.create('example');
        },

        exampleCriteria() {
            return new Criteria(1, 1);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadExample();
        },

        async loadExample() {
            if (!this.exampleId) {
                this.example = this.exampleRepository.create();
                return;
            }

            this.isLoading = true;

            try {
                this.example = await this.exampleRepository.get(
                    this.exampleId,
                    Shopware.Context.api,
                    this.exampleCriteria,
                );
            } finally {
                this.isLoading = false;
            }
        },

        async onSave() {
            this.isLoading = true;

            try {
                await this.exampleRepository.save(this.example);
            } finally {
                this.isLoading = false;
            }
        },
    },
};
