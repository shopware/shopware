/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.scss';

const { Criteria } = Shopware.Data;
const FlatTree = Shopware.Helper.FlatTreeHelper;

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'domainLinkService',
    ],

    data() {
        return {
            salesChannels: [],
            salesChannelsLoaded: false,
            showModal: false,
            isLoading: true,
            isMobileViewport: false,
            contextMenuOpen: false,
        };
    },

    computed: {
        adminMenuStore() {
            return Shopware.Store.get('adminMenu');
        },

        isSidebarExpanded() {
            // The off-canvas panel always shows the expanded layout
            return this.adminMenuStore.isExpanded || this.isMobileViewport;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        canCreateSalesChannels() {
            return this.acl.can('sales_channel.creator');
        },

        // Gated on the finished request, so the row never flashes while loading.
        // Stale favourites of deleted channels return zero rows although channels exist.
        showAddChannelMenuItem() {
            return (
                this.salesChannelsLoaded &&
                this.salesChannels.length === 0 &&
                this.salesChannelFavorites.length === 0 &&
                this.canCreateSalesChannels
            );
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
                children: [],
                icon: 'regular-eye',
                label: this.$t('sw-sales-channel.general.titleMenuMoreItems'),
                path: 'sw.sales.channel.list',
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

        // The teleported action menu would keep floating over the next page otherwise
        '$route.path'() {
            this.contextMenuOpen = false;
        },

        // The teleported action menu would float detached over the hidden off-canvas rail otherwise
        isMobileViewport(isMobile) {
            if (isMobile) {
                this.contextMenuOpen = false;
            }
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
            this.mobileViewportQuery = this.$device.getMediaQuery('(max-width: 1280px)');
            this.mobileViewportQuery.addEventListener('change', this.syncMobileViewport);
            this.syncMobileViewport();

            this.registerListener();

            this.salesChannelFavoritesService.initService().finally(() => {
                this.isLoading = false;
            });
        },

        syncMobileViewport() {
            this.isMobileViewport = this.mobileViewportQuery.matches;
        },

        registerListener() {
            Shopware.Utils.EventBus.on('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
            Shopware.Utils.EventBus.on('sw-language-switch-change-application-language', this.loadEntityData);
            Shopware.Utils.EventBus.on('sw-sales-channel-detail-base-sales-channel-change', this.openSalesChannelModal);
            Shopware.Utils.EventBus.on('sw-sales-channel-list-add-new-channel', this.openSalesChannelModal);
        },

        destroyedComponent() {
            this.mobileViewportQuery?.removeEventListener('change', this.syncMobileViewport);
            Shopware.Utils.EventBus.off('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
            Shopware.Utils.EventBus.off('sw-language-switch-change-application-language', this.loadEntityData);
            Shopware.Utils.EventBus.off('sw-sales-channel-detail-base-sales-channel-change', this.openSalesChannelModal);
            Shopware.Utils.EventBus.off('sw-sales-channel-list-add-new-channel', this.openSalesChannelModal);
        },

        getDomainLink(salesChannel) {
            return this.domainLinkService.getDomainLink(salesChannel);
        },

        loadEntityData() {
            return this.salesChannelRepository.search(this.salesChannelCriteria).then((response) => {
                this.salesChannels = response;
                this.salesChannelsLoaded = true;
            });
        },

        openSalesChannelModal() {
            this.showModal = true;
        },

        openStorefrontLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },
    },
};
