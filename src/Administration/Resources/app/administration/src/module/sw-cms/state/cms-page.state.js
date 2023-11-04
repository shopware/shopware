/**
 * @private
 * @package content
 */
Shopware.State.registerModule('cmsPageState', {
    namespaced: true,

    state: {
        currentPage: null,
        currentPageType: null,
        currentMappingEntity: null,
        currentMappingTypes: {},
        currentDemoEntity: null,
        currentDemoProducts: [],
        pageEntityName: 'cms_page',
        defaultMediaFolderId: null,
        currentCmsDeviceView: 'desktop',
        selectedSection: null,
        selectedBlock: null,
        isSystemDefaultLanguage: true,
    },

    mutations: {
        setCurrentPage(state, page) {
            state.currentPage = page;
        },

        removeCurrentPage(state) {
            state.currentPage = null;
        },

        setCurrentPageType(state, type) {
            state.currentPageType = type;
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

        setCurrentDemoProducts(state, products) {
            state.currentDemoProducts = products;
        },

        removeCurrentDemoProducts(state) {
            state.currentDemoProducts = [];
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
        },

        setSelectedSection(state, section) {
            state.selectedSection = section;
        },

        removeSelectedSection(state) {
            state.selectedSection = null;
        },

        setSelectedBlock(state, block) {
            state.selectedBlock = block;
        },

        removeSelectedBlock(state) {
            state.selectedBlock = null;
        },

        setIsSystemDefaultLanguage(state, isSystemDefaultLanguage) {
            state.isSystemDefaultLanguage = isSystemDefaultLanguage;
        },
    },

    actions: {
        resetCmsPageState({ commit }) {
            commit('removeCurrentPage');
            commit('removeCurrentMappingEntity');
            commit('removeCurrentMappingTypes');
            commit('removeCurrentDemoEntity');
            commit('removeCurrentDemoProducts');
        },

        setSection({ commit }, section) {
            commit('removeSelectedBlock');
            commit('setSelectedSection', section);
        },

        setBlock({ commit }, block) {
            commit('removeSelectedSection');
            commit('setSelectedBlock', block);
        },
    },
});
