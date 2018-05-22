import deDEMessages from 'src/app/snippets/de-DE.json';
import enGBMessages from 'src/app/snippets/en-GB.json';

export default function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const localeFactory = factoryContainer.locale;

    localeFactory.register('de-DE', deDEMessages);
    localeFactory.register('en-GB', enGBMessages);

    return true;
}
