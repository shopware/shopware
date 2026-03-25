/**
 * @sw-package framework
 */
import initializeLocaleService from 'src/app/init/locale.init';

describe('src/app/init/locale.init.ts', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg1, msg2) => {
                if (typeof msg2 !== 'string') {
                    return false;
                }

                return msg2?.includes('A apiService always needs a name');
            },
        });

        Shopware.Service().register('snippetService', () => {
            return {
                getLocales: jest.fn().mockResolvedValue([]),
                getSnippets: jest.fn().mockResolvedValue({}),
            };
        });
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

        // Mock the snippetService to return expected locales
        Shopware.Service('snippetService').getLocales = () => {
            return Promise.resolve(expectedLocales);
        };

        expect(Shopware.Service('snippetService')).toBeDefined();

        await initializeLocaleService();

        const factoryContainer = Shopware.Application.getContainer('factory');
        const localeRegistry = factoryContainer.locale.getLocaleRegistry();
        const locales = Array.from(localeRegistry.keys());

        expect(locales).toEqual(Object.values(expectedLocales));
    });
});
