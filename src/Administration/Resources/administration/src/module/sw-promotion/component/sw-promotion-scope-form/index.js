import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-scope-form.html.twig';
import './sw-promotion-scope-form.scss';

Component.register('sw-promotion-scope-form', {
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
                    'cartHasDeliveryFreeItem', 'cartWeight', 'cartLineItemsInCart', 'cartLineItemsInCartCount',
                    'cartGoodsCount', 'cartGoodsPrice'
                ]),
                Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])])
            ]);
        },

        isEditingDisabled() {
            return !PromotionPermissions.isEditingAllowed(this.promotion);
        }
    }
});
