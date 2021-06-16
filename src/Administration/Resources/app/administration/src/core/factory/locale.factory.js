/**
 * @module core/factory/locale
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { object } from 'src/core/service/util.service';

export default {
    getLocaleByName,
    getLocaleRegistry,
    register,
    extend,
    getBrowserLanguage,
    getBrowserLanguages,
    getLastKnownLocale,
    storeCurrentLocale,
};

/**
 * Registry which holds all locales including the interface translations
 *
 * @type {Map}
 */
const localeRegistry = new Map();

/**
 * Defines the default locale
 *
 * @type {String}
 */
const defaultLocale = 'en-GB';

/**
 * Defines the key of the localStorage item
 *
 * @type {String}
 */
const localStorageKey = 'sw-admin-locale';

/**
 * Get the complete locale registry
 * @returns {Map}
 */
function getLocaleRegistry() {
    return localeRegistry;
}

/**
 * Registers a new locale
 *
 * @param {String} localeName
 * @param {Object} [localeMessages={}]
 * @returns {Boolean|String}
 */
function register(localeName, localeMessages = {}) {
    if (!localeName || !localeName.length) {
        warn(
            'LocaleFactory',
            'A locale always needs a name',
        );
        return false;
    }

    if (localeName.split('-').length < 2) {
        warn(
            'LocaleFactory',
            'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode] for example "en-US"',
        );
        return false;
    }

    if (localeRegistry.has(localeName)) {
        warn(
            'LocaleFactory',
            `The locale "${localeName}" is registered already.`,
            'Please use the extend method to extend and override certain keys',
        );

        return false;
    }

    localeRegistry.set(localeName, localeMessages);

    return localeName;
}

/**
 * Extends a given locale with the provided translations
 *
 * @param {String} localeName
 * @param {Object} [localeMessages={}]
 * @returns {Boolean|String}
 */
function extend(localeName, localeMessages = {}) {
    if (localeName.split('-').length < 2) {
        warn(
            'LocaleFactory',
            'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode]] for example "en-US"',
        );
        return false;
    }

    if (!localeRegistry.has(localeName)) {
        warn(
            'LocaleFactory',
            `The locale "${localeName}" doesn't exists. Please use the register method to register a new locale`,
        );
        return false;
    }

    const originalMessages = localeRegistry.get(localeName);
    localeRegistry.set(localeName, object.merge(originalMessages, localeMessages));

    return localeName;
}

/**
 * Get translations for a specific locale
 *
 * @param {String} localeName
 * @returns {Boolean|String}
 */
function getLocaleByName(localeName) {
    if (!localeRegistry.has(localeName)) {
        return false;
    }

    return localeRegistry.get(localeName);
}

/**
 * Checks if the {@link localStorage} has an item associated to the {@link localStorageKey} key.
 *
 * @returns {String}
 */
function getLastKnownLocale() {
    let localeName = getBrowserLanguage();

    if (window.localStorage.getItem(localStorageKey) !== null) {
        localeName = window.localStorage.getItem(localStorageKey);
    }

    return localeName;
}

/**
 * Terminates the browser language and checks if the language is in the registry.
 * If this is not the case the {@link defaultLocale} will be returned.
 *
 * @returns {String}
 */
function getBrowserLanguage() {
    const shortLanguageCodes = new Map();
    localeRegistry.forEach((messages, locale) => {
        const lang = locale.split('-')[0];
        shortLanguageCodes.set(lang.toLowerCase(), locale);
    });

    let matchedLanguage = null;

    getBrowserLanguages().forEach((language) => {
        if (!matchedLanguage && localeRegistry.has(language)) {
            matchedLanguage = language;
        }

        if (!matchedLanguage && shortLanguageCodes.has(language)) {
            matchedLanguage = shortLanguageCodes.get(language);
        }
    });

    return matchedLanguage || defaultLocale;
}

/**
 * Looks up all available browser languages.
 *
 * @returns {Array}
 */
function getBrowserLanguages() {
    const languages = [];

    if (navigator.language) {
        languages.push(navigator.language);
    }

    // Chrome only
    if (navigator.languages?.length) {
        navigator.languages.forEach((lang) => {
            languages.push(lang);
        });
    }

    if (navigator.userLanguage) {
        languages.push(navigator.userLanguage);
    }

    if (navigator.systemLanguage) {
        languages.push(navigator.systemLanguage);
    }

    return languages;
}

/**
 * Sets up the DOM and http client to use the provided locale
 *
 * @param {String} localeName
 * @param {AxiosInstance|null} [httpClient=null]
 * @returns {String}
 */
function storeCurrentLocale(localeName) {
    // Necessary for testing purpose
    if (typeof document === 'object') {
        const shortLocaleName = localeName.split('-')[0];
        document.querySelector('html').setAttribute('lang', shortLocaleName);
    }

    window.localStorage.setItem(localStorageKey, localeName);

    return localeName;
}
