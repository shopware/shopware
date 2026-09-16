/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeLocaleService() {
    const factoryContainer = Shopware.Application.getContainer('factory');
    const localeFactory = factoryContainer.locale;
    const snippetService = Shopware.Service('snippetService');

    if (!snippetService) {
        console.warn('Snippet service not found. Snippets could not be loaded');

        return Promise.resolve(localeFactory);
    }

    // Load locales and snippets before rendering to avoid showing raw snippet keys
    return snippetService
        .getLocales()
        .then((locales) => {
            Object.values(locales).forEach((locale) => {
                localeFactory.register(locale, {});
            });

            const { systemLanguageId } = Shopware.Context.api;
            const systemFallbackLocale = systemLanguageId ? (locales[systemLanguageId] ?? null) : null;

            localeFactory.setSystemFallbackLocale(systemFallbackLocale);

            return snippetService.getSnippets(localeFactory);
        })
        .then(() => localeFactory)
        .catch((error) => {
            console.error('Error loading locales or snippets:', error);

            return localeFactory;
        });
}
