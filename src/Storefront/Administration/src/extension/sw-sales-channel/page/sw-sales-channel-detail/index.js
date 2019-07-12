import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-sales-channel-detail.html.twig';

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
                .get(this.$route.params.id, this.context, criteria)
                .then((entity) => {
                    this.salesChannel = entity;
                    this.isLoading = false;
                });
        }
    }
});
