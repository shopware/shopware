import template from './sw-sales-channel-menu.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const FlatTree = Shopware.Helper.FlatTreeHelper;

Component.register('sw-sales-channel-menu', {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            salesChannels: [],
            menuItems: [],
            showModal: false
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        canCreateSalesChannels() {
            return this.acl.can('sales_channel.creator');
        }
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
        },

        destroyedComponent() {
            this.$root.$off('sales-channel-change', this.loadEntityData);
            this.$root.$off('on-change-application-language', this.loadEntityData);
        },

        loadEntityData() {
            const criteria = new Criteria();

            criteria.setPage(1);
            criteria.setLimit(500);
            criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
            criteria.addAssociation('type');

            this.salesChannelRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.salesChannels = response;
                this.createMenuTree();
            });
        },

        createMenuTree() {
            const flatTree = new FlatTree();

            this.salesChannels.forEach((salesChannel) => {
                flatTree.add({
                    id: salesChannel.id,
                    path: 'sw.sales.channel.detail',
                    params: { id: salesChannel.id },
                    color: '#D8DDE6',
                    label: { label: salesChannel.translated.name, translated: true },
                    icon: salesChannel.type.iconName,
                    children: []
                });
            });

            this.menuItems = flatTree.convertToTree();
        }
    }
});
