import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-order-condition-form.html.twig';
import './sw-promotion-order-condition-form.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-order-condition-form', {
    template,

    inject: ['acl'],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
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
                    'customerShippingZipCode',
                ]),
                Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])]),
            ]));

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        isEditingDisabled() {
            if (!this.acl.can('promotion.editor')) {
                return true;
            }

            return !PromotionPermissions.isEditingAllowed(this.promotion);
        },
    },
});
