const { Criteria } = Shopware.Data;

export default {
    namespaced: true,

    state() {
        return {
            category: null,
            customFieldSets: [],
            loading: {
                customFieldSets: false
            }
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        }
    },

    mutations: {
        setActiveCategory(state, { category }) {
            state.category = category;
        },

        setLoading(state, value) {
            const name = value[0];
            const data = value[1];

            if (typeof data !== 'boolean') {
                return false;
            }

            if (state.loading[name] !== undefined) {
                state.loading[name] = data;
                return true;
            }
            return false;
        },

        setAttributeSet(state, newAttributeSets) {
            state.customFieldSets = newAttributeSets;
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
