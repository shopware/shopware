import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-detail-discounts.html.twig';
import DiscountTypes from './../../common/discount-type';
import DiscountScopes from './../../common/discount-scope';

/*
 * TODO ADD PAGINATION FOR DISCOUNTS
 * TODO ADD global state and remove registerSaveCall process
 */
Component.register('sw-promotion-detail-discounts', {
    template,

    inject: [
        'repositoryFactory',
        'context',
        'registerSaveCall',
        'removeSaveCall'
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        }
    },

    data() {
        return {
            discounts: [],
            isLoading: true,
            deleteDiscountId: null,
            repository: null
        };
    },

    computed: {
        discountAssociationStore() {
            return this.promotion.getAssociation('discounts');
        },

        // Gets if the card view is in deleting mode.
        // If so, it will automatically render the modal view
        // for the confirmation of the delete process.
        isDeleting() {
            return (this.deleteDiscountId != null);
        },

        promotionDiscountRepository() {
            return this.repositoryFactory.create(
                this.promotion.discounts.entity,
                this.promotion.discounts.source
            );
        }
    },

    watch: {
        promotion(value) {
            if (value) {
                this.loadDiscounts();
            }
        }
    },

    created() {
        this.registerSaveCall(this.onSave);
        this.createdComponent();
    },

    beforeDestroy() {
        this.removeSaveCall(this.onSave);
    },

    methods: {
        createdComponent() {
            if (this.promotion) {
                this.loadDiscounts();
            }
        },

        loadDiscounts() {
            this.isLoading = true;
            return this.promotionDiscountRepository.search(new Criteria(), this.context).then((searchResult) => {
                this.discounts = searchResult;
                this.isLoading = false;
            });
        },

        // This function saves all discounts of our promotion.
        onSave() {
            this.isLoading = true;
            return this.promotionDiscountRepository.sync(this.discounts, this.context).then(() => {
                this.loadDiscounts().then(() => {
                    this.isLoading = false;
                }).catch(() => {
                    this.isLoading = false;
                });
            });
        },

        // This function adds a new blank discount object to our promotion.
        // It will automatically trigger a rendering of the view which
        // leads to a new card that appears within our discounts area.
        onAddDiscount() {
            const newDiscount = this.promotionDiscountRepository.create(this.context);
            newDiscount.promotionId = this.promotion.id;
            newDiscount.scope = DiscountScopes.CART;
            newDiscount.type = DiscountTypes.PERCENTAGE;
            newDiscount.value = 0.01;
            newDiscount.considerAdvancedRules = false;

            this.discounts.push(newDiscount);
        },

        deleteDiscount(discount) {
            if (discount.isNew()) {
                this.discounts.remove(discount.id);
                return;
            }

            this.promotionDiscountRepository.delete(discount.id, this.discounts.context).then(() => {
                this.discounts.remove(discount.id);
            });
        }
    }
});
