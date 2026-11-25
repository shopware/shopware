/* eslint no-console: 0 */
/* global __SW_HOT_MODE_EXTENSION_ENTRIES__ */

/**
 * Warns about extension scripts that the hot mode compiles but the page does not load.
 *
 * The Storefront only adds an extension script to `theme_scripts()` if its production build
 * `Resources/app/storefront/dist/storefront/js/<name>/<name>.js` exists.
 * The hot mode template rewrites those scripts to the hot proxy.
 * Without a production build, webpack compiles the entry, but the page never requests it.
 *
 * @sw-package framework
 * @private
 */

export const LOG_PREFIX = '[sw-hot-mode]';

/**
 * @param {string[]} entryNames - technical names of the extension entries that webpack compiles
 * @param {Document} doc
 * @returns {string[]} the entry names without a script on the page
 */
export function findMissingExtensionScripts(entryNames, doc = document) {
    const sources = [...doc.querySelectorAll('script[src]')].map((script) => script.getAttribute('src'));

    return entryNames.filter((name) => !sources.some((src) => src.includes(`/${name}/${name}.js`)));
}

export function warnAboutMissingExtensionScripts(entryNames, doc = document) {
    const missing = findMissingExtensionScripts(entryNames, doc);
    if (!missing.length) {
        return;
    }

    console.warn([
        `${LOG_PREFIX} The JavaScript of these extensions is compiled, but the page does not load it: ${missing.join(', ')}.`,
        'The Storefront only loads extension scripts that have a production build.',
        'Build them once with "bin/build-storefront.sh" ("composer build:js:storefront" in the Shopware repository),',
        'then run "bin/console cache:clear" and reload the page.',
    ].join('\n'));
}

// Replaced by the webpack DefinePlugin in hot mode.
if (typeof __SW_HOT_MODE_EXTENSION_ENTRIES__ !== 'undefined') {
    warnAboutMissingExtensionScripts(__SW_HOT_MODE_EXTENSION_ENTRIES__);
}
