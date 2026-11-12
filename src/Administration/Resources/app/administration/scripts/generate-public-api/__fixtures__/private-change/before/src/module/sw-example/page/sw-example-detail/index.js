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

        /**
         * @private
         */
        draftKey() {
            return `sw-example-detail-${this.exampleId}`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadExample();
            this.onRestoreDraft();
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
                this.clearDraft(this.draftKey);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * @private
         */
        onRestoreDraft() {
            const name = this.restoreDraft(this.draftKey);

            if (this.example && name !== null) {
                this.example.name = name;
            }
        },
    },
};
