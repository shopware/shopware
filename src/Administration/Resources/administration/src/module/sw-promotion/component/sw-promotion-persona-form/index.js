import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-persona-form.html.twig';
import './sw-promotion-persona-form.scss';

Component.register('sw-promotion-persona-form', {
    template,

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        ruleFilter() {
            return Criteria.equalsAny('conditions.type', [
                'customerBillingCountry', 'customerBillingStreet', 'customerBillingZipCode', 'customerIsNewCustomer',
                'customerCustomerGroup', 'customerCustomerNumber', 'customerDaysSinceLastOrder',
                'customerDifferentAddresses', 'customerLastName', 'customerOrderCount', 'customerShippingCountry',
                'customerShippingStreet', 'customerShippingZipCode'
            ]);
        }
    }
});
