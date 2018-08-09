import { Component, State } from 'src/core/shopware';
import FlatTree from 'src/core/helper/flattree.helper';
import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.less';

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
        const params = {
            limit: 500,
            offset: 0
        };

        this.salesChannelStore.getList(params).then((response) => {
            this.salesChannels = response.items;
            this.createMenuTree();
        });
    },

    methods: {
        createMenuTree() {
            const flatTree = new FlatTree();

            this.salesChannels.forEach((salesChannel) => {
                flatTree.add({
                    id: salesChannel.id,
                    path: 'sw.sales.channel.detail',
                    params: { id: salesChannel.id },
                    color: '#D8DDE6',
                    label: { label: salesChannel.name, translated: true },
                    icon: 'default-money-cash',
                    children: []
                });
            });

            this.menuItems = flatTree.convertToTree();
        }
    }
});
