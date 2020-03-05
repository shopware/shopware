import template from './sw-product-cross-selling-form.html.twig';
import './sw-product-cross-selling-form.scss';

const { Criteria } = Shopware.Data;
const { Component, Context } = Shopware;
const { mapPropertyErrors, mapGetters, mapState } = Component.getComponentHelper();

Component.register('sw-product-cross-selling-form', {
    template,

    inject: ['repositoryFactory'],

    props: {
        crossSelling: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            showDeleteModal: false,
            showModalPreview: false,
            productStream: null,
            productStreamFilter: [],
            optionSearchTerm: '',
            useManualAssignment: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            assignmentKey: 0
        };
    },

    computed: {
        ...mapPropertyErrors('crossSellings', [
            'name',
            'type',
            'position'
        ]),

        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        productCrossSellingRepository() {
            return this.repositoryFactory.create('product_cross_selling');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        crossSellingAssigmentRepository() {
            return this.repositoryFactory.create('product_cross_selling_assigned_products');
        },

        sortingTypes() {
            return [{
                label: this.$tc('sw-product.crossselling.priceDescendingSortingType'),
                value: 'price:DESC'
            }, {
                label: this.$tc('sw-product.crossselling.priceAscendingSortingType'),
                value: 'price:ASC'
            }, {
                label: this.$tc('sw-product.crossselling.nameSortingType'),
                value: 'name:ASC'
            }, {
                label: this.$tc('sw-product.crossselling.releaseDateDescendingSortingType'),
                value: 'releaseDate:DESC'
            }, {
                label: this.$tc('sw-product.crossselling.releaseDateAscendingSortingType'),
                value: 'releaseDate:ASC'
            }];
        },

        crossSellingTypes() {
            return [{
                label: this.$tc('sw-product.crossselling.productStreamType'),
                value: 'productStream'
            }, {
                label: this.$tc('sw-product.crossselling.productListType'),
                value: 'productList'
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
        }
    },

    watch: {
        'crossSelling.productStreamId'() {
            if (!this.useManualAssignment) {
                this.loadStreamPreview();
            }
        }
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

            this.loadStreamPreview();
            this.showModalPreview = true;
        },

        closeModalPreview() {
            this.showModalPreview = false;
        },

        loadStreamPreview() {
            this.productStreamRepository.get(this.crossSelling.productStreamId, Shopware.Context.api)
                .then((searchResult) => {
                    this.productStream = searchResult;

                    const filterRepository = this.repositoryFactory.create(
                        this.productStream.filters.entity,
                        this.productStream.filters.source,
                    );

                    const criteria = new Criteria();
                    criteria.addFilter(Criteria.equals('productStreamId', this.crossSelling.productStreamId));

                    return filterRepository.search(criteria, Context.api).then((productFilter) => {
                        this.productStreamFilter = productFilter;
                    });
                });
        },

        onSortingChanged(value) {
            [this.crossSelling.sortBy, this.crossSelling.sortDirection] = value.split(':');
        },

        onTypeChanged(value) {
            this.useManualAssignment = value === 'productList';
        }
    }
});
