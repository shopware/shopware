/**
 * @sw-package framework
 */

import template from './sw-example-detail.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('example-draft'),
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadExample();

            const name = this.restoreDraft(`sw-example-detail-${this.exampleId}`);

            if (this.example && name !== null) {
                this.example.name = name;
            }
        },

        async loadExample() {
            if (!this.exampleId) {
                this.example = this.exampleRepository.create();
                return;
            }

            this.isLoading = true;

            try {
                this.example = await this.exampleRepository.get(this.exampleId);
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
