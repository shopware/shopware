/**
 * @package admin
 *
 * @module core/factory/locale
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { object } from 'src/core/service/util.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
 * @private
 */
export type Snippets = {
    [key: string]: string | Snippets;
};

/**
 * Registry which holds all locales including the interface translations
 */
const localeRegistry = new Map<string, Snippets>();

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
 */
function register(localeName: string, localeMessages: Snippets = {}): boolean | string {
    if (!localeName || !localeName.length) {
        warn('LocaleFactory', 'A locale always needs a name');
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
 */
function extend(localeName: string, localeMessages: Snippets = {}): boolean | string {
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
 */
function getLocaleByName(localeName: string): Snippets | boolean {
    return localeRegistry.get(localeName) || false;
}

/**
 * Checks if the {@link localStorage} has an item associated to the {@link localStorageKey} key.
 */
function getLastKnownLocale(): string {
    let localeName = getBrowserLanguage();

    if (window.localStorage.getItem(localStorageKey) !== null) {
        localeName = window.localStorage.getItem(localStorageKey) as string;
    }

    return localeName;
}

/**
 * Terminates the browser language and checks if the language is in the registry.
 * If this is not the case the {@link defaultLocale} will be returned.
 */
function getBrowserLanguage(): string {
    const shortLanguageCodes = new Map<string, string>();
    localeRegistry.forEach((messages, locale) => {
        const lang = locale.split('-')[0];
        shortLanguageCodes.set(lang.toLowerCase(), locale);
    });

    let matchedLanguage: string | null = null;

    getBrowserLanguages().forEach((language) => {
        if (!matchedLanguage && localeRegistry.has(language)) {
            matchedLanguage = language;
        }

        if (!matchedLanguage && shortLanguageCodes.has(language)) {
            matchedLanguage = shortLanguageCodes.get(language) || null;
        }
    });

    return matchedLanguage || defaultLocale;
}

/**
 * Looks up all available browser languages.
 */
function getBrowserLanguages(): string[] {
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

    // @ts-expect-error
    if (navigator.userLanguage) {
        // @ts-expect-error
        languages.push(navigator.userLanguage);
    }

    // @ts-expect-error
    if (navigator.systemLanguage) {
        // @ts-expect-error
        languages.push(navigator.systemLanguage);
    }

    return languages as string[];
}

/**
 * Sets up the DOM and http client to use the provided locale
 */
function storeCurrentLocale(localeName: string): string {
    // Necessary for testing purpose
    if (typeof document === 'object') {
        const shortLocaleName = localeName.split('-')[0];
        document.querySelector('html')?.setAttribute('lang', shortLocaleName);
    }

    window.localStorage.setItem(localStorageKey, localeName);

    return localeName;
}
