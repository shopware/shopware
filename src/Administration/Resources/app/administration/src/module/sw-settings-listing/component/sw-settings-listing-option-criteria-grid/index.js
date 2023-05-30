import template from './sw-settings-listing-option-criteria-grid.html.twig';
import './sw-settings-listing-option-criteria-grid.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        productSortingEntity: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            customFields: [],
            selectedCriteria: null,
            customFieldSetIDs: null,
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetRelationsRepository() {
            return this.repositoryFactory.create('custom_field_set_relation');
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.customFieldSetIDs) {
                criteria.addFilter(
                    Criteria.equalsAny('customFieldSetId', this.customFieldSetIDs),
                );
            }

            if (this.getProductSortingFieldsByName().length) {
                criteria.addFilter(
                    Criteria.equalsAny('id', this.getProductSortingFieldsByName()),
                );
            }

            return criteria;
        },

        customFieldsRelationsCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('entityName', 'product'));

            return criteria;
        },

        /**
         * Sorts custom fields by their priority in an ascending order.
         * @returns {[]}
         */
        sortedProductSortingFields() {
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            return this.productSortingEntity.fields.sort((a, b) => {
                if (a.priority === b.priority) {
                    return 0;
                }

                return a.priority < b.priority ? 1 : -1;
            });
        },

        productSortingEntityColumns() {
            return [
                {
                    property: 'field',
                    label: this.$tc('sw-settings-listing.general.productSortingCriteriaGrid.header.name'),
                    inlineEdit: 'string',
                },
                {
                    property: 'order',
                    label: this.$tc('sw-settings-listing.general.productSortingCriteriaGrid.header.order'),
                    inlineEdit: 'string',
                },
                {
                    property: 'priority',
                    label: this.$tc('sw-settings-listing.general.productSortingCriteriaGrid.header.priority'),
                    inlineEdit: 'number',
                },
            ];
        },

        criteriaOptions() {
            const criteriaOptions = [
                {
                    value: 'product.name',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.name',
                    ),
                },
                {
                    value: 'product.ratingAverage',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.ratingAverage',
                    ),
                },
                {
                    value: 'product.productNumber',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.productNumber',
                    ),
                },
                {
                    value: 'product.releaseDate',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.releaseDate',
                    ),
                },
                {
                    value: 'product.stock',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.stock',
                    ),
                },
                {
                    value: 'product.sales',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.sales',
                    ),
                },
                {
                    value: 'customField',
                    label: this.$tc('sw-settings-listing.general.productSortingCriteriaGrid.options.label.customField'),
                },
                {
                    value: 'product.cheapestPrice',
                    label: this.$tc(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.cheapestPrice',
                    ),
                },
                {
                    value: 'product.price',
                    label: this.$tc('sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.price'),
                },
            ];

            return criteriaOptions.sort((a, b) => {
                return a.label.localeCompare(b.label);
            });
        },

        orderOptions() {
            return [
                {
                    label: this.$tc('global.default.ascending'),
                    value: 'asc',
                },
                {
                    label: this.$tc('global.default.descending'),
                    value: 'desc',
                },
            ];
        },
    },

    watch: {
        productSortingEntity: {
            handler() {
                if (!this.productSortingEntity || !this.productSortingEntity.fields) {
                    return;
                }

                this.productSortingEntity.fields.forEach(field => {
                    if (field.field === null) {
                        field.field = 'customField';
                    }
                });
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCustomFieldSetIds().then(() => {
                this.fetchCustomFields();
            });
        },

        fetchCustomFieldSetIds() {
            return this.customFieldSetRelationsRepository.search(this.customFieldsRelationsCriteria).then(response => {
                this.customFieldSetIDs = response.map(currentField => {
                    return currentField.customFieldSetId;
                });
            });
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria).then(response => {
                this.customFields = response;
            });
        },

        /**
         * Checks if the given field is a custom field.
         * @param {string} fieldName
         * @returns {boolean}
         */
        isItemACustomField(fieldName) {
            const strippedFieldName = this.stripCustomFieldPath(fieldName);

            return this.customFields.some(currentCustomField => {
                return currentCustomField.name === strippedFieldName;
            });
        },

        getCustomFieldByName(technicalName) {
            return this.customFields.find(currentCustomField => {
                return currentCustomField.name === technicalName;
            });
        },

        /**
         * First checks if the newly added criteria is already used. If not it emits an 'criteria-add' event.
         * Otherwise it creates an error notification.
         * @param {string} fieldName
         */
        onAddCriteria(fieldName) {
            if (!this.criteriaIsAlreadyUsed(fieldName)) {
                this.$emit('criteria-add', fieldName);

                const record = this.productSortingEntity.fields.find(field => field.field === fieldName);
                this.$nextTick().then(() => {
                    if (record && this.$refs.dataGrid) {
                        this.$refs.dataGrid.onDbClickCell(record);
                    }
                });

                return;
            }

            const criteriaName = this.getCriteriaSnippetByFieldName(fieldName);

            this.createNotificationError({
                message: this.$t(
                    'sw-settings-listing.general.productSortingCriteriaGrid.options.criteriaAlreadyUsed',
                    { criteriaName },
                ),
            });
        },

        getOrderSnippet(order) {
            if (order === 'asc') {
                return this.$tc('global.default.ascending');
            }

            return this.$tc('global.default.descending');
        },

        onRemoveCriteria(item) {
            this.$emit('criteria-delete', item);
        },

        getCriteriaTemplate(fieldName) {
            return { field: fieldName, order: 'asc', priority: 1, naturalSorting: 0 };
        },

        onSaveInlineEdit(item) {
            if (item.field === null) {
                this.createNotificationError({
                    message: this.$t(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.customFieldCriteriaNotNull',
                    ),
                });

                return;
            }

            if (item.field === 'customFields') {
                item.field = `customFields.${item.field}`;
            }

            if (item.field === 'customField') {
                this.createNotificationError({
                    message: this.$t(
                        'sw-settings-listing.general.productSortingCriteriaGrid.options.customFieldCriteriaNotNull',
                    ),
                });

                this.filterEmptyCustomFields(item);
                return;
            }

            this.$emit('inline-edit-save');
        },

        onCancelInlineEdit(item) {
            if (item && item.field === 'customField') {
                this.filterEmptyCustomFields(item);
            } else {
                this.$emit('inline-edit-cancel', item);
            }
        },

        filterEmptyCustomFields(item) {
            this.productSortingEntity.fields = this.productSortingEntity.fields.filter(field => {
                return field.field !== item.field;
            });
        },

        /**
         * removes the stripCustomFieldPath `customFields.` part of the string.
         * @param {string} fieldName
         * @returns {string}
         */
        stripCustomFieldPath(fieldName) {
            return fieldName.replace(/customFields\./, '');
        },

        /**
         * Returns the snippet of the corresponding field.
         * @param {string} fieldName
         * @returns {string}
         */
        getCriteriaSnippetByFieldName(fieldName) {
            return this.$tc(`sw-settings-listing.general.productSortingCriteriaGrid.options.label.${fieldName}`);
        },

        criteriaIsAlreadyUsed(criteriaName) {
            return this.productSortingEntity.fields.some(currentCriteria => {
                return currentCriteria.field === criteriaName;
            });
        },

        getCustomFieldLabelByCriteriaName(criteriaName) {
            const technicalName = this.stripCustomFieldPath(criteriaName);
            const customField = this.getCustomFieldByName(technicalName);

            return this.getInlineSnippet(customField.config.label) || technicalName;
        },

        getCustomFieldName(customField) {
            const inlineSnippet = customField && this.getInlineSnippet(customField.config.label);

            if (!inlineSnippet) {
                return customField?.name;
            }

            return inlineSnippet;
        },

        customFieldCriteriaSingleSelect(customField) {
            const criteria = new Criteria(1, 25);

            if (this.customFieldSetIDs) {
                criteria.addFilter(
                    Criteria.equalsAny('customFieldSetId', this.customFieldSetIDs),
                );
            }

            if (this.getProductSortingFieldsByName(customField).length) {
                criteria.addFilter(Criteria.not(
                    'AND',
                    [
                        Criteria.equalsAny('id', this.getProductSortingFieldsByName(customField)),
                    ],
                ));
            }

            return criteria;
        },

        async changeCustomField(key, field) {
            const customField = this.sortedProductSortingFields.find((item) => {
                return item.field === 'customField' || item.name === key;
            });

            customField.field = `customFields.${field.name}`;

            await this.fetchCustomFields();

            this.onSaveInlineEdit(customField.field);
        },

        getProductSortingFieldsByName(customField = null) {
            return this.sortedProductSortingFields.filter((item) => {
                if (customField) {
                    return /^customFields\./.test(item.field) && item.field !== customField.field;
                }

                return /^customFields\./.test(item.field);
            }).map(item => item.name) || {};
        },
    },
};
