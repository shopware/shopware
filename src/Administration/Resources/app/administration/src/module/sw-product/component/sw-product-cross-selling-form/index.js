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
            productStreamFilter: [],
            sortBy: 'name',
            sortDirection: 'ASC'
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

        previewDisabled() {
            return !this.productStream;
        },

        sortingConCat() {
            return `${this.crossSelling.sortBy}:${this.crossSelling.sortDirection}`;
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
        }
    }
});
