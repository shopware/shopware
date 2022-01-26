import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.scss';

const { Component, Defaults, State } = Shopware;
const { Criteria } = Shopware.Data;
const FlatTree = Shopware.Helper.FlatTreeHelper;

Component.register('sw-sales-channel-menu', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            criteria.setLimit(7);

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

        loadEntityData() {
            this.salesChannelRepository.search(this.salesChannelCriteria).then((response) => {
                this.salesChannels = response;
            });
        },

        openSalesChannelModal() {
            this.showModal = true;
        },

        getDomainLink(salesChannel) {
            if (salesChannel.type.id !== Defaults.storefrontSalesChannelTypeId) {
                return null;
            }

            if (salesChannel.domains.length === 0) {
                return null;
            }

            const adminLanguageDomain = salesChannel.domains.find((domain) => {
                return domain.languageId === State.get('session').languageId;
            });
            if (adminLanguageDomain) {
                return adminLanguageDomain.url;
            }

            const systemLanguageDomain = salesChannel.domains.find((domain) => {
                return domain.languageId === Defaults.systemLanguageId;
            });
            if (systemLanguageDomain) {
                return systemLanguageDomain.url;
            }

            return salesChannel.domains[0].url;
        },

        openStorefrontLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },
    },
});
