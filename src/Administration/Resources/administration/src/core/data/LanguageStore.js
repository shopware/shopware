import EntityStore from './EntityStore';

/**
 * @module core/data/LanguageStore
 * @deprecated 6.1
 */

export default class LanguageStore extends EntityStore {
    constructor(
        apiService,
        EntityClass,
        currentLanguageId = ''
    ) {
        super('language', apiService, EntityClass);

        if (!currentLanguageId || !currentLanguageId.length) {
            this.setCurrentId(this.systemLanguageId);
        }
    }

    /**
     * Set the current languageId and calls the init method to fetch the data from server if necessary.
     *
     * @param {String} languageId
     */
    setCurrentId(languageId) {
        Shopware.State.commit('context/setApiLanguageId', languageId);
        localStorage.setItem('sw-admin-current-language', languageId);
    }

    /**
     * Get the current languageId
     *
     * @returns {String}
     */
    getCurrentId() {
        return this.currentLanguageId;
    }

    /**
     * Get the current language entity proxy
     *
     * @return {EntityProxy}
     */
    getCurrentLanguage() {
        return this.store[this.currentLanguageId];
    }

    getLanguageStore() {
        return this;
    }

    get systemLanguageId() {
        return Shopware.Context.api.systemLanguageId;
    }

    set systemLanguageId(newValue) {
        Shopware.State.commit('context/setApiSystemLanguageId', newValue);
    }

    get currentLanguageId() {
        return Shopware.Context.api.languageId;
    }

    set currentLanguageId(newValue) {
        Shopware.State.commit('context/setApiLanguageId', newValue);
    }

    init() {
        return this.getByIdAsync(this.getCurrentId());
    }
}
