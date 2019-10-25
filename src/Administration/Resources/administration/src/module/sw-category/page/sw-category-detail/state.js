const { Criteria } = Shopware.Data;

export default {
    namespaced: true,

    state() {
        return {
            category: null
        };
    },

    mutations: {
        setActiveCategory(state, { category }) {
            state.category = category;
        }
    },

    actions: {
        setActiveCategory({ commit }, payload) {
            commit('setActiveCategory', payload);
        },

        loadActiveCategory({ commit }, { repository, id, context }) {
            const criteria = new Criteria();
            criteria.addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels');

            return repository.get(id, context, criteria).then((category) => {
                commit('setActiveCategory', { category });
            });
        }
    }
};
