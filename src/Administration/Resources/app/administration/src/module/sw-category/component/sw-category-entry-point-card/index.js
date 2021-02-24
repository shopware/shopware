import template from './sw-category-entry-point-card.html.twig';

const { Component, Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Component.register('sw-category-entry-point-card', {
    template,

    props: {
        category: {
            type: Object,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        entryPoints() {
            return [{
                value: 'navigationSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelMainNavigation')
            }, {
                value: 'footerSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelFooterNavigation')
            }, {
                value: 'serviceSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelServiceNavigation')
            }];
        },

        associatedCollection() {
            return this.category[this.selectedEntryPoint];
        }
    },

    data() {
        return {
            selectedEntryPoint: this.getInitialEntryPointFromCategory(),
            selectedSalesChannels: new EntityCollection('/sales_channel', 'sales_channel', Context.api),
            configureHomeModalVisible: false
        };
    },

    methods: {
        getInitialEntryPointFromCategory() {
            if (this.category.navigationSalesChannels && this.category.navigationSalesChannels.length > 0) {
                return 'navigationSalesChannels';
            }

            if (this.category.footerSalesChannels && this.category.footerSalesChannels.length > 0) {
                return 'footerSalesChannels';
            }

            if (this.category.serviceSalesChannels && this.category.serviceSalesChannels.length > 0) {
                return 'serviceSalesChannels';
            }

            return '';
        },

        onEntryPointChange() {
            this.resetSalesChannelCollections();
        },

        onSalesChannelChange(changedEntityCollection) {
            const entryPoint = this.selectedEntryPoint;
            changedEntityCollection.source = this.category[this.selectedEntryPoint].source;
            this.resetSalesChannelCollections();

            this.category[entryPoint] = changedEntityCollection;
        },

        resetSalesChannelCollections() {
            const entryPoint = this.selectedEntryPoint;

            const salesChannelsCollectionToReset = this.entryPoints.reduce((accumulator, { value }) => {
                if (value === entryPoint) {
                    return accumulator;
                }

                accumulator.push(this.category[value]);
                return accumulator;
            }, []);

            salesChannelsCollectionToReset.forEach((collection) => {
                const ids = collection.getIds();

                ids.forEach((id) => {
                    collection.remove(id);
                });
            });
        },

        openConfigureHomeModal() {
            this.configureHomeModalVisible = true;
        },

        closeConfigureHomeModal() {
            this.configureHomeModalVisible = false;
        }
    }
});
