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
    CREDIT_NOTE: 'credit_note',
    CANCELLATION: 'storno',
    DELIVERY_NOTE: 'delivery_note',
};

/**
 * @private
 */
export const DOCUMENT_CONFIG_DEFAULTS = {
    pageSize: 'a4',
    pageOrientation: 'portrait',
    itemsPerPage: 10,
    fileTypes: [
        'pdf',
        'html',
    ],
    displayHeader: true,
    displayFooter: true,
    displayPageCount: true,
    displayLineItems: true,
    displayLineItemPosition: false,
    displayPrices: true,
    displayAdditionalNoteDelivery: false,
    displayDivergentDeliveryAddress: false,
    displayCustomerVatId: false,
    displayReturnAddress: false,
    displayCompanyAddress: false,
};

/**
 * @private
 */
export const DOCUMENT_SETTINGS_GENERAL = (tc) => [
    {
        name: 'pageSize',
        type: 'single-select',
        config: {
            valueProperty: 'id',
            options: [
                { id: 'a4', label: tc('sw-settings-document.detail.pageSizeOptions.a4') },
                { id: 'a5', label: tc('sw-settings-document.detail.pageSizeOptions.a5') },
                { id: 'letter', label: tc('sw-settings-document.detail.pageSizeOptions.letter') },
                { id: 'legal', label: tc('sw-settings-document.detail.pageSizeOptions.legal') },
            ],
            required: true,
            label: tc('sw-settings-document.detail.labelPageSize'),
            placeholder: tc('sw-settings-document.detail.placeholderPageSize'),
        },
    },
    {
        name: 'pageOrientation',
        type: 'single-select',
        config: {
            valueProperty: 'id',
            options: [
                { id: 'portrait', label: tc('sw-settings-document.detail.pageOrientationOptions.portrait') },
                { id: 'landscape', label: tc('sw-settings-document.detail.pageOrientationOptions.landscape') },
            ],
            required: true,
            label: tc('sw-settings-document.detail.labelPageOrientation'),
            placeholder: tc('sw-settings-document.detail.placeholderPageOrientation'),
        },
    },
    {
        name: 'itemsPerPage',
        type: 'number',
        config: {
            numberType: 'int',
            min: 1,
            required: true,
            label: tc('sw-settings-document.detail.labelItemsPerPage'),
            placeholder: tc('sw-settings-document.detail.placeholderItemsPerPage'),
        },
    },
    {
        name: 'fileTypes',
        type: 'array',
        config: {
            componentName: 'sw-multi-select',
            valueProperty: 'id',
            options: [
                {
                    id: 'pdf',
                    label: 'PDF',
                },
                {
                    id: 'html',
                    label: 'HTML',
                },
            ],
            required: true,
            label: tc('sw-settings-document.detail.labelFileTypes'),
            placeholder: tc('sw-settings-document.detail.placeholderFileTypes'),
        },
    },
];

/**
 * @private
 */
export const DOCUMENT_SETTINGS_GENERAL_DISPLAY = (tc) => [
    {
        name: 'displayHeader',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayHeader'),
        },
    },
    {
        name: 'displayFooter',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayFooter'),
        },
    },
    {
        name: 'displayPageCount',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayPageCount'),
        },
    },
    {
        name: 'displayLineItems',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayLineItems'),
        },
    },
    {
        name: 'displayLineItemPosition',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayLineItemPosition'),
        },
    },
    {
        name: 'displayPrices',
        type: 'checkbox',
        config: {
            type: 'checkbox',
            label: tc('sw-settings-document.detail.labelDisplayPrices'),
        },
    },
    {
        name: 'displayInCustomerAccount',
        type: 'checkbox',
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
        name: 'companyName',
        type: 'text',
        config: {
            type: 'text',
            required: true,
            label: tc('sw-settings-document.detail.labelCompanyName'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyName'),
        },
    },
    {
        name: 'companyEmail',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyEmail'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyEmail'),
        },
    },
    {
        name: 'companyPhone',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyPhone'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyPhone'),
        },
    },
    {
        name: 'companyStreet',
        type: 'text',
        config: {
            type: 'text',
            required: true,
            label: tc('sw-settings-document.detail.labelCompanyStreet'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyStreet'),
        },
    },
    {
        name: 'companyZipcode',
        type: 'text',
        config: {
            type: 'text',
            required: true,
            label: tc('sw-settings-document.detail.labelCompanyZipcode'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyZipcode'),
        },
    },
    {
        name: 'companyCity',
        type: 'text',
        config: {
            type: 'text',
            required: true,
            label: tc('sw-settings-document.detail.labelCompanyCity'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyCity'),
        },
    },
    {
        name: 'companyCountryId',
        type: 'sw-entity-single-select',
        config: {
            entity: 'country',
            componentName: 'sw-entity-single-select',
            required: true,
            label: tc('sw-settings-document.detail.labelCompanyCountry'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyCountry'),
        },
    },
    {
        name: 'companyUrl',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelCompanyUrl'),
            placeholder: tc('sw-settings-document.detail.placeholderCompanyUrl'),
        },
    },
    {
        name: 'taxNumber',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelTaxNumber'),
            placeholder: tc('sw-settings-document.detail.placeholderTaxNumber'),
        },
    },
    {
        name: 'taxOffice',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelTaxOffice'),
            placeholder: tc('sw-settings-document.detail.placeholderTaxOffice'),
        },
    },
    {
        name: 'vatId',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelVatId'),
            placeholder: tc('sw-settings-document.detail.placeholderVatId'),
        },
    },
    {
        name: 'bankName',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankName'),
            placeholder: tc('sw-settings-document.detail.placeholderBankName'),
        },
    },
    {
        name: 'bankIban',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankIban'),
            placeholder: tc('sw-settings-document.detail.placeholderBankIban'),
        },
    },
    {
        name: 'bankBic',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelBankBic'),
            placeholder: tc('sw-settings-document.detail.placeholderBankBic'),
        },
    },
    {
        name: 'placeOfJurisdiction',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPlaceOfJurisdiction'),
            placeholder: tc('sw-settings-document.detail.placeholderPlaceOfJurisdiction'),
        },
    },
    {
        name: 'placeOfFulfillment',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPlaceOfFulfillment'),
            placeholder: tc('sw-settings-document.detail.placeholderPlaceOfFulfillment'),
        },
    },
    {
        name: 'executiveDirector',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelExecutiveDirector'),
            placeholder: tc('sw-settings-document.detail.placeholderExecutiveDirector'),
        },
    },
    {
        name: 'paymentDueDate',
        type: 'text',
        config: {
            type: 'text',
            label: tc('sw-settings-document.detail.labelPaymentDueDate'),
            placeholder: tc('sw-settings-document.detail.placeholderPaymentDueDate'),
        },
    },
];

/**
 * @sw-package after-sales
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
                config: { ...DOCUMENT_CONFIG_DEFAULTS },
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
            return DOCUMENT_SETTINGS_GENERAL(this.$t);
        },

        generalDisplayFields() {
            return DOCUMENT_SETTINGS_GENERAL_DISPLAY(this.$t);
        },

        companyFormFields() {
            return DOCUMENT_SETTINGS_COMPANY(this.$t);
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
                message: this.$t('sw-privileges.tooltip.warning'),
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

        showCompanyForm() {
            return this.documentConfig.config.displayCompanyAddress || this.documentConfig.config.displayReturnAddress;
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

            try {
                const [salesChannels] = await Promise.all([
                    this.salesChannelRepository.search(new Criteria(1, 500)),
                    this.loadCustomFieldSets(),
                ]);

                this.salesChannels = salesChannels;

                if (this.documentConfigId || this.$route.params?.id) {
                    await this.loadEntityData();
                } else {
                    this.documentConfig = this.documentBaseConfigRepository.create();
                    this.documentConfig.global = false;
                    this.documentConfig.config = { ...DOCUMENT_CONFIG_DEFAULTS };
                }
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
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

            this.documentConfig.config = {
                ...DOCUMENT_CONFIG_DEFAULTS,
                ...this.documentConfig.config,
            };

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
                DOCUMENT_TYPE_TECHNICAL_NAMES.CANCELLATION,
                DOCUMENT_TYPE_TECHNICAL_NAMES.DELIVERY_NOTE,
                DOCUMENT_TYPE_TECHNICAL_NAMES.INVOICE,
            ].includes(documentType.technicalName);

            this.createSalesChannelSelectOptions();

            this.documentConfigSalesChannels = [];

            const documentSalesChannelCriteria = new Criteria(1, 500).addFilter(
                Criteria.equals('documentTypeId', documentType.id),
            );

            let responseSalesChannels = [];

            try {
                responseSalesChannels =
                    await this.documentBaseConfigSalesChannelRepository.search(documentSalesChannelCriteria);
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });

                this.typeIsLoading = false;

                return;
            }

            this.alreadyAssignedSalesChannelIdsToType = responseSalesChannels
                .filter(
                    (salesChannel) =>
                        salesChannel.salesChannelId !== null && salesChannel.documentBaseConfigId !== this.documentConfig.id,
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

                const option = this.documentConfigSalesChannelOptionsCollection.find(
                    (o) => o.salesChannelId === salesChannelId,
                );

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
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
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
                const existingAssoc = this.documentConfig.salesChannels.find((sc) => sc.salesChannelId === salesChannel.id);

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

        onChangeCompanyLogo(media) {
            this.documentConfig.logoId = media.at(0)?.id || null;
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
