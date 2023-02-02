/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const localeFactory = factoryContainer.locale;

    // Register default snippets
    localeFactory.register('de-DE', {});
    localeFactory.register('en-GB', {});

    const snippetService = Shopware.Service('snippetService');

    if (snippetService) {
        await snippetService.getSnippets(localeFactory);
    }

    return localeFactory;
}
