import template from './sw-review-list.html.twig';
import './sw-review-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-review-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            isLoading: false,
            items: null,
            sortBy: 'status,createdAt',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: 'sw-review.list.columnTitle',
                },
                {
                    property: 'points',
                    dataIndex: 'points',
                    label: 'sw-review.list.columnPoints',
                },
                {
                    property: 'product',
                    dataIndex: 'product.name',
                    label: 'sw-review.list.columnProduct',
                    routerLink: 'sw.review.detail',
                    primary: true,
                },
                {
                    property: 'user',
                    dataIndex: 'customer.lastName,customer.firstName',
                    label: 'sw-review.list.columnUser',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-review.list.columnCreatedAt',
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: 'sw-review.list.columnStatus',
                    align: 'center',
                },
                {
                    property: 'comment',
                    dataIndex: 'comment',
                    label: 'sw-review.list.columnComment',
                    align: 'center',
                },
            ];
        },
        repository() {
            return this.repositoryFactory.create('product_review');
        },
        criteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            this.sortBy.split(',').forEach(sorting => {
                criteria.addSorting(Criteria.sort(sorting, this.sortDirection, this.naturalSorting));
            });
            criteria.addAssociation('customer');
            criteria.addAssociation('product');

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            const context = { ...Shopware.Context.api, inheritance: true };
            return this.repository.search(this.criteria, context).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            });
        },

        onDelete(option) {
            this.$refs.listing.deleteItem(option);

            this.repository.search(this.criteria, { ...Shopware.Context.api, inheritance: true }).then((result) => {
                this.total = result.total;
                this.items = result;
            });
        },
    },
});
