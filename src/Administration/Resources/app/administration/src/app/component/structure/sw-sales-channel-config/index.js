import template from './sw-sales-channel-config.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-config', {
    template,

    inject: ['systemConfigApiService', 'repositoryFactory'],

    props: {
        domain: {
            type: String,
            required: false,
            default: ''
        },
        value: {
            type: Object,
            required: false
        },
        criteria: {
            type: Object,
            required: false,
            default: () => {
                return new Criteria();
            }
        }
    },

    data() {
        return {
            allConfigs: {},
            selectedSalesChannelId: null,
            salesChannel: []
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        actualConfigData: {
            handler(configData) {
                if (!configData) {
                    return;
                }

                this.$emit('input', configData);
            },
            deep: true
        }
    },

    computed: {
        actualConfigData: {
            get() {
                return this.allConfigs[this.selectedSalesChannelId];
            },
            set(config) {
                this.allConfigs = {
                    ...this.allConfigs,
                    [this.selectedSalesChannelId]: config
                };
            }
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        }
    },

    methods: {
        createdComponent() {
            if (!this.salesChannel.length) {
                this.salesChannelRepository.search(this.criteria, Shopware.Context.api).then(res => {
                    res.add({
                        id: null,
                        translated: {
                            name: this.$tc('sw-sales-channel-switch.labelDefaultOption')
                        }
                    });

                    this.salesChannel = res;
                });
            }

            if (this.allConfigs[this.selectedSalesChannelId]) {
                return;
            }

            if (this.domain && !this.actualConfigData) {
                this.readAll().then((values) => {
                    this.actualConfigData = values;
                });
            }
        },

        readAll() {
            return this.systemConfigApiService.getValues(this.domain, this.selectedSalesChannelId);
        },

        onInput(salesChannelId) {
            this.selectedSalesChannelId = salesChannelId;
            this.$emit('salesChannelChanged');
            this.createdComponent();
        },

        save() {
            if (this.domain && this.domain.length !== 0) {
                return this.systemConfigApiService.batchSave(this.allConfigs);
            }

            return Promise.resolve(this.allConfigs);
        }
    }
});
