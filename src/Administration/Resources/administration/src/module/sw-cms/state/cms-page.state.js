Shopware.State.registerModule('cmsPageState', {
    namespaced: true,

    state: {
        currentPage: null,
        currentMappingEntity: null,
        currentMappingTypes: {},
        currentDemoEntity: null,
        pageEntityName: 'cms_page',
        defaultMediaFolderId: null,
        currentCmsDeviceView: 'desktop'
    },

    mutations: {
        setCurrentPage(state, page) {
            state.currentPage = page;
        },

        removeCurrentPage(state) {
            state.currentPage = null;
        },

        setCurrentMappingEntity(state, entity) {
            state.currentMappingEntity = entity;
        },

        removeCurrentMappingEntity(state) {
            state.currentMappingEntity = null;
        },

        setCurrentMappingTypes(state, types) {
            state.currentMappingTypes = types;
        },

        removeCurrentMappingTypes(state) {
            state.currentMappingTypes = {};
        },

        setCurrentDemoEntity(state, entity) {
            state.currentDemoEntity = entity;
        },

        removeCurrentDemoEntity(state) {
            state.currentDemoEntity = null;
        },

        setPageEntityName(state, entity) {
            state.pageEntityName = entity;
        },

        removePageEntityName(state) {
            state.pageEntityName = 'cms_page';
        },

        setDefaultMediaFolderId(state, folderId) {
            state.defaultMediaFolderId = folderId;
        },

        removeDefaultMediaFolderId(state) {
            state.defaultMediaFolderId = null;
        },

        setCurrentCmsDeviceView(state, view) {
            state.currentCmsDeviceView = view;
        },

        removeCurrentCmsDeviceView(state) {
            state.currentCmsDeviceView = 'desktop';
        }
    },

    actions: {
        resetCmsPageState({ commit }) {
            commit('removeCurrentPage');
            commit('removeCurrentMappingEntity');
            commit('removeCurrentMappingTypes');
            commit('removeCurrentDemoEntity');
        }
    }
});
