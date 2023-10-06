import template from './sw-product-cross-selling-form.html.twig';
import './sw-product-cross-selling-form.scss';

const { Criteria } = Shopware.Data;
const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapGetters, mapState } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'productStreamConditionService'],

    provide() {
        return {
            productCustomFields: {},
        };
    },

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        crossSelling: {
            type: Object,
            required: true,
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
            showDeleteModal: false,
            showModalPreview: false,
            productStream: null,
            productStreamFilter: [],
            productStreamFilterTree: null,
            optionSearchTerm: '',
            useManualAssignment: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            assignmentKey: 0,
        };
    },

    computed: {
        ...mapPropertyErrors('crossSelling', [
            'name',
            'type',
            'position',
        ]),

        ...mapState('swProductDetail', [
            'product',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
        ]),

        productCrossSellingRepository() {
            return this.repositoryFactory.create('product_cross_selling');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productStreamFilterRepository() {
            if (!this.productStream) {
                return null;
            }

            const { entity, source } = this.productStream.filters;

            return this.repositoryFactory.create(entity, source);
        },

        productStreamFilterCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.equals('productStreamId', this.crossSelling.productStreamId),
            );

            return criteria;
        },

        crossSellingAssigmentRepository() {
            return this.repositoryFactory.create('product_cross_selling_assigned_products');
        },

        sortingTypes() {
            return [{
                label: this.$tc('sw-product.crossselling.priceDescendingSortingType'),
                value: 'price:DESC',
            }, {
                label: this.$tc('sw-product.crossselling.priceAscendingSortingType'),
                value: 'price:ASC',
            }, {
                label: this.$tc('sw-product.crossselling.nameSortingType'),
                value: 'name:ASC',
            }, {
                label: this.$tc('sw-product.crossselling.releaseDateDescendingSortingType'),
                value: 'releaseDate:DESC',
            }, {
                label: this.$tc('sw-product.crossselling.releaseDateAscendingSortingType'),
                value: 'releaseDate:ASC',
            }];
        },

        crossSellingTypes() {
            return [{
                label: this.$tc('sw-product.crossselling.productStreamType'),
                value: 'productStream',
            }, {
                label: this.$tc('sw-product.crossselling.productListType'),
                value: 'productList',
            }];
        },

        previewDisabled() {
            return !this.productStream;
        },

        sortingConCat() {
            return `${this.crossSelling.sortBy}:${this.crossSelling.sortDirection}`;
        },

        disablePositioning() {
            return (!!this.term) || (this.sortBy !== 'position');
        },

        associationValue() {
            return this.crossSelling?.productStreamId || '';
        },
    },

    watch: {
        'crossSelling.productStreamId'() {
            if (!this.useManualAssignment) {
                this.loadStreamPreview();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.useManualAssignment = this.crossSelling.type === 'productList';
            if (!this.useManualAssignment && this.crossSelling.productStreamId !== null) {
                this.loadStreamPreview();
            }
        },

        onShowDeleteModal() {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.$nextTick(() => {
                this.product.crossSellings.remove(this.crossSelling.id);
            });
        },

        openModalPreview() {
            if (this.previewDisabled) {
                return;
            }

            this.showModalPreview = true;
        },

        closeModalPreview() {
            this.showModalPreview = false;
        },

        loadStreamPreview() {
            this.productStreamRepository.get(this.crossSelling.productStreamId)
                .then((productStream) => {
                    this.productStream = productStream;
                    this.getProductStreamFilter();
                });
        },

        getProductStreamFilter() {
            if (this.productStreamFilterRepository === null) {
                return [];
            }
            return this.productStreamFilterRepository.search(this.productStreamFilterCriteria)
                .then((productStreamFilter) => {
                    this.productStreamFilter = productStreamFilter;
                });
        },

        updateProductStreamFilterTree({ conditions }) {
            this.productStreamFilterTree = conditions;
        },

        onSortingChanged(value) {
            [this.crossSelling.sortBy, this.crossSelling.sortDirection] = value.split(':');
        },

        onTypeChanged(value) {
            this.useManualAssignment = value === 'productList';
        },
    },
};
