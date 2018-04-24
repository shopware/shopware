import deDEMessages from 'src/app/snippets/de-DE.json';
import enUKMessages from 'src/app/snippets/en-UK.json';

export default function initializeLocaleService() {
    const factoryContainer = this.getContainer('factory');
    const localeFactory = factoryContainer.locale;

    localeFactory.register('de-DE', deDEMessages);
    localeFactory.register('en-UK', enUKMessages);

    return true;
}
