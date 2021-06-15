import template from './sw-product-stream-detail.html.twig';
import './sw-product-stream-detail.scss';

const { Component, Mixin, Context } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

Component.register('sw-product-stream-detail', {
    template,

    inject: ['repositoryFactory', 'productStreamConditionService', 'acl', 'customFieldDataProviderService'],

    provide() {
        return {
            productCustomFields: this.productCustomFields,
        };
    },

    beforeRouteLeave(to, from, next) {
        if (this.showModalPreview) {
            this.showModalPreview = false;
            this.$nextTick(() => next());
            return;
        }

        next();
    },

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('productStream'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        productStreamId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            productStream: null,
            productStreamFilters: null,
            productStreamFiltersTree: null,
            deletedProductStreamFilters: [],
            productCustomFields: {},
            showModalPreview: false,
            languageId: null,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.productStream, 'name');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productStreamFiltersRepository() {
            if (!this.productStream) {
                return null;
            }

            return this.repositoryFactory.create(
                this.productStream.filters.entity,
                this.productStream.filters.source,
            );
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        tooltipSave() {
            if (!this.acl.can('product_stream.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    appearance: 'dark',
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        isSystemLanguage() {
            return this.languageId === Context.api.systemLanguageId;
        },

        nameRequired() {
            return this.isSystemLanguage;
        },

        ...mapPropertyErrors('productStream', ['name']),

        showCustomFields() {
            return this.productStream && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        productStreamId: {
            immediate: true,
            handler() {
                if (!this.productStreamId) {
                    this.createProductStream();
                    return;
                }

                this.isLoading = true;
                this.loadEntityData(this.productStreamId).then(() => {
                    this.isLoading = false;
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.languageId = Context.api.languageId;
            this.getProductCustomFields();
            this.loadCustomFieldSets();
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('product_stream').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        createProductStream() {
            this.getProductCustomFields().then(() => {
                Context.api.languageId = Context.api.systemLanguageId;
                this.productStream = this.productStreamRepository.create(Context.api);
                this.productStreamFilters = this.productStream.filters;
            });
        },

        loadEntityData(productStreamId) {
            return this.productStreamRepository.get(productStreamId, Context.api).then((productStream) => {
                this.productStream = productStream;
                return this.loadFilters();
            });
        },

        loadFilters(collection = null) {
            if (collection === null) {
                const filterCriteria = new Criteria();
                filterCriteria.addFilter(Criteria.equals('productStreamId', this.productStreamId));

                return this.productStreamFiltersRepository.search(filterCriteria, Context.api).then((productFilter) => {
                    return this.loadFilters(productFilter);
                });
            }

            if (collection.length >= collection.total) {
                this.productStreamFilters = collection;
                return Promise.resolve();
            }

            const nextCriteria = Criteria.fromCriteria(collection.criteria);
            nextCriteria.page += 1;

            return this.productStreamFiltersRepository.search(nextCriteria, collection.context).then((nextFilters) => {
                collection.push(...nextFilters);
                collection.criteria = nextFilters.criteria;
                collection.total = nextFilters.total;

                return this.loadFilters(collection);
            });
        },

        abortOnLanguageChange() {
            return this.productStreamRepository.hasChanges(this.productStream);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            this.languageId = languageId;

            this.isLoading = true;
            this.loadEntityData(this.productStream.id).then(() => {
                this.isLoading = false;
            });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (this.productStream.isNew()) {
                this.productStream.filters = this.productStreamFiltersTree;
                this.saveProductStream()
                    .then(() => {
                        this.$router.push({ name: 'sw.product.stream.detail', params: { id: this.productStream.id } });
                        this.isSaveSuccessful = true;
                    })
                    .catch(() => {
                        this.showErrorNotification();
                        this.isLoading = false;
                    });

                return;
            }

            this.productStreamRepository.save(this.productStream, Context.api)
                .then(this.syncProductStreamFilters)
                .then(() => {
                    return this.loadEntityData(this.productStream.id);
                })
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                    this.showErrorNotification();
                });
        },

        showErrorNotification() {
            this.createNotificationError({
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                ),
            });
        },

        saveProductStream() {
            return this.productStreamRepository.save(this.productStream, Context.api);
        },

        syncProductStreamFilters() {
            return this.productStreamFiltersRepository.sync(this.productStreamFiltersTree, Context.api)
                .then(() => {
                    if (this.deletedProductStreamFilters.length > 0) {
                        return this.productStreamFiltersRepository
                            .syncDeleted(this.deletedProductStreamFilters, Context.api)
                            .then(() => {
                                this.deletedProductStreamFilters = [];
                            });
                    }

                    return Promise.resolve();
                });
        },

        onCancel() {
            this.$router.push({ name: 'sw.product.stream.index' });
        },

        openModalPreview() {
            this.showModalPreview = true;
        },
        closeModalPreview() {
            this.showModalPreview = false;
        },

        getProductCustomFields() {
            const customFieldsCriteria = new Criteria();
            customFieldsCriteria.addFilter(Criteria.equals('relations.entityName', 'product'))
                .addAssociation('customFields')
                .addAssociation('relations');

            return this.customFieldSetRepository.search(customFieldsCriteria, Context.api).then((customFieldSets) => {
                customFieldSets.forEach((customFieldSet) => {
                    const customFields = customFieldSet.customFields
                        .reduce((acc, customField) => {
                            acc[customField.name] = this.mapCustomFieldType({
                                type: customField.type,
                                value: `customFields.${customField.name}`,
                                label: this.getCustomFieldLabel(customField),
                            });
                            return acc;
                        }, {});
                    Object.assign(this.productCustomFields, customFields);
                });
            });
        },

        getCustomFieldLabel(customField) {
            return this.getInlineSnippet(customField.config.label) || customField.name;
        },

        mapCustomFieldType(customField) {
            switch (customField.type) {
                case 'bool':
                    customField.type = 'boolean';
                    break;
                case 'html':
                case 'text':
                    customField.type = 'string';
                    break;
                case 'date':
                    customField.type = 'string';
                    customField.format = 'date-time';
                    break;
                default:
                    break;
            }
            return customField;
        },

        updateFilterTree({ conditions, deletedIds }) {
            this.productStreamFiltersTree = conditions;
            this.deletedProductStreamFilters = [
                ...this.deletedProductStreamFilters,
                ...deletedIds,
            ];
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
            };
        },
    },
});
