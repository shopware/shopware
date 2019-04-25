import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-detail-discounts.html.twig';
import DiscountTypes from './../../common/discount-type';
import DiscountScopes from './../../common/discount-scope';
import './sw-promotion-detail-discounts.scss';

Component.register('sw-promotion-detail-discounts', {
    inject: ['repositoryFactory', 'context'],
    template,

    props: {
        promotion: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            discounts: [],
            isLoading: false,
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
        }
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.$parent.$parent.$parent.$on('save', this.onSave);
            this.repository = this.repositoryFactory.create('promotion_discount');

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('promotionId', this.promotion.id)
            );
            criteria.addAssociation('discountRules');

            this.repository.search(criteria, this.context).then((response) => {
                this.discounts = Object.values(response.items);
                this.isLoading = false;
            });
        },
        // This function saves all discounts of our promotion.
        onSave() {
            this.repository.sync(this.discounts, this.context);
        },
        // This function adds a new blank discount object to our promotion.
        // It will automatically trigger a rendering of the view which
        // leads to a new card that appears within our discounts area.
        onAddDiscount() {
            const newDiscount = this.repository.create(this.context);
            newDiscount.promotionId = this.promotion.id;
            newDiscount.scope = DiscountScopes.CART;
            newDiscount.type = DiscountTypes.PERCENTAGE;
            newDiscount.value = 0;
            newDiscount.considerAdvancedRules = false;

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

            this.repository.delete(this.deleteDiscountId, this.context);
            this.discounts = this.discounts.filter((discount) => {
                return discount.id !== this.deleteDiscountId;
            });
            this.deleteDiscountId = null;
        }
    }
});
