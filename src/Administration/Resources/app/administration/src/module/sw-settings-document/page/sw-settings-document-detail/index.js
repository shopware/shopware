import template from './sw-settings-document-detail.html.twig';
import './sw-settings-document-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @private
 */
export const DOCUMENT_TYPE_TECHNICAL_NAMES = {
    INVOICE: 'invoice',
    CREDIT_NODE: 'credit_note',
    CANCELATION: 'storno',
    DELIVERY_NOTE: 'delivery_note',
};

/**
 * @private
 */
export const DOCUMENT_SETTINGS_GENERAL = (tc) => [
    {
        name: 'pageOrientation',
        type: 'radio',
        config: {
            componentName: 'sw-single-select',
            labelProperty: 'name',
            valueProperty: 'id',
            options: [
                { id: 'portrait', name: 'Portrait' },
                { id: 'landscape', name: 'Landscape' },
            ],
            label: tc('sw-settings-document.detail.labelPageOrientation'),
        },
    },
    {
        name: 'pageSize',
        type: 'radio',
        config: {
            componentName: 'sw-single-select',
            labelProperty: 'name',
            valueProperty: 'id',
            options: [
                { id: 'a4', name: 'A4' },
                { id: 'a5', name: 'A5' },
                { id: 'legal', name: 'Legal' },
                { id: 'letter', name: 'Letter' },
            ],
            label: tc('sw-settings-document.detail.labelPageSize'),
        },
    },
    {
        name: 'itemsPerPage',
        type: 'number',
        config: {
            type: 'number',
            label: tc('sw-settings-document.detail.labelItemsPerPage'),
        },
    },
    {
        name: 'fileTypes',
        type: 'array',
        config: {
            componentName: 'sw-multi-select',
            labelProperty: 'name',
            valueProperty: 'id',
            options: [
                {
                    id: 'pdf',
                    name: 'PDF',
                },
                {
                    id: 'html',
                    name: 'HTML',
                },
            ],
            label: tc('sw-settings-document.detail.labelFileTypes'),
        },
    },
    {
        name: 'displayHeader',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayHeader'),
        },
    },
    {
        name: 'displayFooter',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayFooter'),
        },
    },
    {
        name: 'displayPageCount',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayPageCount'),
        },
    },
    {
        name: 'displayLineItems',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayLineItems'),
        },
    },
    {
        name: 'displayLineItemPosition',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayLineItemPosition'),
        },
    },
    {
        name: 'displayPrices',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayPrices'),
        },
    },
    {
        name: 'displayInCustomerAccount',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayDocumentInCustomerAccount'),
            helpText: tc('sw-settings-document.detail.helpTextDisplayDocumentInCustomerAccount'),
        },
    },
];

/**
 * @private
 */
export const DOCUMENT_SETTINGS_COMPANY = (tc) => [
    {
        name: 'displayReturnAddress',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayReturnAddress'),
            class: tc('sw-settings-document-detail__return-address-checkbox'),
            helpText: tc('sw-settings-document.detail.helpTextDisplayReturnAddress'),
        },
    },
    {
        name: 'displayCompanyAddress',
        type: 'bool',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayCompanyAddress'),
            class: tc('sw-settings-document-detail__company-address-checkbox'),
            helpText: tc('sw-settings-document.detail.helpTextDisplayCompanyAddress'),
        },
    },
    {
        name: 'companyStreet',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyStreet'),
        },
    },
    {
        name: 'companyZipcode',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyZipcode'),
        },
    },
    {
        name: 'companyCity',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyCity'),
        },
    },
    {
        name: 'companyCountryId',
        type: 'sw-entity-single-select',
        config: {
            entity: 'country',
            componentName: 'sw-entity-single-select',
            label: tc('sw-settings-document.detail.labelCompanyCountry'),
        },
    },
    {
        name: 'companyName',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyName'),
        },
    },
    {
        name: 'companyEmail',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyEmail'),
        },
    },
    {
        name: 'companyPhone',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyPhone'),
        },
    },
    {
        name: 'companyUrl',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyUrl'),
        },
    },
    {
        name: 'taxNumber',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelTaxNumber'),
        },
    },
    {
        name: 'taxOffice',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelTaxOffice'),
        },
    },
    {
        name: 'vatId',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelVatId'),
        },
    },
    {
        name: 'bankName',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankName'),
        },
    },
    {
        name: 'bankIban',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankIban'),
        },
    },
    {
        name: 'bankBic',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankBic'),
        },
    },
    {
        name: 'placeOfJurisdiction',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPlaceOfJurisdiction'),
        },
    },
    {
        name: 'placeOfFulfillment',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPlaceOfFulfillment'),
        },
    },
    {
        name: 'executiveDirector',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelExecutiveDirector'),
        },
    },
    {
        name: 'paymentDueDate',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPaymentDueDate'),
            helpText: tc('sw-settings-document.detail.helpTextPaymentDueDate'),
        },
    },
];

/**
 * @sw-package after-sales
 *
 * @deprecated tag:v6.8.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        documentConfigId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
            selectedType: {},
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
            isSaveSuccessful: false,
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
            isShowCountriesSelect: false,
            isLoading: false,
            typeIsLoading: false,
            salesChannels: null,
            customFieldSets: null,
            isShowDisplayNoteDelivery: false,
            isShowDivergentDeliveryAddress: false,
            documentConfigSalesChannels: [],
            alreadyAssignedSalesChannelIdsToType: [],
            documentConfigSalesChannelOptionsCollection: [],
            documentConfig: {
                config: {
                    displayAdditionalNoteDelivery: false,
                    displayDivergentDeliveryAddress: false,
                    displayCustomerVatId: false,
                    fileTypes: [],
                },
            },
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    async created() {
        await this.createdComponent();
    },

    computed: {
        generalFormFields() {
            return DOCUMENT_SETTINGS_GENERAL(this.$tc);
        },

        companyFormFields() {
            return DOCUMENT_SETTINGS_COMPANY(this.$tc);
        },

        documentBaseConfigRepository() {
            return this.repositoryFactory.create('document_base_config');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        identifier() {
            return this.documentConfig?.name ?? '';
        },

        documentBaseConfigCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('documentType').getAssociation('salesChannels').addAssociation('salesChannel');

            return criteria;
        },

        documentCriteria() {
            // We don't want to select ZUGFeRD as a type. "invoice" configuration is used instead (NEXT-40492)
            return new Criteria(1, 25).addFilter(Criteria.not('AND', [Criteria.prefix('technicalName', 'zugferd_')]));
        },

        tooltipSave() {
            if (this.acl.can('document.editor')) {
                return {
                    message: `${this.$device.getSystemKey()} + S`,
                    appearance: 'light',
                };
            }

            return {
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('order.editor'),
                showOnDisabledElements: true,
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },

        fileTypesSelected() {
            return this.documentConfig?.config?.fileTypes || [];
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        documentBaseConfigSalesChannelRepository() {
            return this.repositoryFactory.create('document_base_config_sales_channel');
        },

        ...mapPropertyErrors('documentConfig', [
            'name',
            'documentTypeId',
        ]),
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            const [salesChannels] = await Promise.all([
                this.salesChannelRepository.search(new Criteria(1, 500)),
                this.loadCustomFieldSets(),
            ]).catch((error) => {
                this.createNotificationError({
                    message: error.message,
                });

                this.isLoading = false;
            });

            this.salesChannels = salesChannels;

            if (this.documentConfigId || this.$route.params?.id) {
                await this.loadEntityData();
            } else {
                this.documentConfig = this.documentBaseConfigRepository.create();
                this.documentConfig.global = false;
                this.documentConfig.config = {};
            }

            this.isLoading = false;
        },

        async loadEntityData() {
            this.isLoading = true;

            const documentConfigId = this.documentConfigId || this.$route.params?.id;

            const documentConfig = await this.documentBaseConfigRepository
                .get(documentConfigId, Shopware.Context.api, this.documentBaseConfigCriteria)
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });

                    this.isLoading = false;
                });

            if (documentConfig) {
                this.documentConfig = documentConfig;
            }

            if (!this.documentConfig.config) {
                this.documentConfig.config = {};
            }

            await this.onChangeType(this.documentConfig.documentType);

            this.documentConfigSalesChannels = (this.documentConfig.salesChannels || []).map(
                (association) => association.salesChannelId,
            );

            this.isLoading = false;
        },

        async loadCustomFieldSets() {
            this.customFieldSets = await this.customFieldDataProviderService.getCustomFieldSets('document_base_config');
        },

        async onChangeType(documentType) {
            if (!documentType) {
                return;
            }

            this.typeIsLoading = true;

            this.documentConfig.documentType = documentType;
            this.documentConfig.documentTypeId = documentType.id;

            this.isShowDivergentDeliveryAddress = documentType.technicalName === DOCUMENT_TYPE_TECHNICAL_NAMES.INVOICE;
            this.isShowDisplayNoteDelivery = [
                DOCUMENT_TYPE_TECHNICAL_NAMES.CANCELATION,
                DOCUMENT_TYPE_TECHNICAL_NAMES.DELIVERY_NOTE,
                DOCUMENT_TYPE_TECHNICAL_NAMES.INVOICE,
            ].includes(documentType.technicalName);

            this.createSalesChannelSelectOptions();

            this.documentConfigSalesChannels = [];

            const documentSalesChannelCriteria = new Criteria(1, 500).addFilter(
                Criteria.equals('documentTypeId', documentType.id),
            );

            const responseSalesChannels = await this.documentBaseConfigSalesChannelRepository
                .search(documentSalesChannelCriteria)
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });

                    this.typeIsLoading = false;
                });

            this.alreadyAssignedSalesChannelIdsToType = responseSalesChannels
                .filter(
                    (salesChannel) =>
                        salesChannel.salesChannelId !== null &&
                        salesChannel.documentBaseConfigId !== this.documentConfig.id,
                )
                .map((salesChannel) => salesChannel.salesChannelId);

            this.typeIsLoading = false;
        },

        onChangeSalesChannel() {
            if (!this.documentConfig.salesChannels) {
                this.documentConfig.salesChannels = [];
            }

            // add new selections
            this.documentConfigSalesChannels.forEach((salesChannelId) => {
                const exists = this.documentConfig.salesChannels.some(
                    (salesChannel) => salesChannel.salesChannelId === salesChannelId,
                );

                if (exists) {
                    return;
                }

                const option = this.documentConfigSalesChannelOptionsCollection.get(salesChannelId);

                if (option) {
                    this.documentConfig.salesChannels.push(option);
                }
            });

            // remove unselected
            this.documentConfig.salesChannels.forEach((salesChannelAssoc) => {
                if (!this.documentConfigSalesChannels.includes(salesChannelAssoc.salesChannelId)) {
                    this.documentConfig.salesChannels.remove(salesChannelAssoc.id);
                }
            });
        },

        async onSave() {
            this.isLoading = true;

            this.onChangeSalesChannel();

            try {
                await this.documentBaseConfigRepository.save(this.documentConfig);

                if (this.documentConfig.isNew()) {
                    await this.$router.replace({
                        name: 'sw.settings.document.detail',
                        params: { id: this.documentConfig.id },
                    });
                }

                await this.loadEntityData();
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onCancel() {
            await this.$router.push({ name: 'sw.settings.document.index' });
        },

        createSalesChannelSelectOptions() {
            this.documentConfigSalesChannelOptionsCollection = new EntityCollection(
                this.documentConfig.salesChannels.source,
                'document_base_config_sales_channel',
                Shopware.Context.api,
            );

            if (!this.documentConfig.documentType || !this.salesChannels) {
                return;
            }

            this.salesChannels.forEach((salesChannel) => {
                const existingAssoc = this.documentConfig.salesChannels.find(
                    (sc) => sc.salesChannelId === salesChannel.id,
                );

                if (existingAssoc) {
                    this.documentConfigSalesChannelOptionsCollection.push(existingAssoc);
                    return;
                }

                const option = this.documentBaseConfigSalesChannelRepository.create();
                option.documentBaseConfigId = this.documentConfig.id;
                option.documentTypeId = this.documentConfig.documentType.id;
                option.salesChannelId = salesChannel.id;
                option.salesChannel = salesChannel;

                this.documentConfigSalesChannelOptionsCollection.push(option);
            });
        },

        onAddDocumentType(typeId) {
            if (!this.documentConfig.config.fileTypes) {
                this.documentConfig.config.fileTypes = [];
            }

            if (typeof typeId === 'object') {
                typeId = typeId.id;
            }

            if (!this.documentConfig.config.fileTypes.includes(typeId)) {
                this.documentConfig.config.fileTypes.push(typeId);
            }
        },

        onRemoveDocumentType(typeId) {
            if (typeof typeId === 'object') {
                typeId = typeId.id;
            }

            const fileTypes = this.documentConfig.config.fileTypes ?? [];

            if (fileTypes.length <= 1) {
                return;
            }

            this.documentConfig.config.fileTypes = fileTypes.filter((id) => id !== typeId);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async loadAvailableSalesChannel() {
            this.salesChannels = await this.salesChannelRepository.search(new Criteria(1, 500));
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        showOption(item) {
            return item.id !== this.documentConfig.id;
        },
    },
};
