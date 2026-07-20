import template from './sw-order-select-document-type-modal.html.twig';
import './sw-order-select-document-type-modal.scss';
import { DOCUMENT_TYPES, ZUGFERD_DOCUMENT_TYPES } from '../../order.types';

/**
 * @sw-package checkout
 */

const { Criteria } = Shopware.Data;

/**
 * @private
 */
export const REQUIRES_INVOICE = [
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
    DOCUMENT_TYPES.CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
];

/**
 * @private
 */
export const REQUIRES_CREDIT_ITEMS = [
    DOCUMENT_TYPES.CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    emits: [
        'modal-close',
        'update:value',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },

        value: {
            required: false,
            type: Object,
            default: null,
        },
    },

    data() {
        return {
            documentTypes: [],
            documentTypeCollection: null,
            documentType: null,
            invoiceExists: false,
            isLoading: false,
            showZugferd: false,
        };
    },

    computed: {
        creditItems() {
            const items = [];

            this.order.lineItems.forEach((lineItem) => {
                if (lineItem.type === 'credit') {
                    items.push(lineItem);
                }
            });

            return items;
        },

        documentRepository() {
            return this.repositoryFactory.create('document');
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            return new Criteria(1, 100).addSorting(Criteria.sort('name', 'ASC'));
        },

        documentCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('order.id', this.order.id));
            criteria.addFilter(
                Criteria.equalsAny('documentType.technicalName', [
                    DOCUMENT_TYPES.INVOICE,
                    DOCUMENT_TYPES.ZUGFERD_INVOICE,
                    DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
                ]),
            );

            return criteria;
        },

        filteredDocumentTypes() {
            return this.documentTypes
                .filter((type) => {
                    const isZugferd = ZUGFERD_DOCUMENT_TYPES.includes(type.technicalName);

                    return this.showZugferd ? isZugferd : !isZugferd;
                })
                .sort();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.documentRepository.searchIds(this.documentCriteria).then((documentCollection) => {
                this.invoiceExists = documentCollection.total > 0;

                this.documentTypeRepository.search(this.documentTypeCriteria).then((response) => {
                    this.documentTypeCollection = response;

                    this.documentTypes = response.map((documentType) => {
                        const option = {
                            value: documentType.id,
                            name: documentType.translated.name,
                            technicalName: documentType.technicalName,
                            disabled: !this.documentTypeAvailable(documentType),
                        };

                        if (REQUIRES_INVOICE.includes(documentType.technicalName) && !this.invoiceExists) {
                            return this.addHelpTextToOption(option, documentType);
                        }

                        if (
                            REQUIRES_CREDIT_ITEMS.includes(documentType.technicalName) &&
                            this.invoiceExists &&
                            this.creditItems.length === 0
                        ) {
                            return this.addHelpTextToOption(option, documentType);
                        }

                        return option;
                    });

                    if (this.documentTypes.length) {
                        this.documentType = this.filteredDocumentTypes.find((documentType) => !documentType.disabled).value;
                        this.onRadioFieldChange();
                    }

                    this.isLoading = false;
                });
            });
        },

        documentTypeAvailable(documentType) {
            const type = documentType.technicalName;

            if (!REQUIRES_INVOICE.includes(type)) {
                return true;
            }

            if (!this.invoiceExists) {
                return false;
            }

            if (REQUIRES_CREDIT_ITEMS.includes(type)) {
                return this.creditItems.length !== 0;
            }

            return true;
        },

        addHelpTextToOption(option, documentType) {
            option.helpText = this.$t(`sw-order.components.selectDocumentTypeModal.helpText.${documentType.technicalName}`);

            return option;
        },

        onRadioFieldChange() {
            if (!this.documentType) {
                return;
            }

            this.$emit('update:value', this.documentTypeCollection.get(this.documentType));
        },

        onChangeShowZugferd() {
            this.showZugferd = !this.showZugferd;
            this.documentType = this.filteredDocumentTypes.find((documentType) => !documentType.disabled)?.value || null;

            this.onRadioFieldChange();
        },
    },
};
