import { Component } from 'src/core/shopware';
import template from './sw-promotion-detail-discounts.html.twig';
import DiscountTypes from './../../common/discount-type';
import DiscountScopes from './../../common/discount-scope';
import './sw-promotion-detail-discounts.scss';

Component.register('sw-promotion-detail-discounts', {
    template,

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    },
    data() {
        return {
            discounts: [],
            isLoading: false,
            deleteDiscountId: null
        };
    },
    created() {
        this.createdComponent();
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
        }
    },
    methods: {
        createdComponent() {
            this.loadDiscounts();
        },
        // This function loads all discounts of our promotion from the association store.
        // The loaded discounts will then be assigned to our bound discounts
        // array that is used to render the discount cards.
        loadDiscounts() {
            this.isLoading = true;
            this.discountAssociationStore.getList().then((response) => {
                this.discounts = response.items;
                this.isLoading = false;
            });
        },
        // This function adds a new blank discount object to our promotion.
        // It will automatically trigger a rendering of the view which
        // leads to a new card that appears within our discounts area.
        onAddDiscount() {
            const newDiscount = this.discountAssociationStore.create();
            newDiscount.setLocalData({
                scope: DiscountScopes.CART,
                type: DiscountTypes.PERCENTAGE,
                value: 0,
                graduated: false
            });

            this.promotion.discounts.push(newDiscount);
            this.discounts.push(newDiscount);
        },
        // This function triggers the modal view to be rendered.
        // The provided discount ID will be saved to determine the discount
        // if the user confirms the deletion process.
        onStartDeleteDiscount(discountID) {
            this.deleteDiscountId = discountID;
        },
        // This function aborts the delete process by
        // resetting the discountId which will automatically
        // hide the modal view.
        onCancelDeleteDiscount() {
            this.deleteDiscountId = null;
        },
        // This function is used to confirm the delete process.
        // If a discountId has been set before, it will remove that
        // one from our list of discounts.
        onConfirmDeleteDiscount() {
            if (this.deleteDiscountId === null) {
                return;
            }
            this.discountAssociationStore.forEach((item) => {
                if (item.id === this.deleteDiscountId) {
                    item.delete();
                }
            });
            this.discounts = this.discounts.filter((discount) => {
                return discount.id !== this.deleteDiscountId;
            });
            this.deleteDiscountId = null;
        }
    }
});
