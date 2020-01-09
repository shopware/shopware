import template from './sw-sales-channel-detail-hreflang.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-detail-hreflang', {
    template,

    props: {
        salesChannel: {
            required: true
        }
    },

    computed: {
        domainCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));

            return criteria;
        }
    }
});
