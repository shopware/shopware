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

            return criteria;
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
            this.salesChannelRepository.search(this.salesChannelCriteria).then((response) => {
                this.salesChannels = response;
            });
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
