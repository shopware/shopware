import template from './sw-product-detail-cross-selling.html.twig';
import './sw-product-detail-cross-selling.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-cross-selling', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            crossSelling: null
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ])
    },

    methods: {
        onAddCrossSelling() {
            const crossSellingRepository = this.repositoryFactory.create(
                this.product.crossSellings.entity,
                this.product.crossSellings.source
            );
            this.crossSelling = crossSellingRepository.create(Shopware.Context.api);
            this.crossSelling.productId = this.product.id;
            this.crossSelling.position = this.product.crossSellings.length + 1;
            this.crossSelling.type = 'productStream';
            this.crossSelling.sortBy = 'name';
            this.crossSelling.sortDirection = 'ASC';
            this.crossSelling.limit = 24;

            this.product.crossSellings.push(this.crossSelling);
        }
    }
});
