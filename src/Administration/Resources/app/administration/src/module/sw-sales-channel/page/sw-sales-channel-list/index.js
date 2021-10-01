import template from './sw-sales-channel-list.html.twig';
import './sw-sales-channel-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            salesChannels: null,
            productsForSalesChannel: {},
            isLoading: true,
            sortBy: 'name',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        salesChannelColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                allowResize: false,
                routerLink: 'sw.sales.channel.detail',
                label: 'sw-sales-channel.list.columnName',
                primary: true,
            }, {
                property: 'product_visibilities',
                dataIndex: 'product_visibilities',
                allowResize: false,
                sortable: false,
                label: 'sw-sales-channel.list.productsLabel',
            }, {
                property: 'status',
                dataIndex: 'status',
                allowResize: false,
                sortable: false,
                label: 'sw-sales-channel.list.columnStatus',
            }];
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            const salesChannelCriteria = new Criteria(this.page, this.limit);

            salesChannelCriteria.setTerm(this.term);
            salesChannelCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            salesChannelCriteria.addAssociation('type');

            salesChannelCriteria.addAggregation(
                Criteria.terms(
                    'sales_channel',
                    'id',
                    null,
                    null,
                    Criteria.count('visible_products', 'sales_channel.productVisibilities.id'),
                ),
            );

            return salesChannelCriteria;
        },
    },

    methods: {
        onAddSalesChannel() {
            this.$root.$emit('on-add-sales-channel');
        },

        getList() {
            this.isLoading = true;

            return this.salesChannelRepository.search(this.salesChannelCriteria)
                .then(searchResult => {
                    this.salesChannels = searchResult;
                    this.setProductAggregations(searchResult.aggregations?.sales_channel?.buckets);
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },

        setProductAggregations(buckets) {
            this.productsForSalesChannel = buckets.reduce((productsForSalesChannel, bucket) => ({
                ...productsForSalesChannel,
                [bucket.key]: bucket.visible_products?.count,
            }), {});
        },

        getCountForSalesChannel(salesChannelId) {
            return this.productsForSalesChannel[salesChannelId] ?? 0;
        },
    },
});
