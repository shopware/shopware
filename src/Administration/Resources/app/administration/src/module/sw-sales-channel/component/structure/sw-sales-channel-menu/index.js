import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const FlatTree = Shopware.Helper.FlatTreeHelper;

Component.register('sw-sales-channel-menu', {
    template,

    inject: ['repositoryFactory', 'acl', 'domainLinkService'],

    data() {
        return {
            salesChannels: [],
            showModal: false,
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
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
            criteria.addAssociation('type');
            criteria.addAssociation('domains');
            criteria.setLimit(25);

            if (this.salesChannelFavorites.length) {
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
                    label: { label: salesChannel.translated.name, translated: true },
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
                icon: 'default-action-more-vertical',
                label: this.$tc('sw-sales-channel.general.titleMenuMoreItems'),
                path: 'sw.sales.channel.list',
                position: -1, // use last position
            };
        },

        salesChannelFavoritesService() {
            return Shopware.Service('salesChannelFavorites');
        },

        salesChannelFavorites() {
            return this.salesChannelFavoritesService.getFavoriteIds();
        },
    },

    watch: {
        salesChannelFavorites() {
            this.loadEntityData();
        },
    },


    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.loadEntityData();
            this.registerListener();
        },

        registerListener() {
            this.$root.$on('sales-channel-change', this.loadEntityData);
            this.$root.$on('on-change-application-language', this.loadEntityData);
            this.$root.$on('on-add-sales-channel', this.openSalesChannelModal);
        },

        destroyedComponent() {
            this.$root.$off('sales-channel-change', this.loadEntityData);
            this.$root.$off('on-change-application-language', this.loadEntityData);
            this.$root.$off('on-add-sales-channel', this.openSalesChannelModal);
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
