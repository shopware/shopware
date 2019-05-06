import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import FlatTree from 'src/core/helper/flattree.helper';
import template from './sw-sales-channel-menu.html.twig';

Component.register('sw-sales-channel-menu', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

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
            const criteria = new Criteria();

            criteria.setPage(1);
            criteria.setLimit(500);
            criteria.addSorting(
                Criteria.sort('sales_channel.name', 'ASC')
            );

            this.salesChannelRepository.search(criteria, this.context).then((response) => {
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
