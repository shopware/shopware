import template from './sw-category-entry-point-card.html.twig';
import './sw-category-entry-point-card.scss';

const { Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
    ],

    props: {
        category: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            selectedEntryPoint: this.getInitialEntryPointFromCategory(),
            initialNavigationSalesChannels: this.category.navigationSalesChannels,
            addedNavigationSalesChannels: new EntityCollection('/sales_channel', 'sales_channel', Context.api),
            configureHomeModalVisible: false,
        };
    },

    computed: {
        entryPoints() {
            return [{
                value: 'navigationSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelMainNavigation'),
            }, {
                value: 'footerSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelFooterNavigation'),
            }, {
                value: 'serviceSalesChannels',
                label: this.$tc('sw-category.base.entry-point-card.types.labelServiceNavigation'),
            }];
        },

        associatedCollection() {
            if (this.hasExistingNavigation) {
                return this.addedNavigationSalesChannels;
            }

            return this.category[this.selectedEntryPoint];
        },

        helpText() {
            switch (this.selectedEntryPoint) {
                case 'navigationSalesChannels':
                    return this.$tc('sw-category.base.entry-point-card.types.helpTextMainNavigation');
                case 'footerSalesChannels':
                    return this.$tc('sw-category.base.entry-point-card.types.helpTextFooterNavigation');
                case 'serviceSalesChannels':
                    return this.$tc('sw-category.base.entry-point-card.types.helpTextServiceNavigation');
                default:
                    return '';
            }
        },

        hasExistingNavigation() {
            return this.initialNavigationSalesChannels.length > 0;
        },

        salesChannelSelectionLabel() {
            if (this.hasExistingNavigation) {
                return this.$tc('sw-category.base.entry-point-card.labelSalesChannelsAdd');
            }

            return this.$tc('global.entities.sales_channel', 2);
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.hasExistingNavigation) {
                criteria.addFilter(Criteria.not('or', [
                    Criteria.equalsAny('id', this.initialNavigationSalesChannels.getIds()),
                ]));
            }

            return criteria;
        },
    },

    watch: {
        category(newCategory) {
            this.initialNavigationSalesChannels = newCategory.navigationSalesChannels;
            this.addedNavigationSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api);
            this.selectedEntryPoint = this.getInitialEntryPointFromCategory();
        },
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

            if (this.hasExistingNavigation) {
                const joinedNavigationCollection = EntityCollection.fromCollection(this.initialNavigationSalesChannels);
                changedEntityCollection.forEach((item) => {
                    joinedNavigationCollection.add(item);
                });
                this.addedNavigationSalesChannels = changedEntityCollection;
                changedEntityCollection = joinedNavigationCollection;
            }

            changedEntityCollection.source = this.category[entryPoint].source;
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
        },
    },
};
