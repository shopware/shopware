const { Utils } = Shopware;

export default {
    namespaced: true,

    state() {
        return {
            googleShoppingAccount: null,
            isLoadingMerchant: false,
            merchantInfo: null,
            merchantStatus: null,
            storeVerification: null
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
            state.merchantInfo = null;
            state.merchantStatus = null;
            state.storeVerification = null;
        },

        setIsLoadingMerchant(state, isLoadingMerchant) {
            state.isLoadingMerchant = isLoadingMerchant;
        },

        setMerchantInfo(state, merchantInfo) {
            state.merchantInfo = merchantInfo;
        },

        setMerchantStatus(state, merchantStatus) {
            state.merchantStatus = merchantStatus;
        },

        setStoreVerification(state, storeVerification) {
            state.storeVerification = storeVerification;
        },

        setTermsOfService(state, acceptance) {
            state.googleShoppingAccount = {
                ...state.googleShoppingAccount,
                tosAcceptedAt: acceptance
            };
        }
    },

    getters: {
        googleShoppingMerchantAccount(state) {
            return Utils.get(state, 'googleShoppingAccount.googleShoppingMerchantAccount', null);
        },

        isIncompleteVerification(state) {
            if (!state.storeVerification) {
                return true;
            }

            return Object.values(state.storeVerification).includes(false);
        },

        needToCompleteTheSetup(state, getters) {
            if (!state.googleShoppingAccount) {
                return 'step-1';
            }

            if (!state.googleShoppingAccount.googleShoppingMerchantAccount) {
                return 'step-3';
            }

            if (getters.isIncompleteVerification) {
                return 'step-4';
            }

            if (!state.googleShoppingAccount.tosAcceptedAt) {
                return 'step-5';
            }

            return '';
        }
    }
};
