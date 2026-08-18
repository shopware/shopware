/**
 * @sw-package framework
 */
import initializeLocaleService from 'src/app/init/locale.init';

const originalNavigatorLanguage = navigator.language;
const originalNavigatorLanguages = navigator.languages;
const originalSystemLanguageId = Shopware.Context.api.systemLanguageId;

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

    beforeEach(() => {
        Shopware.Application.getContainer('factory').locale.getLocaleRegistry().clear();
        Shopware.Application.getContainer('factory').locale.setSystemFallbackLocale(null);
        Shopware.Context.api.systemLanguageId = originalSystemLanguageId;

        window.localStorage.removeItem('sw-admin-locale');

        Object.defineProperty(window.navigator, 'language', {
            value: originalNavigatorLanguage,
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: originalNavigatorLanguages,
            configurable: true,
        });
    });

    afterEach(() => {
        Shopware.Context.api.systemLanguageId = originalSystemLanguageId;
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
                setSystemFallbackLocale: expect.any(Function),
                getLastKnownLocale: expect.any(Function),
                storeCurrentLocale: expect.any(Function),
            }),
        );
    });

    it('should return locale factory when snippet service is not available', async () => {
        global.console.warn = jest.fn();
        const originalService = Shopware.Service;
        Shopware.Service = jest.fn().mockReturnValue(undefined);

        const result = await initializeLocaleService();

        expect(result).toEqual(
            expect.objectContaining({
                register: expect.any(Function),
            }),
        );

        Shopware.Service = originalService;
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

    it('should use the system language locale when browser and english fallbacks are unavailable', async () => {
        Object.defineProperty(window.navigator, 'language', {
            value: 'es-ES',
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: ['es-ES'],
            configurable: true,
        });
        Shopware.Context.api.systemLanguageId = 'system-language-id';

        Shopware.Service('snippetService').getLocales = () => {
            return Promise.resolve({
                'system-language-id': 'de-DE',
            });
        };

        await initializeLocaleService();

        expect(Shopware.Application.getContainer('factory').locale.getLastKnownLocale()).toBe('de-DE');
    });
});
