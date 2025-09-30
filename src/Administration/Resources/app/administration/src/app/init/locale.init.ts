/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeLocaleService() {
    const factoryContainer = Shopware.Application.getContainer('factory');
    const localeFactory = factoryContainer.locale;
    const snippetService = Shopware.Service('snippetService');

    if (!snippetService) {
        throw new Error('Snippet service not found. Snippets could not be loaded');
    }

    const locales = await snippetService.getLocales();

    Object.values(locales).forEach((locale) => {
        localeFactory.register(locale, {});
    });

    await snippetService.getSnippets(localeFactory);

    return localeFactory;
}
