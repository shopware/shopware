/**
 * @sw-package framework
 */
import type SnippetApiService from 'src/core/service/api/snippet.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeLocaleService() {
    const factoryContainer = Shopware.Application.getContainer('factory');
    const localeFactory = factoryContainer.locale;

    const snippetService = Shopware.Service('snippetService') as SnippetApiService;
    if (snippetService) {
        const locales = await snippetService.getLocales();
        Array.from(locales).forEach((locale) => {
            localeFactory.register(locale, {});
        });

        await snippetService.getSnippets(localeFactory);
    }

    return localeFactory;
}
