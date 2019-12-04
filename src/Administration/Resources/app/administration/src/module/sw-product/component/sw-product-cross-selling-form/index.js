import template from './sw-product-cross-selling-form.html.twig';
import './sw-product-cross-selling-form.scss';

const { Criteria } = Shopware.Data;
const { Component, Context } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

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
            productStreamFilter: []
        };
    },

    computed: {
        ...mapApiErrors('product.crossSelling', [
            'name',
            'displayType',
            'sortingType'
        ]),

        product() {
            const state = Shopware.State.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        displayTitle() {
            if (this.crossSelling._isNew) {
                return this.$tc('sw-product.crossselling.cardTitleCrossSelling');
            }

            return this.crossSelling.translated.name || this.$tc('sw-product.crossselling.cardTitleCrossSelling');
        },

        sortingTypes() {
            return [
                { value: 'price', label: this.$tc('sw-product.crossselling.priceDescendingSortingType') },
                { value: 'name', label: this.$tc('sw-product.crossselling.nameSortingType') },
                { value: 'releaseDate', label: this.$tc('sw-product.crossselling.releaseDateSortingType') }
            ];
        }
    },

    watch: {
        'crossSelling.productStreamId'() {
            this.loadStreamPreview();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadStreamPreview();
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
        }
    }
});
