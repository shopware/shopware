import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.override('sw-sales-channel-detail', {
    template,

    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('paymentMethods');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('countries');
            criteria.addAssociation('currencies');
            criteria.addAssociation('languages');
            criteria.addAssociation('domains');
            criteria.addAssociation('themes');

            criteria.addAssociation('domains.language');
            criteria.addAssociation('domains.snippetSet');
            criteria.addAssociation('domains.currency');

            return criteria;
        }
    }
});
