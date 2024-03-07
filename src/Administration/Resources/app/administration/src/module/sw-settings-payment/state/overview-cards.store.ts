import type { Module } from 'vuex';
import type { uiModulePaymentOverviewCard } from '@shopware-ag/meteor-admin-sdk/es/ui/module/payment/overview-card';

/**
 * @package checkout
 */

type PaymentOverviewCard = Omit<uiModulePaymentOverviewCard, 'responseType'>

interface PaymentOverviewCardState {
    cards: PaymentOverviewCard[]
}

const PaymentOverviewCardsStore: Module<PaymentOverviewCardState, VuexRootState> = {
    namespaced: true,

    state: (): PaymentOverviewCardState => ({
        cards: [],
    }),

    mutations: {
        add(state, paymentOverviewCard: uiModulePaymentOverviewCard) {
            state.cards.push(paymentOverviewCard);
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default PaymentOverviewCardsStore;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { PaymentOverviewCardState, PaymentOverviewCard };
