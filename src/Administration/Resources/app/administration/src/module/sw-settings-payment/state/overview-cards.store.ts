import type { Module } from 'vuex';
import type { uiModulePaymentOverviewCard } from '@shopware-ag/admin-extension-sdk/es/ui/module/payment/overviewCard';

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

export default PaymentOverviewCardsStore;
export type { PaymentOverviewCardState, PaymentOverviewCard };
