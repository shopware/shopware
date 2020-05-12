const { Utils } = Shopware;

export default {
    namespaced: true,

    state() {
        return {
            googleShoppingAccount: null,
            isLoadingMerchant: false,
            merchantInfo: null,
            merchantStatus: null
        };
    },

    mutations: {
        setGoogleShoppingAccount(state, googleShoppingAccount) {
            state.googleShoppingAccount = googleShoppingAccount;
        },

        setGoogleShoppingMerchantAccount(state, account) {
            state.googleShoppingAccount = {
                ...state.googleShoppingAccount,
                googleShoppingMerchantAccount: account
            };
        },

        removeGoogleShoppingAccount(state) {
            state.googleShoppingAccount = null;
        },

        setIsLoadingMerchant(state, isLoadingMerchant) {
            state.isLoadingMerchant = isLoadingMerchant;
        },

        setMerchantInfo(state, merchantInfo) {
            state.merchantInfo = merchantInfo;
        },

        setMerchantStatus(state, merchantStatus) {
            state.merchantStatus = merchantStatus;
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
