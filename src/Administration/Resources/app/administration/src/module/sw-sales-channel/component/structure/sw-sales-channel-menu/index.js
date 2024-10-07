/**
 * @package buyers-experience
 */

import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const FlatTree = Shopware.Helper.FlatTreeHelper;

/**
 * @private
 */
Component.register('sw-sales-channel-menu', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'acl',
        'domainLinkService',
    ],

    data() {
        return {
            salesChannels: [],
            showModal: false,
            isLoading: true,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        canCreateSalesChannels() {
            return this.acl.can('sales_channel.creator');
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 7);

            criteria.addIncludes({
                sales_channel: [
                    'name',
                    'type',
                    'active',
                    'translated',
                    'domains',
                ],
                sales_channel_type: ['iconName'],
                sales_channel_domain: [
                    'url',
                    'languageId',
                ],
            });

            criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
            criteria.addAssociation('type');
            criteria.addAssociation('domains');

            if (this.salesChannelFavorites.length) {
                criteria.setLimit(50);
                criteria.addFilter(Criteria.equalsAny('id', this.salesChannelFavorites));
            }

            return criteria;
        },

        moreSalesChannelAvailable() {
            return this.salesChannels?.total > this.salesChannels?.length;
        },

        buildMenuTree() {
            const flatTree = new FlatTree();

            this.salesChannels.forEach((salesChannel) => {
                flatTree.add({
                    id: salesChannel.id,
                    path: 'sw.sales.channel.detail',
                    params: { id: salesChannel.id },
                    color: '#D8DDE6',
                    label: {
                        label: salesChannel.translated.name,
                        translated: true,
                    },
                    icon: salesChannel.type.iconName,
                    children: [],
                    domainLink: this.getDomainLink(salesChannel),
                    active: salesChannel.active,
                });
            });

            return flatTree.convertToTree();
        },

        moreItemsEntry() {
            return {
                active: true,
                children: [],
                color: '#D8DDE6',
                icon: 'regular-ellipsis-v',
                label: this.$tc('sw-sales-channel.general.titleMenuMoreItems'),
                path: 'sw.sales.channel.list',
                position: -1, // use last position
            };
        },

        salesChannelFavoritesService() {
            return Shopware.Service('salesChannelFavorites');
        },

        salesChannelFavorites() {
            if (this.isLoading) {
                return [];
            }

            return this.salesChannelFavoritesService.getFavoriteIds();
        },
    },

    watch: {
        salesChannelFavorites() {
            if (this.isLoading) {
                return;
            }

            this.loadEntityData();
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.registerListener();

            this.salesChannelFavoritesService.initService().finally(() => {
                this.isLoading = false;
            });
        },

        registerListener() {
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$root.$on('sales-channel-change', this.loadEntityData);
                this.$root.$on('on-change-application-language', this.loadEntityData);
                this.$root.$on('on-add-sales-channel', this.openSalesChannelModal);
            } else {
                Shopware.Utils.EventBus.on('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
                Shopware.Utils.EventBus.on('sw-language-switch-change-application-language', this.loadEntityData);
                Shopware.Utils.EventBus.on('sw-sales-channel-detail-base-sales-channel-change', this.openSalesChannelModal);
            }
        },

        destroyedComponent() {
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$root.$off('sales-channel-change', this.loadEntityData);
                this.$root.$off('on-change-application-language', this.loadEntityData);
                this.$root.$off('on-add-sales-channel', this.openSalesChannelModal);
            } else {
                Shopware.Utils.EventBus.off('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
                Shopware.Utils.EventBus.off('sw-language-switch-change-application-language', this.loadEntityData);
                Shopware.Utils.EventBus.off('sw-sales-channel-detail-base-sales-channel-change', this.openSalesChannelModal);
            }
        },

        getDomainLink(salesChannel) {
            return this.domainLinkService.getDomainLink(salesChannel);
        },

        loadEntityData() {
            this.salesChannelRepository.search(this.salesChannelCriteria).then((response) => {
                this.salesChannels = response;
            });
        },

        openSalesChannelModal() {
            this.showModal = true;
        },

        openStorefrontLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },
    },
});
