import template from './sw-promotion-detail-discounts.html.twig';
import './sw-promotion-detail-discounts.scss';
import {
    aclService,
    DiscountScopes,
    DiscountTypes,
    repositoryFactoryService,
    usePromotionDetailStore,
} from '../dependencies';

/**
 * @sw-package checkout
 *
 * @private
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            deleteDiscountId: null,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            repository: null,
        };
    },

    computed: {
        promotion() {
            return usePromotionDetailStore().promotion;
        },

        isLoading: {
            get() {
                return usePromotionDetailStore().isLoading;
            },
            set(isLoading) {
                usePromotionDetailStore().isLoading = isLoading;
            },
        },

        discounts() {
            return usePromotionDetailStore().promotion && usePromotionDetailStore().promotion.discounts;
        },
    },

    methods: {
        // This function adds a new blank discount object to our promotion.
        // It will automatically trigger a rendering of the view which
        // leads to a new card that appears within our discounts area.
        onAddDiscount() {
            const promotionDiscountRepository = this.repositoryFactory.create(this.discounts.entity, this.discounts.source);
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
            this.discounts.remove(discount.id);
        },
    },
};
