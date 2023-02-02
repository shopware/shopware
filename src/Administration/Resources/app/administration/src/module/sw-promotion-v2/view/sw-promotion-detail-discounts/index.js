import { DiscountTypes, DiscountScopes } from 'src/module/sw-promotion-v2/helper/promotion.helper';
import template from './sw-promotion-detail-discounts.html.twig';
import './sw-promotion-detail-discounts.scss';

/**
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            deleteDiscountId: null,
            repository: null,
        };
    },

    computed: {
        promotion() {
            return Shopware.State.get('swPromotionDetail').promotion;
        },

        isLoading: {
            get() {
                return Shopware.State.get('swPromotionDetail').isLoading;
            },
            set(isLoading) {
                Shopware.State.commit('swPromotionDetail/setIsLoading', isLoading);
            },
        },

        discounts() {
            return Shopware.State.get('swPromotionDetail').promotion &&
                Shopware.State.get('swPromotionDetail').promotion.discounts;
        },

    },

    methods: {
        // This function adds a new blank discount object to our promotion.
        // It will automatically trigger a rendering of the view which
        // leads to a new card that appears within our discounts area.
        onAddDiscount() {
            const promotionDiscountRepository = this.repositoryFactory.create(
                this.discounts.entity,
                this.discounts.source,
            );
            const newDiscount = promotionDiscountRepository.create();
            newDiscount.promotionId = this.promotion.id;
            newDiscount.scope = DiscountScopes.CART;
            newDiscount.type = DiscountTypes.PERCENTAGE;
            newDiscount.value = 0.01;
            newDiscount.considerAdvancedRules = false;
            newDiscount.sorterKey = 'PRICE_ASC';
            newDiscount.applierKey = 'ALL';
            newDiscount.usageKey = 'ALL';

            this.discounts.push(newDiscount);
        },

        deleteDiscount(discount) {
            if (discount.isNew()) {
                this.discounts.remove(discount.id);
                return;
            }

            this.isLoading = true;
            const promotionDiscountRepository = this.repositoryFactory.create(
                this.discounts.entity,
                this.discounts.source,
            );

            promotionDiscountRepository.delete(discount.id, this.discounts.context).then(() => {
                this.discounts.remove(discount.id);
                this.isLoading = false;
            });
        },
    },
};
