// eslint-disable-next-line max-len
import FeatureGridTranslationService from 'src/module/sw-settings-product-feature-sets/service/feature-grid-translation.service';
import template from './sw-settings-product-feature-sets-values-card.html.twig';
import './sw-settings-product-feature-sets-values-card.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        productFeatureSet: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            valuesLoading: false,
            cardLoading: false,
            values: [],
            selection: null,
            deleteButtonDisabled: true,
            term: '',
            showModal: false,
            currentValue: null,
            translationService: null,
        };
    },

    computed: {
        productFeatureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        valuesEmpty() {
            return this.values.length === 0;
        },

        valuesCardClasses() {
            return {
                'is--empty': this.valuesEmpty,
            };
        },

        productFeatureSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('product_feature_set.id', this.productFeatureSet.id));

            return criteria;
        },

        featureGridTranslationService() {
            if (this.translationService === null) {
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                this.translationService = new FeatureGridTranslationService(
                    this,
                    this.propertyGroupRepository,
                    this.customFieldRepository,
                );
            }

            return this.translationService;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        onAddField() {
            this.onShowFeatureModal();
        },

        onGridSelectionChanged(selection, selectionCount) {
            this.selection = selection;
            this.deleteButtonDisabled = selectionCount <= 0;
        },

        onSearch() {
            this.doSearch();
        },

        doSearch() {
            if (!this.term) {
                this.getList();
            }

            this.values = this.productFeatureSet.features.filter((item) => {
                return item.name.match(this.term) || item.type.match(this.term);
            });
        },

        getList() {
            this.valuesLoading = true;
            this.values = [];

            if (this.productFeatureSet.features) {
                this.values = this.productFeatureSet.features;

                if (this.term) {
                    this.doSearch();
                }
            }

            // Initially sort the features by position, further sorting will be handled by the grid component
            this.values.sort((a, b) => a.position - b.position);

            Promise.all([
                this.featureGridTranslationService.fetchPropertyGroupEntities(this.values),
                this.featureGridTranslationService.fetchCustomFieldEntities(this.values),
            ]).then(() => {
                this.valuesLoading = false;
            });
        },

        onModalClose() {
            this.showModal = false;
            this.currentValue = null;
            this.$nextTick(() => this.getList());
        },

        onShowFeatureModal() {
            this.showModal = true;
        },

        onDeleteFields() {
            if (this.selection) {
                const deletedKeys = Object.keys(this.selection);

                this.productFeatureSet.features = this.productFeatureSet.features.filter((feature) => {
                    return !deletedKeys.includes(feature.id);
                });

                this.resetPositions();
                this.getList();
            }
        },

        onPositionChange(features) {
            this.$set(this.productFeatureSet, 'features', features);
        },

        resetPositions() {
            this.productFeatureSet.features.forEach((feature, index) => {
                feature.position = index + 1;
            });
        },

        getColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-product-feature-sets.valuesCard.labelValue',
                primary: true,
            }, {
                property: 'type',
                label: 'sw-settings-product-feature-sets.valuesCard.labelType',
            }, {
                property: 'position',
                label: 'sw-settings-product-feature-sets.valuesCard.labelPosition',
            }];
        },
    },
};
