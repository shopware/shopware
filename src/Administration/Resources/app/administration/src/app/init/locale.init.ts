/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeLocaleService() {
    const factoryContainer = Shopware.Application.getContainer('factory');
    const localeFactory = factoryContainer.locale;
    const snippetService = Shopware.Service('snippetService');

    if (!snippetService) {
        // eslint-disable-next-line no-console
        console.warn('Snippet service not found. Snippets could not be loaded');

        return localeFactory;
    }

    // Load locales and snippets parallel to speed up the boot process
    void snippetService
        .getLocales()
        .then((locales) => {
            Object.values(locales).forEach((locale) => {
                localeFactory.register(locale, {});
            });

            return snippetService.getSnippets(localeFactory);
        })
        .catch((error) => {
            console.error('Error loading locales or snippets:', error);
        });

    return localeFactory;
}
