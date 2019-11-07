import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.override('sw-sales-channel-detail', {
    template,

    methods: {
        loadSalesChannel() {
            const criteria = new Criteria();

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.addAssociation('currencies');
            criteria.addAssociation('languages');
            criteria.addAssociation('domains');
            criteria.addAssociation('themes');

            this.isLoading = true;
            this.salesChannelRepository
                .get(this.$route.params.id, this.apiContext, criteria)
                .then((entity) => {
                    this.salesChannel = entity;
                    this.isLoading = false;
                });
        }
    }
});
