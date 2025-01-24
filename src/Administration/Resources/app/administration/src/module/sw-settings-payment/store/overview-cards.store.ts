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
