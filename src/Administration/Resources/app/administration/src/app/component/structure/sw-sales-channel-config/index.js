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
            default: '',
        },
        // FIXME: add default value
        // eslint-disable-next-line vue/require-default-prop
        value: {
            type: Object,
            required: false,
        },
        criteria: {
            type: Object,
            required: false,
            default: () => {
                return new Criteria();
            },
        },
    },

    data() {
        return {
            allConfigs: {},
            selectedSalesChannelId: null,
            salesChannel: [],
        };
    },

    computed: {
        actualConfigData: {
            get() {
                return this.allConfigs[this.selectedSalesChannelId];
            },
            set(config) {
                this.allConfigs = {
                    ...this.allConfigs,
                    [this.selectedSalesChannelId]: config,
                };
            },
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
    },

    watch: {
        actualConfigData: {
            handler(configData) {
                if (!configData) {
                    return;
                }

                this.$emit('input', configData);
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.salesChannel.length) {
                this.salesChannelRepository.search(this.criteria, Shopware.Context.api).then(res => {
                    res.add({
                        id: null,
                        translated: {
                            name: this.$tc('sw-sales-channel-switch.labelDefaultOption'),
                        },
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
        },
    },
});
