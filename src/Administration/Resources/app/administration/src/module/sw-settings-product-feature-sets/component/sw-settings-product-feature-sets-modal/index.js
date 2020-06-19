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
            return this.repositoryFactory.create('product_feature_sets');
        },
        customFieldsRepository() {
            return this.repositoryFactory.create('custom_field');
        },
        propertyGroupsRepository() {
            return this.repositoryFactory.create('property_group');
        },
        productFeatureSetCriteria() {
            const criteria = new Criteria();

            return criteria;
        },
        customFieldCriteria() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('type', 'DESC'));
            return criteria;
        },
        propertyGroupCriteria() {
            const criteria = new Criteria();
            return criteria;
        },
        basePriceSelected() {
            return this.selectedSettingOption === 'basePrice';
        },
        getCustomFieldColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-product-feature-sets.modal.labelName',
                primary: true
            }, {
                property: 'type',
                dataIndex: 'type',
                label: 'sw-settings-product-feature-sets.valuesCard.labelType'
            }];
        },
        getPropertyGroupColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-product-feature-sets.modal.labelProperty',
                primary: true
            }];
        }
    },

    data() {
        return {
            showModal: false,
            feature: null,
            selectedSettingOption: null,
            showPageOne: true,
            term: '',
            showCustomField: false,
            showPropertyGroups: false,
            showProductInfo: false,
            nextButtonDisabled: true,
            showNextButton: true,
            valuesLoading: false,
            customFields: [],
            propertyGroups: [],
            settingOptions: [
                {
                    value: 'property',
                    name: this.$tc('sw-settings-product-feature-sets.modal.textPropertyLabel')
                },
                {
                    value: 'customField',
                    name: this.$tc('sw-settings-product-feature-sets.modal.textCustomFieldLabel')
                },
                {
                    value: 'productInformation',
                    name: this.$tc('sw-settings-product-feature-sets.modal.textProductInfoLabel')
                },
                {
                    value: 'basePrice',
                    name: this.$tc('sw-settings-product-feature-sets.modal.textBasePriceLabel')
                }
            ],
            currentFeature: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.currentFeature) {
                this.feature = this.currentFeature;
            } else {
                this.feature = {
                    id: [],
                    type: null,
                    position: 1
                };
            }
            this.getCustomFieldList();
            this.getPropertyList();
        },

        onSearchCustomFields() {
            this.customFieldCriteria.setTerm(this.term);
            this.getCustomFieldList();
        },

        onSearchPropertyGroups() {
            this.propertyGroupCriteria.setTerm(this.term);
            this.getPropertyList();
        },

        onChangeFeatureType(type) {
            this.feature.type = type;
        },

        onChangeFeatureId(id) {
            this.feature.id.push(id);
        },

        onClickNext() {
            this.showPageOne = false;
            this.showNextButton = false;
            switch (this.selectedSettingOption) {
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
                return items;
            }).catch(() => {
                this.valuesLoading = false;
            });
        },

        onChangeOption() {
            this.checkIfBasePriceIsSelected();
            if (this.nextButtonDisabled) {
                this.nextButtonDisabled = false;
            }
        },

        checkIfBasePriceIsSelected() {
            this.showNextButton = !this.basePriceSelected;
        },

        onConfirm() {
            if (this.basePriceSelected) {
                this.onChangeFeatureType('basePrice');
                this.onChangeFeatureId('referencePrice');
            }
            this.productFeatureSet.features.add(this.feature);
            this.productFeatureSetRepository.save(this.productFeatureSet, Context.api).then(() => {
                this.isSaveSuccessful = true;
                this.$emit('modal-close');
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
