import deDEMessages from 'src/app/snippets/de-DE.json';
import enGBMessages from 'src/app/snippets/en-GB.json';

export default function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const moduleFactory = factoryContainer.module;
    const localeFactory = factoryContainer.locale;

    // Register default snippets
    localeFactory.register('de-DE', deDEMessages);
    localeFactory.register('en-GB', enGBMessages);

    // Extend default snippets with module specific snippets
    const moduleSnippets = moduleFactory.getModuleSnippets();
    Object.keys(moduleSnippets).forEach((key) => {
        localeFactory.extend(key, moduleSnippets[key]);
    });

    return localeFactory;
}
