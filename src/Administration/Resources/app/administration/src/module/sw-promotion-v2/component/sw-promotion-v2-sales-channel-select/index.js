import template from './sw-promotion-v2-sales-channel-select.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            salesChannels: [],
            sortBy: 'name',
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        promotionSalesChannelRepository() {
            if (this.promotion) {
                return this.repositoryFactory.create(
                    this.promotion.salesChannels.entity,
                    this.promotion.salesChannels.source,
                );
            }

            return null;
        },

        salesChannelIds: {
            get() {
                if (!this.promotion) {
                    return [];
                }

                return this.promotion.salesChannels.map((promotionSalesChannels) => {
                    return promotionSalesChannels.salesChannelId;
                });
            },

            set(salesChannelsIds) {
                salesChannelsIds = salesChannelsIds || [];
                const { deleted, added } = this.getChangeset(salesChannelsIds);

                if (this.promotion.isNew()) {
                    this.handleLocalMode(deleted, added);
                    return;
                }

                this.handleWithRepository(deleted, added);
            },
        },

        salesChannelCriteria() {
            const salesChannelCriteria = new Criteria(1, 500);
            salesChannelCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return salesChannelCriteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannelRepository
                .search(this.salesChannelCriteria)
                .then(searchresult => {
                    this.salesChannels = searchresult;
                });
        },

        getChangeset(salesChannelsIds) {
            const deleted = [];
            const added = [];

            salesChannelsIds.forEach((id) => {
                const foundSalesChannel = this.promotion.salesChannels.find((salesChannel) => {
                    return salesChannel.salesChannelId === id;
                });

                if (!foundSalesChannel) {
                    added.push(id);
                }
            });

            this.promotion.salesChannels.forEach((salesChannel) => {
                if (!salesChannelsIds.includes(salesChannel.salesChannelId)) {
                    deleted.push(salesChannel.salesChannelId);
                }
            });

            return { deleted, added };
        },

        getAssociationBySalesChannelId(salesChannelId) {
            return this.promotion.salesChannels.find((association) => {
                return association.salesChannelId === salesChannelId;
            });
        },

        handleLocalMode(deleted, added) {
            deleted.forEach((deletedId) => {
                const collectionEntry = this.getAssociationBySalesChannelId(deletedId);
                this.promotion.salesChannels.remove(collectionEntry.id);
            });

            added.forEach((newId) => {
                const newAssociation = this.promotionSalesChannelRepository.create(this.promotion.salesChannels.context);

                newAssociation.salesChannelId = newId;
                newAssociation.promotionId = this.promotion.id;
                newAssociation.priority = 1;
                this.promotion.salesChannels.add(newAssociation);
            });
        },

        handleWithRepository(deleted, added) {
            deleted.forEach((deletedId) => {
                const associationEntry = this.getAssociationBySalesChannelId(deletedId);
                this.promotion.salesChannels.remove(associationEntry.id);
            });

            added.forEach((addedId) => {
                const newAssociation = this.promotionSalesChannelRepository.create(this.promotion.salesChannels.context);

                newAssociation.salesChannelId = addedId;
                newAssociation.promotionId = this.promotion.id;
                newAssociation.priority = 1;
                this.promotion.salesChannels.add(newAssociation);
            });
        },
    },
};
