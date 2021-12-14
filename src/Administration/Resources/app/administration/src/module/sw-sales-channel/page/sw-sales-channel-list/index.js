import template from './sw-sales-channel-list.html.twig';
import './sw-sales-channel-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const STATUS_NUMBER = {
    ACTIVE: 1,
    MAINTENANCE: 2,
    OFFLINE: 3,
};

Component.register('sw-sales-channel-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            salesChannels: null,
            productsForSalesChannel: {},
            isLoading: true,
            sortBy: 'name',
            searchConfigEntity: 'sales_channel',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        salesChannelColumns() {
            const columns = [{
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
                sortable: this.feature.isActive('FEATURE_NEXT_17421'),
                useCustomSort: this.feature.isActive('FEATURE_NEXT_17421'),
                label: 'sw-sales-channel.list.productsLabel',
            }, {
                property: 'status',
                dataIndex: 'status',
                allowResize: false,
                sortable: this.feature.isActive('FEATURE_NEXT_17421'),
                useCustomSort: this.feature.isActive('FEATURE_NEXT_17421'),
                label: 'sw-sales-channel.list.columnStatus',
            }];

            if (this.feature.isActive('FEATURE_NEXT_17421')) {
                columns.splice(1, 0, {
                    property: 'type.name',
                    dataIndex: 'type.name',
                    allowResize: false,
                    label: 'sw-sales-channel.list.columnType',
                });

                columns.push({
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    allowResize: false,
                    label: 'sw-sales-channel.list.columnCreatedAt',
                });
            }

            return columns;
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

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.salesChannelCriteria);
            if (this.feature.isActive('FEATURE_NEXT_6040') && !this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            return this.salesChannelRepository.search(criteria)
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

        sortColumns(column, sortDirection) {
            if (!this.feature.isActive('FEATURE_NEXT_17421')) {
                return;
            }

            if (column.dataIndex === 'product_visibilities') {
                this.sortProductVisibilities(sortDirection);
            }

            if (column.dataIndex === 'status') {
                this.sortStatus(sortDirection);
            }
        },

        sortProductVisibilities(sortDirection) {
            this.salesChannels = this.salesChannels.sort((a, b) => {
                const countA = this.getCountForSalesChannel(a.id);
                const countB = this.getCountForSalesChannel(b.id);

                if (sortDirection === 'ASC') {
                    return countA - countB;
                }

                return countB - countA;
            });
        },

        sortStatus(sortDirection) {
            this.salesChannels = this.salesChannels.sort((a, b) => {
                const statusA = this.getSalesChannelStatusNumber(a);
                const statusB = this.getSalesChannelStatusNumber(b);

                if (sortDirection === 'ASC') {
                    return statusA - statusB;
                }

                return statusB - statusA;
            });
        },

        getSalesChannelStatusNumber(item) {
            if (item.maintenance) {
                return STATUS_NUMBER.MAINTENANCE;
            }

            if (item.active) {
                return STATUS_NUMBER.ACTIVE;
            }

            return STATUS_NUMBER.OFFLINE;
        },
    },
});
