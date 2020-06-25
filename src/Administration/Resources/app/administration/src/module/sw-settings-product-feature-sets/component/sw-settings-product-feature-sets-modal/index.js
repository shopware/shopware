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
            required: true
        }
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
        basePriceSelected() {
            return this.selectedFeatureType === 'basePrice';
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
        checkIfBasePriceSelected() {
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
                    name: this.$tc('sw-settings-product-feature-sets.modal.textPropertyLabel')
                },
                {
                    value: 'customField',
                    disabled: this.customFields.length < 1,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textCustomFieldLabel')
                },
                {
                    value: 'productInformation',
                    disabled: this.productInformations.length < 1,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textProductInfoLabel')
                },
                {
                    value: 'basePrice',
                    disabled: this.checkIfBasePriceSelected,
                    name: this.$tc('sw-settings-product-feature-sets.modal.textBasePriceLabel')
                }
            ];
        }
    },

    data() {
        return {
            showModal: false,
            featureType: null,
            selectedFeatures: null,
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
            productInformations: [
                {
                    id: 'fd32132e205b4145af0ddfbb451ce64d',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelManufacturer'),
                    name: 'manufacturer'
                },
                {
                    id: 'fc472b0728ce4000969214a0fa61f2df',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelDescription'),
                    name: 'description'
                },
                {
                    id: 'f64801aad24a4de7bfea4d312f957258',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelReleaseDate'),
                    name: 'releaseDate'
                },
                {
                    id: 'f4a361187eac4f6ea4507ebf20c2e9d7',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelManufacturerNumber'),
                    name: 'manufacturerNumber'
                },
                {
                    id: 'eb6c8ec9b6e24811a176be5a5c9871cf',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelEan'),
                    name: 'ean'
                },
                {
                    id: '09110f8260804f009ab4536a1ffbc938',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelWidth'),
                    name: 'width'
                },
                {
                    id: 'e8a48d5fce2f402e8696477b03d7e8e7',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelHeight'),
                    name: 'height'
                },
                {
                    id: 'e4cf3f607a704f569c3912fb85ada9ad',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelLength'),
                    name: 'length'
                },
                {
                    id: 'e06c53dc014a4130a8850fe64e395046',
                    type: 'product',
                    label: this.$tc('sw-settings-product-feature-sets.modal.labelWeight'),
                    name: 'weight'
                }
            ]
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.productFeatureSet.features) {
                this.features = this.productFeatureSet.features;
            }

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
                case 'productInformation':
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

            this.productInformations = this.productInformations.filter((item) => {
                return !(item.type === 'product' && featureNames.includes(item.name));
            });
        },

        getCustomFieldList() {
            this.valuesLoading = true;
            this.customFieldsRepository.search(this.customFieldCriteria, Shopware.Context.api).then((items) => {
                this.customFields = items;

                this.valuesLoading = false;
                return items;
            }).catch(() => {
                this.valuesLoading = false;
            });
        },

        getPropertyList() {
            this.valuesLoading = true;
            this.propertyGroupsRepository.search(this.propertyGroupCriteria, Shopware.Context.api).then((items) => {
                this.propertyGroups = items;

                this.valuesLoading = false;
            }).catch(() => {
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
            this.checkIfBasePriceIsSelected();

            this.addButtonDisabled = !this.basePriceSelected;

            if (this.nextButtonDisabled) {
                this.nextButtonDisabled = false;
            }
        },

        checkIfBasePriceIsSelected() {
            this.showNextButton = !this.basePriceSelected;
        },

        onConfirm() {
            if (this.basePriceSelected) {
                this.features.push({
                    id: 'd45b40f6a99c4c2abe66c410369b9d3c',
                    name: 'basePrice',
                    type: 'referencePrice',
                    position: this.features.length + 1
                });
            } else {
                this.setFeatures(this.selectedFeatures);
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
                        'global.notification.unspecifiedSaveErrorMessage'
                    )
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
                    position: this.features.length + 1
                });
            });
        },

        setFeatureSelection(features, count) {
            if (count < 1) {
                this.addButtonDisabled = true;
                this.selectedFeatures = null;
                return;
            }

            this.selectedFeatures = features;
            this.addButtonDisabled = false;
        },

        getPropertyGroupColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-product-feature-sets.modal.labelProperty',
                primary: true
            }];
        },

        getCustomFieldColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-product-feature-sets.modal.labelName',
                primary: true
            }, {
                property: 'type',
                label: 'sw-settings-product-feature-sets.valuesCard.labelType'
            }];
        },

        getProductInformationColumns() {
            return [{
                property: 'label',
                label: 'sw-settings-product-feature-sets.modal.labelName',
                primary: true
            }];
        }
    }
});
