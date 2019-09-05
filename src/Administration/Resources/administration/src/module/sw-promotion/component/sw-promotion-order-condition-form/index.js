import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-order-condition-form.html.twig';
import './sw-promotion-order-condition-form.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;


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
            const criteria = new Criteria();

            criteria.addFilter(Criteria.multi('AND', [
                Criteria.equalsAny('conditions.type', [
                    'customerOrderCount', 'customerDaysSinceLastOrder', 'customerBillingCountry',
                    'customerBillingStreet', 'customerBillingZipCode', 'customerCustomerGroup',
                    'customerCustomerNumber', 'customerDifferentAddresses', 'customerIsNewCustomer',
                    'customerLastName', 'customerShippingCountry', 'customerShippingStreet',
                    'customerShippingZipCode'
                ]),
                Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])])
            ]));

            return criteria;
        },

        isEditingDisabled() {
            return !PromotionPermissions.isEditingAllowed(this.promotion);
        }
    }
});
