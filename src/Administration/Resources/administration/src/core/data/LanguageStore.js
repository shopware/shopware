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
     * @return {Promise<{}>}
     */
    setCurrentId(languageId) {
        Shopware.Context.Api.languageId = languageId;
        localStorage.setItem('sw-admin-current-language', languageId);

        return this.getByIdAsync(languageId);
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

    getDefaultLanguageIds() {
        return this.defaultLanguageIds;
    }

    get systemLanguageId() {
        return Shopware.Context.Api.systemLanguageId;
    }

    set systemLanguageId(newValue) {
        Shopware.Context.Api.systemLanguageId = newValue;
    }

    get currentLanguageId() {
        return Shopware.Context.Api.languageId;
    }

    set currentLanguageId(newValue) {
        Shopware.Context.Api.languageId = newValue;
    }

    get defaultLanguageIds() {
        return Shopware.Context.Api.defaultLanguageIds;
    }

    set defaultLanguageIds(newValue) {
        Shopware.Context.Api.defaultLanguageIds = newValue;
    }

    init() {
        return this.getByIdAsync(this.getCurrentId());
    }
}
