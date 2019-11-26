import template from './sw-product-detail-cross-selling.html.twig';
import './sw-product-detail-cross-selling.scss';

const {Component} = Shopware;
const {mapState} = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-cross-selling', {
    inject: ['repositoryFactory'],
    template,

    data() {
        return {
            crossSelling: null
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ]),

        crossSellings: {
            get() {
                return this.product.crossSellings;
            },
            set() {
                this.product.add(this.crossSellings);
            }
        }
    },

    methods: {
        onAddCrossSelling() {
            const crossSellingRepository = this.repositoryFactory.create(
                this.crossSellings.entity,
                this.crossSellings.source
            );
            this.crossSelling = crossSellingRepository.create(Shopware.Context.api);
            this.crossSelling.productId = this.product.id;
            this.crossSelling.sortBy = 'popularity';

            this.crossSellings.push(this.crossSelling);
        }
    }
});
