import template from './sw-review-list.html.twig';
import './sw-review-list.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-review-list', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            criteria: null,
            repository: null,
            items: null,
            term: this.$route.query ? this.$route.query.term : null
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: this.$tc('sw-review.list.columnTitle')
                },
                {
                    property: 'points',
                    dataIndex: 'points',
                    label: this.$tc('sw-review.list.columnPoints')
                },
                {
                    property: 'product',
                    dataIndex: 'product.name',
                    label: this.$tc('sw-review.list.columnProduct'),
                    routerLink: 'sw.review.detail',
                    primary: true
                },
                {
                    property: 'user',
                    dataIndex: 'externalUser',
                    label: this.$tc('sw-review.list.columnUser')
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$tc('sw-review.list.columnCreatedAt')
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: this.$tc('sw-review.list.columnStatus'),
                    align: 'center'
                },
                {
                    property: 'comment',
                    dataIndex: 'comment',
                    label: this.$tc('sw-review.list.columnComment'),
                    align: 'center'
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory.create('product_review');

            this.criteria = new Criteria();
            this.criteria.addSorting(Criteria.sort('status', 'ASC'));
            this.criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            this.criteria.addAssociation('customer');
            this.criteria.addAssociation('product');

            if (this.term) {
                this.criteria.setTerm(this.term);
            }

            this.isLoading = true;

            this.repository
                .search(this.criteria, Shopware.Context.api)
                .then((result) => {
                    this.total = result.total;
                    this.items = result;
                    this.isLoading = false;
                });
        },
        onSearch(term) {
            this.criteria.setTerm(term);
            this.$route.query.term = term;
            this.$refs.listing.doSearch();
        }
    }
});
