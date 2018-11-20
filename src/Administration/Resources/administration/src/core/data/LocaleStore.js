/**
 * @module core/data/LocaleStore
 */
import { Application } from 'src/core/shopware';

class LocaleStore {
    constructor(locale) {
        this.locale = locale;
    }

    /**
     * Set the current locale state.
     *
     * @param {String} locale
     */
    setLocale(locale) {
        const factoryContainer = Application.getContainer('factory');
        const localeFactory = factoryContainer.locale;

        this.locale = locale;
        localeFactory.setLocale(locale);
    }
}

export default LocaleStore;
