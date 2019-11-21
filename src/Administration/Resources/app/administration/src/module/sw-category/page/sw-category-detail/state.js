const { Criteria } = Shopware.Data;

export default {
    namespaced: true,

    state() {
        return {
            category: null,
            customFieldSets: []
        };
    },

    mutations: {
        setActiveCategory(state, { category }) {
            state.category = category;
        },

        setCustomFieldSets(state, newCustomFieldSets) {
            state.customFieldSets = newCustomFieldSets;
        }
    },

    actions: {
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
