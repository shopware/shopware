/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-list', {
    inject: ['repositoryFactory', 'filterFactory'],

    data() {
        return {
            exportChannelOptions: [],
        };
    },

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            criteria.addAssociation('salesChannelTracking.salesChannel');

            return criteria;
        },

        listFilters() {
            const filters = this.$super('listFilters');

            const exportChannelFilter = this.filterFactory.create('order', {
                'export-channel-filter': {
                    property: 'salesChannelTracking.salesChannelId',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-export-channel-tracking.extension.sw-order-list.filters.exportFeedFilter.label'),
                    placeholder: this.$tc('sw-export-channel-tracking.extension.sw-order-list.filters.exportFeedFilter.placeholder'),
                    valueProperty: 'id',
                    labelProperty: 'name',
                    options: this.exportChannelOptions,
                },
            }).pop();

            const anchorIndex = filters.findIndex((f) => f.name === 'campaign-code-filter');
            if (anchorIndex !== -1) {
                filters.splice(anchorIndex + 1, 0, exportChannelFilter);
            } else {
                filters.push(exportChannelFilter);
            }

            return filters;
        },
    },

    methods: {
        createdComponent() {
            this.defaultFilters.push('export-channel-filter');
            this.loadExportChannelOptions();

            return this.$super('createdComponent');
        },

        getOrderColumns() {
            const columns = this.$super('getOrderColumns');

            columns.push({
                property: 'extensions.salesChannelTracking.salesChannel.name',
                dataIndex: 'extensions.salesChannelTracking.salesChannelId',
                label: 'sw-export-channel-tracking.extension.sw-order-list.list.columnExportFeed',
                allowResize: true,
                visible: false,
            });

            return columns;
        },

        loadExportChannelOptions() {
            const repo = this.repositoryFactory.create('sales_channel');
            const criteria = new Criteria(1, 500);

            criteria.addFilter(
                Criteria.equals('typeId', Shopware.Defaults.agenticCommerceTypeId),
            );
            criteria.addSorting(Criteria.sort('name'));

            repo.search(criteria).then((result) => {
                this.exportChannelOptions = result;
            });
        },
    },
});
