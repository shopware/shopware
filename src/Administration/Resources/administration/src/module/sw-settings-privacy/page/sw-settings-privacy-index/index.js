import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-privacy-index.html.twig';

// ToDo: Delete/Refactor with NEXT-2612 - Temporary Module, just for development
Component.register('sw-settings-privacy-index', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'system_config',
            isLoading: false,
            items: [],
            salesChannels: null,
            skeletonItemAmount: 2
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },
        systemConfigStore() {
            return State.getStore('system_config');
        },
        salesChannelStore() {
            return State.getStore('sales_channel');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getColumns() {
            return [{
                property: 'configurationKey',
                label: this.$tc('sw-settings-privacy.index.columnConfigurationKey'),
                dataIndex: 'configurationKey',
                primary: true
            }, {
                property: 'configurationValue',
                label: this.$tc('sw-settings-privacy.index.columnConfigurationValue'),
                dataIndex: 'configurationValue',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'salesChannelId',
                label: this.$tc('sw-settings-privacy.index.columnSalesChannel'),
                dataIndex: 'salesChannel.name',
                inlineEdit: 'string'
            }];
        },

        getList() {
            this.isLoading = true;

            this.loadSalesChannels().then(() => {
                this.loadSystemConfig();
            });
        },

        loadSalesChannels() {
            return this.salesChannelStore.getList({ page: 1, limit: 500 }).then(({ items }) => {
                this.salesChannels = items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        loadSystemConfig() {
            const config = {
                page: 1,
                limit: 500,
                associations: { salesChannel: {} }
            };

            return this.systemConfigStore.getList(config).then(({ items }) => {
                this.items = items;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        salesChannelPlaceholder(setting) {
            return this.placeholder(
                setting.salesChannel,
                'name',
                this.$tc('sw-settings-privacy.index.salesChannelOptionEmpty')
            );
        },

        onInlineEditSave(setting) {
            setting.save(true).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-privacy.general.titleSuccess'),
                    message: this.$tc(
                        'sw-settings-privacy.index.messageSaveSuccess',
                        0,
                        { key: setting.configurationKey }
                    )
                });
                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-privacy.general.titleError'),
                    message: this.$tc('sw-settings-privacy.index.messageSaveError')
                });
                this.getList();
            });
        },

        onInlineEditCancel(setting) {
            setting.discardChanges();
            this.getList();
        }
    }
});
