const { Criteria } = Shopware.Data;

export default {
    namespaced: true,

    state() {
        return {
            landingPage: null,
            category: null,
            customFieldSets: [],
            landingPagesToDelete: undefined,
            categoriesToDelete: undefined,
            defaultLayout: null,
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
        },

        setLandingPagesToDelete(state, { landingPagesToDelete }) {
            state.landingPagesToDelete = landingPagesToDelete;
        },

        setCategoriesToDelete(state, { categoriesToDelete }) {
            state.categoriesToDelete = categoriesToDelete;
        },

        setDefaultLayout(state, defaultLayout) {
            state.defaultLayout = defaultLayout?.id;
        },
    },

    actions: {
        setActiveLandingPage({ commit }, payload) {
            commit('setActiveLandingPage', payload);
        },

        loadActiveLandingPage({ commit }, { repository, id, apiContext, criteria }) {
            if (id === 'create') {
                const landingPage = repository.create(apiContext);
                landingPage.cmsPageId = null;
                commit('setActiveLandingPage', { landingPage });
                return Promise.resolve();
            }

            if (!criteria) {
                criteria = new Criteria();
            }

            return repository.get(id, apiContext, criteria).then((landingPage) => {
                commit('setActiveLandingPage', { landingPage });
            });
        },

        setActiveCategory({ commit }, payload) {
            commit('setActiveCategory', payload);
        },

        loadActiveCategory({ commit }, { repository, id, apiContext, criteria }) {
            if (!criteria) {
                criteria = new Criteria();
            }

            return repository.get(id, apiContext, criteria).then((category) => {
                category.isColumn = false;
                if (category.parentId !== null) {
                    const parentCriteria = new Criteria();
                    parentCriteria.addAssociation('footerSalesChannels');

                    return repository.get(category.parentId, apiContext, parentCriteria).then((parent) => {
                        category.parent = parent;

                        category.isColumn = category.parent !== undefined
                            && category.parent.footerSalesChannels !== undefined
                            && category.parent.footerSalesChannels.length !== 0;

                        return Promise.resolve(category);
                    });
                }

                return Promise.resolve(category);
            }).then((category) => {
                commit('setActiveCategory', { category });
            });
        },
    },
};
