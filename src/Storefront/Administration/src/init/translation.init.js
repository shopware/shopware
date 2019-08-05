import { Application } from 'src/core/shopware';
import deDeSnippets from '../app/snippet/de-DE.json';
import enGBSnippets from '../app/snippet/en-GB.json';

Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.extend('de-DE', deDeSnippets);
    localeFactory.extend('en-GB', enGBSnippets);

    return localeFactory;
});
