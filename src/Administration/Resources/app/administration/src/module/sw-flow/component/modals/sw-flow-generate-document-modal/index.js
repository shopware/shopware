import template from './sw-flow-generate-document-modal.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;

Component.register('sw-flow-generate-document-modal', {
    template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        if (!this.feature.isActive('FEATURE_NEXT_18083')) {
            return {
                documentType: '',
                fieldError: null,
            };
        }

        return {
            documentTypesSelected: [],
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
        /**
         * @feature-deprecated (flag:FEATURE_NEXT_18083) will be remove
         */
        documentType(value) {
            if (value && this.fieldError) {
                this.fieldError = null;
            }
        },

        /**
         * @internal (flag:FEATURE_NEXT_18083)
         */
        documentTypesSelected(value) {
            if (value.length > 0 && this.fieldError) {
                this.fieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.feature.isActive('FEATURE_NEXT_18083')) {
                this.documentType = this.sequence?.config?.documentType || '';
            } else {
                if (this.sequence?.config?.documentType) {
                    this.documentTypesSelected = [this.sequence.config];
                } else {
                    this.documentTypesSelected = this.sequence?.config?.documentTypes || [];
                }

                this.documentTypesSelected = this.documentTypesSelected.map((type) => {
                    return type.documentType;
                });
            }

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
            if (this.feature.isActive('FEATURE_NEXT_18083') ? !this.documentTypesSelected.length : !this.documentType) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            let sequence = {
                ...this.sequence,
            };

            if (!this.feature.isActive('FEATURE_NEXT_18083')) {
                sequence = {
                    ...sequence,
                    config: {
                        documentType: this.documentType,
                        documentRangerType: `document_${this.documentType}`,
                    },
                };
            } else {
                const documentTypes = this.documentTypesSelected.map((documentType) => {
                    return {
                        documentType: documentType,
                        documentRangerType: `document_${documentType}`,
                    };
                });

                sequence = {
                    ...sequence,
                    config: {
                        documentTypes,
                    },
                };
            }

            this.$emit('process-finish', sequence);
        },
    },
});
