const { Utils } = Shopware;

export default {
    namespaced: true,

    state() {
        return {
            googleShoppingAccount: null
        };
    },

    mutations: {
        setGoogleShoppingAccount(state, account) {
            state.googleShoppingAccount = account;
        },

        setGoogleShoppingMerchantAccount(state, account) {
            state.googleShoppingAccount = {
                ...state.googleShoppingAccount,
                googleShoppingMerchantAccount: account
            };
        },

        removeGoogleShoppingAccount(state) {
            state.googleShoppingAccount = null;
        }
    },

    getters: {
        googleShoppingMerchantAccount(state) {
            return Utils.get(state, 'googleShoppingAccount.googleShoppingMerchantAccount', null);
        },

        needToCompleteTheSetup(state) {
            if (!state.googleShoppingAccount) {
                return 'step-1';
            }

            if (!state.googleShoppingAccount.googleShoppingMerchantAccount) {
                return 'step-3';
            }

            return '';
        }
    }
};
