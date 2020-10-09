import template from './sw-product-detail-cross-selling.html.twig';
import './sw-product-detail-cross-selling.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-detail-cross-selling', {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

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

    watch: {
        product(product) {
            product.crossSellings.forEach((item) => {
                if (item.assignedProducts.length > 0) {
                    return;
                }

                this.loadAssignedProducts(item);
            });
        }
    },

    methods: {
        loadAssignedProducts(crossSelling) {
            const repository = this.repositoryFactory.create(
                crossSelling.assignedProducts.entity,
                crossSelling.assignedProducts.source
            );

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('crossSellingId', crossSelling.id))
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('product');

            repository.search(
                criteria,
                { ...Shopware.Context.api, inheritance: true }
            ).then((assignedProducts) => {
                crossSelling.assignedProducts = assignedProducts;
            });

            return crossSelling;
        },

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
