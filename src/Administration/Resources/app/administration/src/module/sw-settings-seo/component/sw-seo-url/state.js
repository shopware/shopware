/**
 * @package buyers-experience
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

// Store
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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

        getNewOrModifiedUrls: (state) => {
            return () => {
                const seoUrls = [];

                state.seoUrlCollection.forEach((seoUrl) => {
                    if (seoUrl.seoPathInfo === null) {
                        return;
                    }

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
            };
        },
    },
};
