import { Component, State } from 'src/core/shopware';
import FlatTree from 'src/core/helper/flattree.helper';
import template from './sw-sales-channel-menu.html.twig';

Component.register('sw-sales-channel-menu', {
    template,

    data() {
        return {
            salesChannels: [],
            menuItems: [],
            showModal: false
        };
    },

    computed: {
        salesChannelStore() {
            return State.getStore('sales_channel');
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
            this.$root.$on('changed-sales-channel', this.loadEntityData);
            this.$root.$on('on-change-application-language', this.loadEntityData);
        },

        destroyedComponent() {
            this.$root.$off('changed-sales-channel', this.loadEntityData);
            this.$root.$off('on-change-application-language', this.loadEntityData);
        },

        loadEntityData() {
            const params = {
                limit: 500,
                sortBy: 'name',
                page: 1
            };

            this.salesChannelStore.getList(params).then((response) => {
                this.salesChannels = response.items;
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
                    label: { label: salesChannel.meta.viewData.name, translated: true },
                    icon: salesChannel.type.iconName,
                    children: []
                });
            });

            this.menuItems = flatTree.convertToTree();
            // ToDo: only show theme manager in the storefront
            this.menuItems.forEach((item) => {
                item.children = [{
                    children: [],
                    label: this.$tc('sw-theme-manager.general.mainMenuItemIndex'),
                    parent: 'sw-sales-channel',
                    path: 'sw.theme.manager.index',
                    position: 1000
                }];
            });
        }
    }
});
