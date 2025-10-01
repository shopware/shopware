/**
 * @sw-package framework
 */
import initializeLocaleService from 'src/app/init/locale.init';
import initializeApiServices from 'src/app/init-pre/api-services.init';

describe('src/app/init/locale.init.ts', () => {
    beforeAll(() => {
        initializeApiServices();
    });

    it('should register the locale factory with correct snippet languages', async () => {
        global.console.warn = jest.fn();
        await initializeLocaleService();

        expect(Shopware.Application.getContainer('factory').locale).toEqual(
            expect.objectContaining({
                getLocaleByName: expect.any(Function),
                getLocaleRegistry: expect.any(Function),
                register: expect.any(Function),
                extend: expect.any(Function),
                getBrowserLanguage: expect.any(Function),
                getBrowserLanguages: expect.any(Function),
                getLastKnownLocale: expect.any(Function),
                storeCurrentLocale: expect.any(Function),
            }),
        );
    });

    it('should register all locales for languages in the database', async () => {
        const expectedLocales = {
            id1: 'en-GB',
            id2: 'de-DE',
            id3: 'fr-FR',
            id4: 'jp-JP',
        };

        Shopware.Service().register('snippetService', () => {
            return {
                getLocales: () => expectedLocales,
                getSnippets: () => {},
            };
        });

        expect(Shopware.Service('snippetService')).toBeDefined();

        await initializeLocaleService();

        const factoryContainer = Shopware.Application.getContainer('factory');
        const localeRegistry = factoryContainer.locale.getLocaleRegistry();
        const locales = Array.from(localeRegistry.keys());

        expect(locales).toEqual(Object.values(expectedLocales));
    });
});
