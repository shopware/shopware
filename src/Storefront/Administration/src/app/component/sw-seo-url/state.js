// Store
export default {
    namespaced: true,

    state() {
        return {
            salesChannelCollection: null,
            seoUrlCollection: null,
            originalSeoUrls: [],
            defaultSeoUrl: null,
            currentSeoUrl: null,
        };
    },

    mutations: {
        setSeoUrlCollection(state, seoUrlCollection) {
            state.seoUrlCollection = seoUrlCollection;
        },

        setOriginalSeoUrls(state, originalSeoUrls) {
            state.originalSeoUrls = originalSeoUrls;
        },

        setCurrentSeoUrl(state, currentSeoUrl) {
            state.currentSeoUrl = currentSeoUrl;
        },

        setDefaultSeoUrl(state, defaultSeoUrl) {
            state.defaultSeoUrl = defaultSeoUrl;
        },

        setSalesChannelCollection(state, salesChannelCollection) {
            state.salesChannelCollection = salesChannelCollection;
        },

    },

    getters: {
        isLoading: (state) => {
            return state.loading;
        },

        getNewOrModifiedUrls: (state) => () => {
            const seoUrls = [];

            state.seoUrlCollection.forEach((seoUrl) => {
                const originalSeoUrl = state.originalSeoUrls.find((url) => {
                    return url.id === seoUrl.id;
                });

                if (originalSeoUrl && originalSeoUrl.seoPathInfo === seoUrl.seoPathInfo) {
                    return;
                }

                if (!originalSeoUrl && !seoUrl.seoPathInfo) {
                    return;
                }

                seoUrls.push(seoUrl);
            });

            return seoUrls;
        }
    }
};
