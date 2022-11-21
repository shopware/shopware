import template from './sw-settings-listing-default-sales-channel.html.twig';
import './sw-settings-listing-default-sales-channel.scss';

const { EntityCollection } = Shopware.Data;
const { isEmpty } = Shopware.Utils.types;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },
    },

    data() {
        return {
            isDefaultSalesChannelLoading: false,
            displayVisibilityDetail: false,
            configData: {
                null: {
                    'core.defaultSalesChannel.active': true,
                    'core.defaultSalesChannel.salesChannel': [],
                    'core.defaultSalesChannel.visibility': {},
                },
            },
            visibilityConfig: [],
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannel: {
            get() {
                return this.configData?.null?.['core.defaultSalesChannel.salesChannel'];
            },
            set(salesChannel) {
                this.configData.null['core.defaultSalesChannel.salesChannel'] = salesChannel;
            },
        },
    },

    watch: {
        salesChannel: {
            handler() {
                if (!this.salesChannel.length) {
                    this.visibilityConfig = [];
                    return;
                }

                const salesChannelIds = this.salesChannel.map(salesChannel => salesChannel.id);
                this.visibilityConfig = this.visibilityConfig.filter(entry => salesChannelIds.includes(entry.id));

                const configData = new Map();
                this.visibilityConfig.forEach(entry => configData.set(entry.id, { ...entry }));

                this.salesChannel.forEach((salesChannel) => {
                    configData.set(salesChannel, {
                        id: salesChannel,
                        visibility: this.configData.null?.['core.defaultSalesChannel.visibility'][salesChannel] || 30,
                    });
                });

                this.visibilityConfig = [...configData.values()];
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannelsSystemConfig();
        },

        fetchSalesChannelsSystemConfig() {
            this.isDefaultSalesChannelLoading = true;

            const salesChannelEntity = new EntityCollection(
                this.salesChannelRepository.route,
                this.salesChannelRepository.entityName,
                Shopware.Context.api,
            );

            this.systemConfigApiService.getValues('core.defaultSalesChannel').then((configData) => {
                this.isDefaultSalesChannelLoading = false;

                if (!isEmpty(configData)) {
                    this.configData.null = configData;
                    this.salesChannel.forEach(salesChannel => salesChannelEntity.add(salesChannel));
                    this.salesChannel = salesChannelEntity;

                    return;
                }

                this.salesChannel = salesChannelEntity;
            });
        },

        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;

            this.visibilityConfig = cloneDeep(this.$refs.visibilityConfig.items);

            this.configData.null['core.defaultSalesChannel.visibility'] = {};
            this.visibilityConfig.forEach((entry) => {
                this.configData.null['core.defaultSalesChannel.visibility'][entry.id] = entry.visibility;
            });
        },

        saveSalesChannelVisibilityConfig() {
            return this.systemConfigApiService.batchSave(this.configData);
        },

        updateSalesChannel(salesChannel) {
            this.salesChannel = salesChannel;
        },
    },
};
