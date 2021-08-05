import template from './sw-settings-product-feature-sets-modal.html.twig';
import './sw-settings-product-feature-sets-modal.scss';


const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-product-feature-sets-modal', {
    template,

    inject: ['repositoryFactory'],

    props: {
        productFeatureSet: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            showModal: false,
            featureType: null,
            selectedFeatures: new Map(),
            features: [],
            selectedFeatureType: null,
            showPageOne: true,
            term: '',
            showCustomField: false,
            showPropertyGroups: false,
            showProductInfo: false,
            nextButtonDisabled: true,
            addButtonDisabled: true,
            showNextButton: true,
            valuesLoading: false,
            customFields: [],
            propertyGroups: [],
            productInfo: [
                {
                    id: 'fc472b0728ce4000969214a0fa61f2df',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.description'),
                    name: 'description',
                },
                {
                    id: 'f64801aad24a4de7bfea4d312f957258',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.releaseDate'),
                    name: 'releaseDate',
                },
                {
                    id: 'f4a361187eac4f6ea4507ebf20c2e9d7',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.manufacturerNumber'),
                    name: 'manufacturerNumber',
                },
                {
                    id: 'eb6c8ec9b6e24811a176be5a5c9871cf',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.ean'),
                    name: 'ean',
                },
                {
                    id: '09110f8260804f009ab4536a1ffbc938',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.width'),
                    name: 'width',
                },
                {
                    id: 'e8a48d5fce2f402e8696477b03d7e8e7',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.height'),
                    name: 'height',
                },
                {
                    id: 'e4cf3f607a704f569c3912fb85ada9ad',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.length'),
                    name: 'length',
                },
                {
                    id: 'e06c53dc014a4130a8850fe64e395046',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.label.weight'),
                    name: 'weight',
                },
            ],
        };
    },

    computed: {
        productFeatureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        customFieldsRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        propertyGroupsRepository() {
            return this.repositoryFactory.create('property_group');
        },

        productFeatureSetCriteria() {
            return new Criteria();
        },

        customFieldCriteria() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('type', 'DESC'));

            const featureIds = this.getFeaturesIds('customField');
            if (featureIds.length > 0) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', featureIds)]));
            }

            return criteria;
        },

        propertyGroupCriteria() {
            const criteria = new Criteria();

            const featureIds = this.getFeaturesIds('property');
            if (featureIds.length > 0) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', featureIds)]));
            }

            return criteria;
        },

        referencePriceSelected() {
            return this.selectedFeatureType === 'referencePrice';
        },

        propertyGroupColumns() {
            return this.getPropertyGroupColumns();
        },

        customFieldColumns() {
            return this.getCustomFieldColumns();
        },

        productInformationColumns() {
            return this.getProductInformationColumns();
        },

        checkIfReferencePriceSelected() {
            if (!this.productFeatureSet.features) {
                return false;
            }

            return this.productFeatureSet.features.filter((item) => {
                return (item.type === 'referencePrice');
            }).length === 1;
        },

        settingOptions() {
            return [
                {
                    value: 'property',
                    disabled: this.propertyGroups.length < 1,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textPropertyLabel'),
                },
                {
                    value: 'customField',
                    disabled: this.customFields.length < 1,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textCustomFieldLabel'),
                },
                {
                    value: 'product',
                    disabled: this.productInfo.length < 1,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textProductInfoLabel'),
                },
                {
                    value: 'referencePrice',
                    disabled: this.checkIfReferencePriceSelected,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textReferencePriceLabel'),
                },
            ];
        },

        customFieldTotal() {
            return this.customFields.total || 0;
        },

        propertyGroupTotal() {
            return this.propertyGroups.total || 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.productFeatureSet.features) {
                this.features = this.productFeatureSet.features;
            }

            this.customFieldCriteria.setLimit(10);
            this.propertyGroupCriteria.setLimit(10);

            this.getCustomFieldList();
            this.getPropertyList();
            this.getProductInformationList();
        },

        onSearchCustomFields() {
            this.customFieldCriteria.setTerm(this.term);
            this.getCustomFieldList();
        },

        onSearchPropertyGroups() {
            this.propertyGroupCriteria.setTerm(this.term);
            this.getPropertyList();
        },

        onClickNext() {
            this.showPageOne = false;
            this.showNextButton = false;
            this.featureType = this.selectedFeatureType;

            switch (this.selectedFeatureType) {
                case 'customField':
                    this.showCustomField = true;
                    break;
                case 'property':
                    this.showPropertyGroups = true;
                    break;
                case 'product':
                    this.showProductInfo = true;
                    break;
                default:
                    break;
            }
        },

        getProductInformationList() {
            if (!this.productFeatureSet.features) {
                return;
            }

            const featureNames = this.productFeatureSet.features.map(a => a.name);

            this.productInfo = this.productInfo.filter((item) => {
                return !(item.type === 'product' && featureNames.includes(item.name));
            });
        },

        getCustomFieldList() {
            return this.getList(
                this.customFieldsRepository,
                this.customFieldCriteria,
                (items) => {
                    this.customFields = items;
                    this.valuesLoading = false;
                },
            );
        },

        getPropertyList() {
            return this.getList(
                this.propertyGroupsRepository,
                this.propertyGroupCriteria,
                (items) => {
                    this.propertyGroups = items;
                    this.valuesLoading = false;
                },
            );
        },

        getList(repository, criteria, callback) {
            this.valuesLoading = true;

            return repository.search(criteria, Shopware.Context.api).then(callback).catch(() => {
                this.valuesLoading = false;
            });
        },

        getFeaturesIds(type) {
            if (!this.productFeatureSet.features) {
                return [];
            }

            return this.productFeatureSet.features.filter((feature) => {
                return feature.type === type;
            }).map(a => a.id);
        },

        onChangeOption() {
            this.checkIfReferencePriceIsSelected();

            this.addButtonDisabled = !this.referencePriceSelected;

            if (this.nextButtonDisabled) {
                this.nextButtonDisabled = false;
            }
        },

        checkIfReferencePriceIsSelected() {
            this.showNextButton = !this.referencePriceSelected;
        },

        onConfirm() {
            if (this.referencePriceSelected) {
                this.features.push({
                    id: 'd45b40f6a99c4c2abe66c410369b9d3c',
                    name: 'referencePrice',
                    type: 'referencePrice',
                    position: this.features.length + 1,
                });
            } else {
                this.selectedFeatures.forEach(features => this.setFeatures(features));
            }

            this.productFeatureSet.features = this.features;
            this.productFeatureSetRepository.save(this.productFeatureSet, Context.api).then(() => {
                this.isSaveSuccessful = true;
                this.featureType = null;
                this.$emit('modal-close');
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.unspecifiedSaveErrorMessage',
                    ),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        setFeatures(features) {
            Object.keys(features).forEach((key) => {
                this.features.push({
                    id: features[key].id,
                    name: features[key].name,
                    type: this.selectedFeatureType,
                    position: this.features.length + 1,
                });
            });
        },

        setFeatureSelection(features, count) {
            // Return early if we do not need to take pagination/preselection into account
            if (!(this.showCustomField || this.showPropertyGroups)) {
                if (count < 1) {
                    this.addButtonDisabled = true;

                    return;
                }

                this.selectedFeatures.set(1, features);
                this.addButtonDisabled = false;

                return;
            }

            const criteria = this.showCustomField ? this.customFieldCriteria : this.propertyGroupCriteria;
            const page = criteria.page;
            const preSelected = this.selectedFeatures.get(page);

            // Apply the current selection, if there wasn't anything selected on this page before
            if (!preSelected || Object.keys(preSelected).length < 1) {
                this.selectedFeatures.set(page, features);
                this.addButtonDisabled = false;

                return;
            }

            const grid = this.showCustomField ? this.$refs.customFieldGrid : this.$refs.propertyGroupGrid;

            // Mark all preselected items as selected
            Object.keys(preSelected).forEach(key => grid.selectItem(true, preSelected[key]));
        },

        getPropertyGroupColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-product-feature-sets.modal.textPropertyLabel',
                primary: true,
            }];
        },

        getCustomFieldColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-product-feature-sets.modal.labelName',
                primary: true,
            }, {
                property: 'type',
                label: 'sw-settings-product-feature-sets.valuesCard.labelType',
            }];
        },

        getProductInformationColumns() {
            return [{
                property: 'label',
                label: 'sw-settings-product-feature-sets.modal.labelName',
                primary: true,
            }];
        },

        paginateCustomFieldGrid({ page, limit }) {
            this.customFieldCriteria.setPage(page);
            this.customFieldCriteria.setLimit(limit);

            this.getCustomFieldList();
        },

        paginatePropertyGroupGrid({ page, limit }) {
            this.propertyGroupCriteria.setPage(page);
            this.propertyGroupCriteria.setLimit(limit);

            this.getPropertyList();
        },

        readCustomFieldLabel(field) {
            const language = Shopware.State.get('session').currentLocale;
            const fallback = Shopware.Context.app.fallbackLocale;

            return field.config.label[language] || field.config.label[fallback];
        },
    },
});
