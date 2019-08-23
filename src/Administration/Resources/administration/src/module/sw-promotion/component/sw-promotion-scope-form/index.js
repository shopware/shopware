import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-scope-form.html.twig';
import './sw-promotion-scope-form.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

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
