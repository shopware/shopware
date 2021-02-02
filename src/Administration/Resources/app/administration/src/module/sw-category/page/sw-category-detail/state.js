const { Criteria } = Shopware.Data;

export default {
    namespaced: true,

    state() {
        return {
            landingPage: null,
            category: null,
            customFieldSets: []
        };
    },

    mutations: {
        setActiveLandingPage(state, { landingPage }) {
            state.landingPage = landingPage;
        },

        setActiveCategory(state, { category }) {
            state.category = category;
        },

        setCustomFieldSets(state, newCustomFieldSets) {
            state.customFieldSets = newCustomFieldSets;
        }
    },

    actions: {
        setActiveLandingPage({ commit }, payload) {
            commit('setActiveLandingPage', payload);
        },

        loadActiveLandingPage({ commit }, { repository, id, apiContext }) {
            if (id === 'create') {
                const landingPage = repository.create(apiContext);
                landingPage.cmsPageId = null;
                commit('setActiveLandingPage', { landingPage });
                return Promise.resolve();
            }

            const criteria = new Criteria();

            criteria.addAssociation('tags');
            criteria.addAssociation('salesChannels');

            return repository.get(id, apiContext, criteria).then((landingPage) => {
                commit('setActiveLandingPage', { landingPage });
            });
        },

        setActiveCategory({ commit }, payload) {
            commit('setActiveCategory', payload);
        },

        loadActiveCategory({ commit }, { repository, id, apiContext }) {
            const criteria = new Criteria();

            criteria.getAssociation('seoUrls')
                .addFilter(Criteria.equals('isCanonical', true));

            criteria.addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels');


            return repository.get(id, apiContext, criteria).then((category) => {
                commit('setActiveCategory', { category });
            });
        }
    }
};
