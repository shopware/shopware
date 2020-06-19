import template from './sw-settings-product-feature-sets-values-card.html.twig';
import './sw-settings-product-feature-sets-values-card.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-product-feature-sets-values-card', {
    template,

    inject: [
        'repositoryFactory'
    ],

    props: {
        productFeatureSet: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
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
            showDeleteModal: false,
            currentValue: null
        };
    },

    computed: {
        productFeatureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        valuesEmpty() {
            return this.values.length === 0;
        },

        valuesCardClasses() {
            return {
                'sw-settings-product-feature-sets-values-card--is-empty': this.productFeatureSetsEmpty
            };
        },

        productFeatureSetCriteria() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('product_feature_set.features.position', 'ASC'));
            criteria.addFilter(Criteria.equals('product_feature_set.id', this.productFeatureSet.id));

            return criteria;
        },

        getColumns() {
            return [{
                property: 'id',
                dataIndex: 'id',
                label: 'sw-settings-product-feature-sets.valuesCard.labelValues',
                primary: true
            }, {
                property: 'type',
                dataIndex: 'type',
                label: 'sw-settings-product-feature-sets.valuesCard.labelType'
            }, {
                property: 'position',
                label: 'sw-settings-product-feature-sets.valuesCard.labelPosition'
            }];
        }
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
            this.productFeatureSetCriteria.setTerm(this.term);
            this.getList();
        },

        getList() {
            this.valuesLoading = true;
            this.values = [];
            this.values.push(this.productFeatureSet.features);

            this.valuesLoading = false;
        },

        onModalClose() {
            this.showModal = false;
            this.currentValue = null;
            this.$nextTick(() => this.getList());
        },

        onShowFeatureModal() {
            this.showModal = true;
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onDeleteFields() {
            if (this.selection) {
                Object.values(this.selection).forEach((field) => {
                    this.onDelete(field);
                });
                this.getList();
            }
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.productFeatureSetRepository.delete(id, Context.api).then(() => {
                this.getList();
            });
        }
    }
});
