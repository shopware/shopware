export default {
    namespaced: true,

    state() {
        return {
            campaign: {},
        };
    },

    mutations: {
        setCampaign(state, campaign) {
            state.campaign = campaign;
        },
    },

    getters: {
        getActiveCampaign(state) {
            if (Shopware.Service('shopwareDiscountCampaignService').isDiscountCampaignActive(state.campaign)) {
                return state.campaign;
            }

            return null;
        },

        getActiveCampaignDataForComponent(state, getters) {
            return (componentName) => {
                return getters.getActiveCampaign?.components?.[componentName] ?? null;
            };
        },
    },
};
