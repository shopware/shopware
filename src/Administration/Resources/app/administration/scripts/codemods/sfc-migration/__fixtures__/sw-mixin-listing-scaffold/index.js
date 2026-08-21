import template from './sw-mixin-listing-scaffold.html.twig';

/**
 * @sw-package framework
 */
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
            isLoading: false,
            // Listing state the component only configures.
            limit: 10,
            sortBy: 'createdAt',
            searchConfigEntity: 'product',
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('product');
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return criteria;
        },

        // The mixin's own computed defaulted to an empty list and invited this override.
        filters() {
            return [
                {
                    name: 'active-filter',
                    active: this.entitySearchable,
                },
            ];
        },
    },

    methods: {
        // The member the mixin called but never implemented.
        async getList() {
            this.isLoading = true;

            const result = await this.repository.search(this.listCriteria, Shopware.Context.api);

            this.items = result;
            this.total = result.total;
            this.isLoading = false;
        },

        onSaved() {
            this.getList();
        },
    },
};
