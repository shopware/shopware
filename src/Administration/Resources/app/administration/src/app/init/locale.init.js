import deDEMessages from 'src/app/snippet/de-DE.json';
import enGBMessages from 'src/app/snippet/en-GB.json';

export default async function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const localeFactory = factoryContainer.locale;

    // Register default snippets
    localeFactory.register('de-DE', deDEMessages);
    localeFactory.register('en-GB', enGBMessages);

    const snippetService = Shopware.Service('snippetService');

    if (snippetService) {
        await snippetService.getSnippets(localeFactory);
    }

    return localeFactory;
}
