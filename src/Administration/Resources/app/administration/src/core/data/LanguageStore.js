import EntityStore from './EntityStore';

/**
 * @module core/data/LanguageStore
 * @deprecated tag:v6.4.0 - use Context State instead
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
     * @deprecated tag:v6.4.0 - use `Shopware.State.commit('context/setApiLanguageId', languageId)` instead
     * @param {String} languageId
     */
    setCurrentId(languageId) {
        Shopware.State.commit('context/setApiLanguageId', languageId);
    }

    /**
     * Get the current languageId
     *
     * @deprecated tag:v6.4.0 - use `Shopware.Context.api.languageId` instead
     * @returns {String}
     */
    getCurrentId() {
        return this.currentLanguageId;
    }

    /**
     * Get the current language entity proxy
     *
     * @deprecated tag:v6.4.0 - use the Repository instead
     * @return {EntityProxy}
     */
    getCurrentLanguage() {
        return this.store[this.currentLanguageId];
    }

    /**
     * @deprecated tag:v6.4.0
     */
    getLanguageStore() {
        return this;
    }

    /**
     * @deprecated tag:v6.4.0 - use `Shopware.Context.api.systemLanguageId` instead
     */
    get systemLanguageId() {
        return Shopware.Context.api.systemLanguageId;
    }

    /**
     * @deprecated tag:v6.4.0 - use `Shopware.Context.api.systemLanguageId` instead
     */
    set systemLanguageId(newValue) {
        Shopware.State.commit('context/setApiSystemLanguageId', newValue);
    }

    /**
     * @deprecated tag:v6.4.0 - use `Shopware.Context.api.languageId` instead
     */
    get currentLanguageId() {
        return Shopware.Context.api.languageId;
    }

    /**
     * @deprecated tag:v6.4.0 - use `Shopware.Context.api.languageId` instead
     */
    set currentLanguageId(newValue) {
        Shopware.State.commit('context/setApiLanguageId', newValue);
    }

    /**
     * @deprecated tag:v6.4.0
     */
    init() {
        return this.getByIdAsync(this.getCurrentId());
    }
}
