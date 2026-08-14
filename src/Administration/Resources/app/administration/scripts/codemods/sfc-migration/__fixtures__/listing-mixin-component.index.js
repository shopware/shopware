import template from './listing-mixin-component.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('sw-listing-demo', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('listing'),
    ],

    data() {
        return {
            products: null,
            isLoading: false,
            sortBy: 'name',
            searchConfigEntity: 'product',
            storeKey: 'grid.filter.product',
            filterCriteria: [],
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        filters() {
            return [
                { name: 'term', active: false },
            ];
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            const items = await this.productRepository.search(criteria);

            this.total = items.total;
            this.products = items;
            this.isLoading = false;
        },

        onDeleteProduct(id) {
            return this.productRepository.delete(id).then(() => {
                this.getList();
            });
        },
    },
});
