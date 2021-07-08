import template from './sw-flow-generate-document-modal.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

Component.register('sw-flow-generate-document-modal', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            documentType: '',
            fieldError: null,
        };
    },

    computed: {
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        ...mapState('swFlowState', ['documentTypes']),
    },

    watch: {
        documentType(value) {
            if (value && this.fieldError) {
                this.fieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.documentType = this.sequence?.config?.documentType || '';

            if (!this.documentTypes.length) {
                this.documentTypeRepository.search(this.documentTypeCriteria).then((data) => {
                    Shopware.State.commit('swFlowState/setDocumentTypes', data);
                });
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            if (!this.documentType) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    documentType: this.documentType,
                    documentRangerType: `document_${this.documentType}`,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
});
