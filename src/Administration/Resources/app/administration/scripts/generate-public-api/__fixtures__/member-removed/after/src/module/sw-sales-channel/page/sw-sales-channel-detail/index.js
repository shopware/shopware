/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail.html.twig';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            salesChannel: null,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        tooltipSave() {
            if (!this.allowSaving) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSaving,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        allowSaving() {
            return this.acl.can('sales_channel.editor');
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadSalesChannel();
        },

        loadSalesChannel() {
            if (!this.$route.params.id) {
                return;
            }

            this.isLoading = true;

            this.repositoryFactory
                .create('sales_channel')
                .get(this.$route.params.id, Context.api, this.getSalesChannelCriteria())
                .then((salesChannel) => {
                    this.salesChannel = salesChannel;
                    this.isLoading = false;
                });
        },

        getSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('domains');
            criteria.getAssociation('languages').addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            try {
                await this.repositoryFactory.create('sales_channel').save(this.salesChannel, Context.api);
                this.isSaveSuccessful = true;
                this.loadSalesChannel();
            } finally {
                this.isLoading = false;
            }
        },
    },
};
