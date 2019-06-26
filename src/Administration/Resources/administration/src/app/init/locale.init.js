import deDEMessages from 'src/app/snippet/de-DE.json';
import deDEErrorMessages from 'src/app/snippet/error-codes/de-DE.json';
import enGBMessages from 'src/app/snippet/en-GB.json';
import enGBErrorMessages from 'src/app/snippet/error-codes/en-GB.json';

export default function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const moduleFactory = factoryContainer.module;
    const localeFactory = factoryContainer.locale;

    // Register default snippets
    localeFactory.register('de-DE', deDEMessages);
    localeFactory.extend('de-DE', deDEErrorMessages);

    localeFactory.register('en-GB', enGBMessages);
    localeFactory.extend('en-GB', enGBErrorMessages);

    // Extend default snippets with module specific snippets
    const moduleSnippets = moduleFactory.getModuleSnippets();
    Object.keys(moduleSnippets).forEach((key) => {
        localeFactory.extend(key, moduleSnippets[key]);
    });

    return localeFactory;
}
