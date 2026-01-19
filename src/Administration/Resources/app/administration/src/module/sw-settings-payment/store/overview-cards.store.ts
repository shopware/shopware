import type { uiModulePaymentOverviewCard } from '@shopware-ag/meteor-admin-sdk/es/ui/module/payment/overview-card';

/**
 * @sw-package checkout
 */

type PaymentOverviewCard = Omit<uiModulePaymentOverviewCard, 'responseType'>;

interface PaymentOverviewCardState {
    cards: PaymentOverviewCard[];
}
const paymentOverviewCardStore = Shopware.Store.register({
    id: 'paymentOverviewCard',

    state: (): PaymentOverviewCardState => ({
        cards: [],
    }),

    actions: {
        add(paymentOverviewCard: PaymentOverviewCard) {
            // Check if card with same positionId already exists to prevent duplicates on HMR
            const existingIndex = this.cards.findIndex((item) => item.positionId === paymentOverviewCard.positionId);

            if (existingIndex !== -1) {
                // Update existing card
                this.cards[existingIndex] = paymentOverviewCard;
                return;
            }

            this.cards.push(paymentOverviewCard);
        },
    },
});

/**
 * @private
 */
export default paymentOverviewCardStore;

type PaymentOverviewCardStore = ReturnType<typeof paymentOverviewCardStore>;

/**
 * @private
 */
export type { PaymentOverviewCard, PaymentOverviewCardStore };
