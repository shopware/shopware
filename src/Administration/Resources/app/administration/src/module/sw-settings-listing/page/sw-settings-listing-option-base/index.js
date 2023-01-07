import template from './sw-settings-listing-option-base.html.twig';
import './sw-settings-listing-option-base.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            productSortingEntity: null,
            toBeDeletedCriteria: null,
            customFieldOptions: [],
            customFields: [],
            defaultSortingKey: null,
        };
    },

    computed: {
        productSortingRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        smartBarHeading() {
            return this.productSortingEntity && this.productSortingEntity.label ?
                this.productSortingEntity.label :
                this.$tc('sw-settings-listing.base.smartBarTitle');
        },

        isGeneralCardLoading() {
            return !this.productSortingEntity;
        },

        customFieldCriteria() {
            return new Criteria(1, 25);
        },

        productSortingEntityCriteria() {
            return new Criteria(1, 25);
        },

        isSaveButtonDisabled() {
            return !this.productSortingEntity
                || this.productSortingEntity.fields.length <= 0
                || this.productSortingEntity.fields.some(field => !field.field || field.field === 'customField');
        },

        isDefaultSorting() {
            return this.defaultSortingKey === this.productSortingEntity.key;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Promise.all([
                this.fetchProductSortingEntity(),
                this.fetchCustomFields(),
                this.fetchDefaultSorting(),
            ]);
        },

        fetchProductSortingEntity() {
            const productSortingEntityId = this.getProductSortingEntityId();

            this.productSortingRepository.get(
                productSortingEntityId,
                Shopware.Context.api,
                this.productSortingEntityCriteria,
            ).then(response => {
                if (!Array.isArray(response.fields)) {
                    response.fields = [];
                }

                this.productSortingEntity = response;
            });
        },

        fetchCustomFields() {
            return this.customFieldRepository.search(this.customFieldCriteria).then(response => {
                this.customFields = response;
            });
        },

        fetchDefaultSorting() {
            this.systemConfigApiService.getValues('core.listing')
                .then(response => {
                    this.defaultSortingKey = response['core.listing.defaultSorting'];
                });
        },

        getProductSortingEntityId() {
            return this.$route.params.id;
        },

        saveProductSorting() {
            return this.productSortingRepository.save(this.productSortingEntity);
        },

        onSave() {
            this.transformCustomFieldCriterias();

            this.productSortingEntity.fields = this.productSortingEntity.fields.filter(field => {
                return field.field !== 'customField';
            });

            return this.saveProductSorting()
                .then(() => {
                    const sortingOptionName = this.productSortingEntity.label;

                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-listing.base.notification.saveSuccess', { sortingOptionName }),
                    });
                })
                .catch(() => {
                    const sortingOptionName = this.productSortingEntity.label;

                    this.createNotificationError({
                        message: this.$t('sw-settings-listing.base.notification.saveError', { sortingOptionName }),
                    });
                });
        },

        getCriteriaTemplate(fieldName) {
            return { field: fieldName, order: 'asc', priority: 1, naturalSorting: 0 };
        },

        onDeleteCriteria(toBeRemovedItem) {
            this.toBeDeletedCriteria = toBeRemovedItem;
        },

        onConfirmDeleteCriteria() {
            // filter out criteria
            this.productSortingEntity.fields = this.productSortingEntity.fields.filter(currentCriteria => {
                return currentCriteria.field !== this.toBeDeletedCriteria.field;
            });

            // save product sorting entity
            this.saveProductSorting();

            // close delete modal
            this.toBeDeletedCriteria = null;
        },

        onAddCriteria(fieldName) {
            if (!fieldName) {
                return;
            }

            const newCriteria = this.getCriteriaTemplate(fieldName);

            if (!this.productSortingEntity.fields) {
                this.productSortingEntity.fields = [];
            }

            this.productSortingEntity.fields.push(newCriteria);
        },

        onCancelEditCriteria(item) {
            if (this.getProductSortingEntityId()) {
                this.fetchProductSortingEntity();

                return;
            }

            this.productSortingEntity.fields = this.productSortingEntity.fields.filter(currentCriteria => {
                return currentCriteria.field !== item.field;
            });
        },

        isCriteriaACustomField(technicalName) {
            return this.customFields.some(currentCustomField => {
                return currentCustomField.name === technicalName;
            });
        },

        transformCustomFieldCriterias() {
            this.productSortingEntity.fields = this.productSortingEntity.fields.map(currentField => {
                if (!this.isCriteriaACustomField(currentField.field)) {
                    return currentField;
                }

                currentField.field = `customFields.${currentField.field}`;

                return currentField;
            });
        },

        onChangeLanguage() {
            this.fetchProductSortingEntity();
        },
    },
};

