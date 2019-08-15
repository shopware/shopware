import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-order-condition-form.html.twig';
import './sw-promotion-order-condition-form.scss';

const { Component } = Shopware;

Component.register('sw-promotion-order-condition-form', {
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
            return Criteria.multi('AND', [
                Criteria.equalsAny('conditions.type', [
                    'customerOrderCount', 'customerDaysSinceLastOrder', 'customerBillingCountry',
                    'customerBillingStreet', 'customerBillingZipCode', 'customerCustomerGroup',
                    'customerCustomerNumber', 'customerDifferentAddresses', 'customerIsNewCustomer',
                    'customerLastName', 'customerShippingCountry', 'customerShippingStreet',
                    'customerShippingZipCode'
                ]),
                Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])])
            ]);
        }
    }
});
